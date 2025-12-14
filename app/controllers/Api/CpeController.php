<?php

namespace App\Controllers\Api;

require_once __DIR__ . '/BaseApiController.php';

class CpeController extends BaseApiController
{
    /**
     * POST /api/cpe/emitir
     * - Protegido por X-Api-Key
     * - Soporta idempotencia con X-Idempotency-Key
     * - Reserva correlativo por serie (FOR UPDATE)
     * - Genera XML "stub" y lo guarda en: public/cpe/{id_ies}/xml/
     * - Guarda xml_path relativo: cpe/{id_ies}/xml/archivo.xml
     */
    public function emitir()
    {
        $this->requireApiKey();
        $this->maybeReplayIdem();

        $in = json_decode(file_get_contents('php://input'), true) ?? ($_POST ?: []);
        $tipo  = trim((string)($in['tipo_doc'] ?? ''));
        $serie = trim((string)($in['serie'] ?? ''));
        $items = $in['items'] ?? [];

        if (!in_array($tipo, ['01', '03', '07', '08'], true)) {
            $this->error('tipo_doc inválido (01,03,07,08)', 422, 'VALIDATION');
        }
        if ($serie === '') {
            $this->error('Falta serie (ej: F001/B001)', 422, 'VALIDATION');
        }
        if (!is_array($items) || count($items) < 1) {
            $this->error('Faltan items', 422, 'VALIDATION');
        }

        $this->db->beginTransaction();
        try {
            // 1) Reservar correlativo (lock por serie)
            $st = $this->db->prepare("
                SELECT id, correlativo_actual
                  FROM cpe_series
                 WHERE id_ies=? AND tipo_doc=? AND serie=? AND activo=1
                 FOR UPDATE
            ");
            $st->execute([$this->tenantId, $tipo, $serie]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $this->db->prepare("
                    INSERT INTO cpe_series (id_ies, tipo_doc, serie, correlativo_actual, activo, created_at, updated_at)
                    VALUES (?,?,?,?,1,NOW(),NOW())
                ")->execute([$this->tenantId, $tipo, $serie, 0]);

                $seriesId = (int)$this->db->lastInsertId();
                $corr = 1;
                $this->db->prepare("UPDATE cpe_series SET correlativo_actual=? WHERE id=?")
                    ->execute([$corr, $seriesId]);
            } else {
                $seriesId = (int)$row['id'];
                $corr = ((int)$row['correlativo_actual']) + 1;
                $this->db->prepare("UPDATE cpe_series SET correlativo_actual=? WHERE id=?")
                    ->execute([$corr, $seriesId]);
            }

            // 2) Insert documento
            $uuid = $this->uuidv4();
            $cliente = $in['cliente'] ?? [];
            $tot = $in['totales'] ?? [];

            $opg   = (float)($tot['op_gravada'] ?? 0);
            $ope   = (float)($tot['op_exonerada'] ?? 0);
            $opi   = (float)($tot['op_inafecta'] ?? 0);
            $igv   = (float)($tot['igv'] ?? 0);
            $total = (float)($tot['total'] ?? 0);

            $st = $this->db->prepare("
                INSERT INTO cpe_documentos
                (uuid,id_ies,tipo_doc,serie,correlativo,fecha_emision,cliente_doc_tipo,cliente_doc_nro,cliente_nombre,
                 moneda,op_gravada,op_inafecta,op_exonerada,igv,total,estado,created_at,updated_at)
                VALUES
                (?,?,?,?,?,NOW(),?,?,?,?,?,?,?,?,?,'REGISTRADO',NOW(),NOW())
            ");
            $st->execute([
                $uuid,
                $this->tenantId,
                $tipo,
                $serie,
                $corr,
                ($cliente['doc_tipo'] ?? null),
                ($cliente['doc_nro'] ?? null),
                ($cliente['nombre'] ?? null),
                ($in['moneda'] ?? 'PEN'),
                $opg,
                $opi,
                $ope,
                $igv,
                $total,
            ]);
            $idCpe = (int)$this->db->lastInsertId();

            // 3) Insert items
            $n = 1;
            $stI = $this->db->prepare("
                INSERT INTO cpe_items (id_cpe,item_n,codigo,descripcion,unidad,cantidad,valor_unit,precio_unit,igv,total,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
            ");
            foreach ($items as $it) {
                $desc = trim((string)($it['descripcion'] ?? ''));
                if ($desc === '') $this->error('Item sin descripcion', 422, 'VALIDATION');

                $stI->execute([
                    $idCpe,
                    $n++,
                    ($it['codigo'] ?? null),
                    $desc,
                    ($it['unidad'] ?? 'NIU'),
                    (float)($it['cantidad'] ?? 1),
                    (float)($it['valor_unit'] ?? 0),
                    (float)($it['precio_unit'] ?? 0),
                    (float)($it['igv'] ?? 0),
                    (float)($it['total'] ?? 0),
                ]);
            }

            // 4) Guardar XML en carpeta por tenant: public/cpe/{id_ies}/xml/
            $publicRoot = realpath(__DIR__ . '/../../../public');
            if ($publicRoot === false) {
                $this->db->rollBack();
                $this->error('No se ubicó /public', 500, 'PATH_ERROR');
            }

            $xmlDir = $publicRoot
                . DIRECTORY_SEPARATOR . 'cpe'
                . DIRECTORY_SEPARATOR . $this->tenantId
                . DIRECTORY_SEPARATOR . 'xml';

            $this->ensureDir($xmlDir);

            $filename = $this->sanitizeFilename("{$tipo}-{$serie}-{$corr}.xml", 120);
            $absPath  = $this->uniquePath($xmlDir, $filename);

            // XML stub (luego lo reemplazamos por UBL real + firma)
            $xml = "<CPE><tipo>{$tipo}</tipo><serie>{$serie}</serie><correlativo>{$corr}</correlativo><uuid>{$uuid}</uuid></CPE>";
            file_put_contents($absPath, $xml);

            $rel = 'cpe/' . $this->tenantId . '/xml/' . basename($absPath);

            $this->db->prepare("UPDATE cpe_documentos SET xml_path=?, estado='XML_GENERADO' WHERE id=?")
                ->execute([$rel, $idCpe]);

            $this->db->commit();

            // Links seguros (descarga protegida por API key)
            $base = (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '');
            $payload = [
                'ok' => true,
                'uuid' => $uuid,
                'tipo_doc' => $tipo,
                'serie' => $serie,
                'correlativo' => $corr,
                'estado' => 'XML_GENERADO',
                'links' => [
                    'xml' => $base . "/api/cpe/descargar/{$uuid}/xml",
                    'cdr' => $base . "/api/cpe/descargar/{$uuid}/cdr",
                    'pdf' => $base . "/api/cpe/descargar/{$uuid}/pdf",
                ],
            ];
            return $this->respondIdem($payload, 201);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->json(['ok' => false, 'error' => ['code' => 'EXCEPTION', 'message' => $e->getMessage()]], 500);
        }
    }

    /**
     * GET /api/cpe/estado/{uuid}
     * - Protegido por X-Api-Key
     * - Devuelve estado y links seguros de descarga
     */
    public function estado($uuid)
    {
        $this->requireApiKey();

        $uuid = trim((string)$uuid);
        if ($uuid === '') $this->error('uuid inválido', 422, 'VALIDATION');

        $st = $this->db->prepare("
            SELECT uuid, tipo_doc, serie, correlativo, fecha_emision,
                   cliente_doc_tipo, cliente_doc_nro, cliente_nombre,
                   moneda, op_gravada, op_inafecta, op_exonerada, igv, total,
                   estado, sunat_code, sunat_message,
                   xml_path, cdr_path, pdf_path,
                   created_at, updated_at
              FROM cpe_documentos
             WHERE uuid=? AND id_ies=?
             LIMIT 1
        ");
        $st->execute([$uuid, $this->tenantId]);
        $doc = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$doc) $this->error('No encontrado', 404, 'NOT_FOUND');

        $base = (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '');
        $doc['links'] = [
            'xml' => $doc['xml_path'] ? ($base . "/api/cpe/descargar/{$uuid}/xml") : null,
            'cdr' => $doc['cdr_path'] ? ($base . "/api/cpe/descargar/{$uuid}/cdr") : null,
            'pdf' => $doc['pdf_path'] ? ($base . "/api/cpe/descargar/{$uuid}/pdf") : null,
        ];

        return $this->json(['ok' => true, 'data' => $doc], 200);
    }

    /**
     * GET /api/cpe/descargar/{uuid}/{tipo}
     * - Protegido por X-Api-Key
     * - Valida que el documento pertenezca al id_ies (tenant)
     * - Lee el archivo real y lo entrega con readfile()
     */
    public function descargar($uuid = null, $tipo = null)
    {
        // 1) Seguridad: API KEY + tenantId (id_ies)
        $this->requireApiKey();

        $uuid = is_string($uuid) ? trim($uuid) : '';
        $tipo = is_string($tipo) ? strtolower(trim($tipo)) : '';

        // 2) Validaciones básicas anti path traversal
        if ($uuid === '' || strlen($uuid) > 80 || !preg_match('/^[A-Za-z0-9\-_.]+$/', $uuid)) {
            $this->error('UUID inválido', 422, 'VALIDATION');
        }

        $map = [
            'xml' => ['ext' => 'xml', 'mime' => 'application/xml; charset=utf-8'],
            'cdr' => ['ext' => 'zip', 'mime' => 'application/zip'],
            'pdf' => ['ext' => 'pdf', 'mime' => 'application/pdf'],
        ];

        if (!isset($map[$tipo])) {
            $this->error('Tipo inválido. Use: xml|cdr|pdf', 422, 'VALIDATION');
        }

        $ext  = $map[$tipo]['ext'];
        $mime = $map[$tipo]['mime'];

        // 3) Resolver /public
        $publicRoot = realpath(__DIR__ . '/../../../public');
        if ($publicRoot === false) {
            $this->error('No se ubicó /public', 500, 'PATH_ERROR');
        }

        // 4) Rutas por IES (separado por cliente)
        $tenant = (int)$this->tenantId;
        $baseDir = $publicRoot . DIRECTORY_SEPARATOR . 'cpe'
            . DIRECTORY_SEPARATOR . $tenant
            . DIRECTORY_SEPARATOR . $tipo;

        // Path principal: /public/cpe/{id_ies}/{tipo}/{uuid}.{ext}
        $path1 = $baseDir . DIRECTORY_SEPARATOR . $uuid . '.' . $ext;

        // Fallback opcional si usas: /public/cpe/{id_ies}/{uuid}/{tipo}.{ext}
        $path2 = $publicRoot . DIRECTORY_SEPARATOR . 'cpe'
            . DIRECTORY_SEPARATOR . $tenant
            . DIRECTORY_SEPARATOR . $uuid
            . DIRECTORY_SEPARATOR . $tipo . '.' . $ext;

        $filePath = null;
        if (is_file($path1)) $filePath = $path1;
        elseif (is_file($path2)) $filePath = $path2;

        if ($filePath === null) {
            $this->error('Archivo no encontrado', 404, 'NOT_FOUND', [
                'uuid' => $uuid,
                'tipo' => $tipo,
                'id_ies' => $tenant
            ]);
        }

        // 5) Headers de descarga (seguro)
        if (ob_get_level()) @ob_end_clean();

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);

        $downloadName = $uuid . '.' . $ext;
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');

        $size = @filesize($filePath);
        if ($size !== false) header('Content-Length: ' . $size);

        // Opcional: cache-control
        header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // 6) Entregar
        @readfile($filePath);
        exit;
    }

    private function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
