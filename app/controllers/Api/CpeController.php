<?php

namespace App\Controllers\Api;

use PDO;

require_once __DIR__ . '/BaseApiController.php';

class CpeController extends BaseApiController
{
    private string $tableDocs    = 'cpe_documentos';
    private string $tableSeries  = 'cpe_series';
    private string $tableItems   = 'cpe_items';
    private string $tableEmisores = 'cpe_emisores';
    private string $tableCred = 'cpe_sunat_credenciales';


    /* ===================== Helpers ===================== */
    private function money($n): string
    {
        return number_format((float)$n, 2, '.', '');
    }

    private function getDocFullByUuid(string $uuid): array
    {
        $st = $this->db->prepare("
        SELECT *
          FROM {$this->tableDocs}
         WHERE uuid=? AND id_ies=?
         LIMIT 1
        ");
        $st->execute([$uuid, (int)$this->tenantId]);
        $doc = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$doc) $this->error('Documento no encontrado', 404, 'NOT_FOUND', ['uuid' => $uuid]);

        $st = $this->db->prepare("
        SELECT item_n, codigo, descripcion, unidad, cantidad, valor_unit, precio_unit, igv, total
          FROM {$this->tableItems}
         WHERE id_cpe=?
         ORDER BY item_n ASC
        ");
        $st->execute([(int)$doc['id']]); // <- doc.id
        $items = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [$doc, $items];
    }

    private function getAfectacionCode(array $doc): string
    {
        $igv = (float)($doc['igv'] ?? 0);
        if ($igv > 0) return '10'; // Gravada

        $exo  = (float)($doc['op_exonerada'] ?? 0);
        $inaf = (float)($doc['op_inafecta'] ?? 0);

        if ($exo > 0)  return '20'; // Exonerada
        if ($inaf > 0) return '30'; // Inafecta

        return '10';
    }



    private function validateUuid(string $uuid): void
    {
        $uuid = trim($uuid);
        if ($uuid === '' || strlen($uuid) > 80) {
            $this->error('UUID inválido', 422, 'VALIDATION');
        }
        if (!preg_match('/^[A-Za-z0-9\-]+$/', $uuid)) {
            $this->error('UUID inválido', 422, 'VALIDATION');
        }
    }

    private function getEmisor(int $idIes): array
    {
        $st = $this->db->prepare("
        SELECT id, id_ies, ruc, razon_social, nombre_comercial, ubigeo, direccion,
               departamento, provincia, distrito, email, telefono
          FROM {$this->tableEmisores}
         WHERE id_ies=? 
         LIMIT 1
    ");
        $st->execute([(int)$idIes]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        if (!$r) {
            $this->error('Emisor no configurado para esta IES', 422, 'EMISOR_NOT_CONFIGURED', [
                'id_ies' => $idIes
            ]);
        }

        $ruc = trim((string)($r['ruc'] ?? ''));
        if ($ruc === '' || !ctype_digit($ruc) || strlen($ruc) !== 11) {
            $this->error('Emisor con RUC inválido. Verifica cpe_emisores', 422, 'EMISOR_RUC_INVALID', [
                'id_ies' => $idIes,
                'ruc'    => $ruc
            ]);
        }

        return $r;
    }

    private function getCredenciales(int $idIes, string $modo = 'beta'): array
    {
        $modo = strtolower(trim($modo));
        if (!in_array($modo, ['beta', 'prod'], true)) $modo = 'beta';

        $st = $this->db->prepare("
        SELECT id, id_ies, modo, sol_user, sol_pass_enc, cert_pfx_enc, cert_pass_enc, activo
          FROM {$this->tableCred}
         WHERE id_ies=? AND modo=? AND activo=1
         LIMIT 1
    ");
        $st->execute([(int)$idIes, $modo]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        if (!$r) {
            $this->error('Credenciales SUNAT no configuradas o inactivas para este modo', 422, 'SUNAT_CREDENTIALS_NOT_CONFIGURED', [
                'id_ies' => (int)$idIes,
                'modo'   => $modo
            ]);
        }

        // Para firma/envío a SUNAT, deben existir los 3 secretos
        $missing = [];
        if (trim((string)($r['sol_user'] ?? '')) === '') $missing[] = 'sol_user';
        if (empty($r['sol_pass_enc'])) $missing[] = 'sol_pass';
        if (empty($r['cert_pfx_enc'])) $missing[] = 'cert_pfx';
        if (empty($r['cert_pass_enc'])) $missing[] = 'cert_pass';

        if ($missing) {
            $this->error('Credenciales SUNAT incompletas. Completa los campos faltantes en Admin.', 422, 'SUNAT_CREDENTIALS_INCOMPLETE', [
                'id_ies'   => (int)$idIes,
                'modo'     => $modo,
                'missing'  => $missing
            ]);
        }

        return $r;
    }


    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    private function escapeXml(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function buildXmlStub(string $tipoDoc, string $serie, int $corr, string $moneda, array $in, string $rucEmisor): string
    {
        $cliente = $in['cliente']['nombre'] ?? 'CLIENTE';
        $corrPad = str_pad((string)$corr, 8, '0', STR_PAD_LEFT);

        // Stub mínimo (luego lo cambiamos por UBL 2.1 + firma)
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CPE>
  <EmisorRUC>{$this->escapeXml($rucEmisor)}</EmisorRUC>
  <TipoDoc>{$this->escapeXml($tipoDoc)}</TipoDoc>
  <Serie>{$this->escapeXml($serie)}</Serie>
  <Correlativo>{$this->escapeXml($corrPad)}</Correlativo>
  <Moneda>{$this->escapeXml($moneda)}</Moneda>
  <Cliente>{$this->escapeXml((string)$cliente)}</Cliente>
</CPE>
XML;
    }

    private function getIes(int $idIes): array
    {
        $st = $this->db->prepare("SELECT id, nombre_ies, estado FROM ies WHERE id=? LIMIT 1");
        $st->execute([$idIes]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) $this->error('IES no existe', 401, 'IES_NOT_FOUND');
        if (($r['estado'] ?? 'activa') !== 'activa') $this->error('IES suspendida', 403, 'IES_SUSPENDED');
        return $r;
    }


    private function getDocByUuid(string $uuid): ?array
    {
        $st = $this->db->prepare("
            SELECT id, uuid, id_ies, tipo_doc, serie, correlativo, estado,
                   xml_path, cdr_path, pdf_path, hash
              FROM {$this->tableDocs}
             WHERE uuid = ? AND id_ies = ?
             LIMIT 1
        ");
        $st->execute([$uuid, (int)$this->tenantId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * Garantiza cpe_series(id_ies,tipo_doc,serie). Devuelve correlativo seguro (FOR UPDATE).
     */
    private function nextCorrelativo(int $idIes, string $tipoDoc, string $serie): int
    {
        // IMPORTANTE: esto debe correrse dentro de una transacción (ya lo haces en emitir)
        $st = $this->db->prepare("
        SELECT id, correlativo_actual
          FROM {$this->tableSeries}
         WHERE id_ies=? AND tipo_doc=? AND serie=? AND activo=1
         LIMIT 1
         FOR UPDATE
    ");
        $st->execute([$idIes, $tipoDoc, $serie]);

        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->error('Serie no existe o no está activa. Configúrala en Admin.', 422, 'SERIE_NOT_ACTIVE', [
                'id_ies'    => (int)$idIes,
                'tipo_doc'  => $tipoDoc,
                'serie'     => $serie,
            ]);
        }

        $next = ((int)($row['correlativo_actual'] ?? 0)) + 1;

        $up = $this->db->prepare("
        UPDATE {$this->tableSeries}
           SET correlativo_actual=?, updated_at=NOW()
         WHERE id=?
         LIMIT 1
    ");
        $up->execute([$next, (int)$row['id']]);

        return $next;
    }


    private function isHttpUrl(string $v): bool
    {
        $v = trim($v);
        if ($v === '') return false;
        return (bool)preg_match('~^https?://~i', $v);
    }

    private function docBaseName(string $rucEmisor, string $tipoDoc, string $serie, int $correlativo): string
    {
        $corrPad = str_pad((string)$correlativo, 8, '0', STR_PAD_LEFT);
        // Estándar SUNAT: RUC-TIPO-SERIE-CORRELATIVO
        return "{$rucEmisor}-{$tipoDoc}-{$serie}-{$corrPad}";
    }

    /* ===================== Endpoints ===================== */

    /**
     * POST /api/cpe/emitir
     * Enfoque nuevo:
     * - Admin (API): genera/valida, asigna correlativo, registra en BD
     * - Devuelve xml_b64 (y luego cdr_b64/pdf_b64)
     * - Cliente guarda los archivos localmente
     */
    public function emitir()
    {
        $this->requireApiKey();
        $this->maybeReplayIdem();

        $in = json_decode(file_get_contents('php://input'), true);
        if (!is_array($in)) $in = [];

        $modo    = trim((string)($in['modo'] ?? 'beta')); // 👈 NUEVO
        $tipoDoc = trim((string)($in['tipo_doc'] ?? ''));
        $serie   = trim((string)($in['serie'] ?? ''));
        $moneda  = trim((string)($in['moneda'] ?? 'PEN'));

        if ($tipoDoc === '' || $serie === '') {
            $this->error('Faltan campos: tipo_doc, serie', 422, 'VALIDATION');
        }

        $items = $in['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            $this->error('Faltan items', 422, 'VALIDATION');
        }

        $idIes = (int)$this->tenantId;

        // ✅ 1) IES activa (ya estaba)
        $ies = $this->getIes($idIes);

        // ✅ 2) Emisor desde cpe_emisores (ya lo implementaste)
        $emisor = $this->getEmisor($idIes);
        $rucEmisor = trim((string)($emisor['ruc'] ?? ''));

        // ✅ 3) Credenciales SUNAT activas y completas (NUEVO)
        $cred = $this->getCredenciales($idIes, $modo);

        // (si quieres, guardas $modo real usado)
        $modo = strtolower($cred['modo'] ?? $modo);

        // resto normal...
        $clienteTipo = trim((string)($in['cliente']['doc_tipo'] ?? ''));
        $clienteNro  = trim((string)($in['cliente']['doc_nro']  ?? ''));
        $clienteNom  = trim((string)($in['cliente']['nombre']   ?? ''));

        $opGrav = (float)($in['totales']['op_gravada'] ?? 0);
        $opInaf = (float)($in['totales']['op_inafecta'] ?? 0);
        $opExo  = (float)($in['totales']['op_exonerada'] ?? 0);
        $igv    = (float)($in['totales']['igv'] ?? 0);
        $total  = (float)($in['totales']['total'] ?? 0);

        $uuid = $this->uuidV4();

        try {
            $this->db->beginTransaction();

            // ✅ 4) Serie activa + correlativo seguro (ya NO crea series)
            $correlativo = $this->nextCorrelativo($idIes, $tipoDoc, $serie);

            $xmlContent = $this->buildXmlStub($tipoDoc, $serie, $correlativo, $moneda, $in, $rucEmisor);
            $hash = hash('sha256', $xmlContent);

            $st = $this->db->prepare("
            INSERT INTO {$this->tableDocs}
            (uuid, id_ies, tipo_doc, serie, correlativo, fecha_emision,
             cliente_doc_tipo, cliente_doc_nro, cliente_nombre,
             moneda, op_gravada, op_inafecta, op_exonerada, igv, total,
             estado, xml_path, cdr_path, pdf_path, hash, created_at, updated_at)
            VALUES
            (?,?,?,?,?, NOW(),
             ?,?,?, 
             ?,?,?,?,?,?,
             'XML_GENERADO', NULL, NULL, NULL, ?, NOW(), NOW())
        ");
            $st->execute([
                $uuid,
                $idIes,
                $tipoDoc,
                $serie,
                $correlativo,
                $clienteTipo ?: null,
                $clienteNro  ?: null,
                $clienteNom  ?: null,
                $moneda,
                $opGrav,
                $opInaf,
                $opExo,
                $igv,
                $total,
                $hash
            ]);

            $idCpe = (int)$this->db->lastInsertId();

            $stI = $this->db->prepare("
            INSERT INTO {$this->tableItems}
            (id_cpe, item_n, codigo, descripcion, unidad, cantidad, valor_unit, precio_unit, igv, total, created_at)
            VALUES
            (?,?,?,?,?,?,?,?,?,?, NOW())
        ");

            $n = 1;
            foreach ($items as $it) {
                if (!is_array($it)) continue;

                $desc = trim((string)($it['descripcion'] ?? ''));
                if ($desc === '') {
                    $this->db->rollBack();
                    $this->error("Item $n: falta descripcion", 422, 'VALIDATION');
                }

                $stI->execute([
                    $idCpe,
                    $n,
                    isset($it['codigo']) ? trim((string)$it['codigo']) : null,
                    $desc,
                    trim((string)($it['unidad'] ?? 'NIU')) ?: 'NIU',
                    (float)($it['cantidad'] ?? 1),
                    (float)($it['valor_unit'] ?? 0),
                    (float)($it['precio_unit'] ?? 0),
                    (float)($it['igv'] ?? 0),
                    (float)($it['total'] ?? 0),
                ]);
                $n++;
            }

            $this->db->commit();

            $baseName = $this->docBaseName($rucEmisor, $tipoDoc, $serie, $correlativo);

            $payload = [
                'ok' => true,
                'uuid' => $uuid,
                'id_ies' => $idIes,
                'modo' => $modo, // 👈 para trazabilidad
                'tipo_doc' => $tipoDoc,
                'serie' => $serie,
                'correlativo' => $correlativo,
                'estado' => 'XML_GENERADO',
                'hash' => $hash,
                'suggested_files' => [
                    'xml' => $baseName . '.xml',
                    'cdr' => 'R-' . $baseName . '.zip',
                    'pdf' => $baseName . '.pdf',
                ],
                'files' => [
                    'xml_b64' => base64_encode($xmlContent),
                ],
                'links' => [
                    'registrar_archivos' => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/archivos/{$uuid}",
                    'descargar_xml'      => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/descargar/{$uuid}/xml",
                    'descargar_cdr'      => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/descargar/{$uuid}/cdr",
                    'descargar_pdf'      => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/descargar/{$uuid}/pdf",
                ],
            ];

            return $this->respondIdem($payload, 201);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->error('EXCEPTION: ' . $e->getMessage(), 500, 'EXCEPTION');
        }
    }


    /**
     * POST /api/cpe/archivos/{uuid}
     * El Cliente reporta dónde guardó los archivos (ideal: URLs públicas del cliente).
     * body JSON:
     * { "xml_url":"https://cliente.../cpe/...xml", "cdr_url":"...", "pdf_url":"..." }
     */
    public function archivos($uuid = null)
    {
        $this->requireApiKey();

        $uuid = (string)$uuid;
        $this->validateUuid($uuid);

        $doc = $this->getDocByUuid($uuid);
        if (!$doc) {
            $this->error('Documento no encontrado', 404, 'NOT_FOUND', ['uuid' => $uuid]);
        }

        $in = json_decode(file_get_contents('php://input'), true);
        if (!is_array($in)) $in = [];

        $xml = trim((string)($in['xml_url'] ?? $in['xml_path'] ?? ''));
        $cdr = trim((string)($in['cdr_url'] ?? $in['cdr_path'] ?? ''));
        $pdf = trim((string)($in['pdf_url'] ?? $in['pdf_path'] ?? ''));

        // Recomendación: registrar como URL pública del cliente
        // (o un path del cliente si luego implementarás proxy interno).
        if ($xml !== '' && !$this->isHttpUrl($xml)) {
            $this->error('xml_url debe ser URL http(s)', 422, 'VALIDATION');
        }
        if ($cdr !== '' && !$this->isHttpUrl($cdr)) {
            $this->error('cdr_url debe ser URL http(s)', 422, 'VALIDATION');
        }
        if ($pdf !== '' && !$this->isHttpUrl($pdf)) {
            $this->error('pdf_url debe ser URL http(s)', 422, 'VALIDATION');
        }

        $st = $this->db->prepare("
    UPDATE {$this->tableDocs}
       SET xml_path = COALESCE(NULLIF(?,''), xml_path),
           cdr_path = COALESCE(NULLIF(?,''), cdr_path),
           pdf_path = COALESCE(NULLIF(?,''), pdf_path),
           estado = CASE
                     WHEN (COALESCE(NULLIF(?,''), xml_path) IS NOT NULL AND COALESCE(NULLIF(?,''), xml_path) <> '')
                       OR (COALESCE(NULLIF(?,''), cdr_path) IS NOT NULL AND COALESCE(NULLIF(?,''), cdr_path) <> '')
                       OR (COALESCE(NULLIF(?,''), pdf_path) IS NOT NULL AND COALESCE(NULLIF(?,''), pdf_path) <> '')
                     THEN 'ARCHIVOS_REGISTRADOS'
                     ELSE estado
                   END,
           updated_at = NOW()
     WHERE uuid=? AND id_ies=?
     LIMIT 1
");
        $st->execute([
            $xml,
            $cdr,
            $pdf,
            $xml,
            $xml,
            $cdr,
            $cdr,
            $pdf,
            $pdf,
            $uuid,
            (int)$this->tenantId
        ]);


        return $this->json([
            'ok' => true,
            'uuid' => $uuid,
            'stored' => [
                'xml_path' => $xml ?: ($doc['xml_path'] ?? null),
                'cdr_path' => $cdr ?: ($doc['cdr_path'] ?? null),
                'pdf_path' => $pdf ?: ($doc['pdf_path'] ?? null),
            ]
        ], 200);
    }

    /**
     * GET /api/cpe/descargar/{uuid}/{tipo}
     * Nuevo enfoque:
     * - Admin NO tiene archivos locales.
     * - Si xml_path/cdr_path/pdf_path está registrado como URL del cliente => redirect 302.
     */
    public function descargar($uuid = null, $tipo = null)
    {
        $this->requireApiKey();

        $uuid = (string)$uuid;
        $tipo = strtolower((string)$tipo);
        $this->validateUuid($uuid);

        $tipoMap = [
            'xml' => 'xml_path',
            'cdr' => 'cdr_path',
            'pdf' => 'pdf_path',
        ];
        if (!isset($tipoMap[$tipo])) {
            $this->error('Tipo inválido. Use: xml|cdr|pdf', 422, 'VALIDATION');
        }

        $doc = $this->getDocByUuid($uuid);
        if (!$doc) {
            $this->error('Documento no encontrado', 404, 'NOT_FOUND', [
                'uuid' => $uuid,
                'id_ies' => (int)$this->tenantId
            ]);
        }

        $col = $tipoMap[$tipo];
        $url = trim((string)($doc[$col] ?? ''));

        if ($url === '') {
            $this->error('Archivo no disponible todavía (no registrado por el cliente)', 404, 'NOT_READY', [
                'uuid' => $uuid,
                'tipo' => $tipo,
                'estado' => $doc['estado'] ?? null
            ]);
        }

        if (!$this->isHttpUrl($url)) {
            $this->error('Archivo registrado no es URL http(s). Ajusta /api/cpe/archivos/{uuid}', 422, 'BAD_FILE_URL', [
                'value' => $url
            ]);
        }

        // Seguridad extra: evita cache
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Redirect controlado por API (validó API key + id_ies)
        header('Location: ' . $url, true, 302);
        exit;
    }

    /**
     * GET /api/cpe/estado/{uuid}
     * Devuelve el estado actual y los links/paths registrados por el cliente.
     */
    public function estado($uuid = null)
    {
        $this->requireApiKey();

        $uuid = (string)$uuid;
        $this->validateUuid($uuid);

        $st = $this->db->prepare("
        SELECT id, uuid, id_ies, tipo_doc, serie, correlativo, fecha_emision,
               cliente_doc_tipo, cliente_doc_nro, cliente_nombre,
               moneda, op_gravada, op_inafecta, op_exonerada, igv, total,
               estado, xml_path, cdr_path, pdf_path, hash, created_at, updated_at
          FROM {$this->tableDocs}
         WHERE uuid=? AND id_ies=?
         LIMIT 1
    ");
        $st->execute([$uuid, (int)$this->tenantId]);
        $doc = $st->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            $this->error('Documento no encontrado', 404, 'NOT_FOUND', [
                'uuid' => $uuid,
                'id_ies' => (int)$this->tenantId
            ]);
        }

        // (Opcional) sugerencia de nombres de archivo si existe emisor
        $suggested = null;
        try {
            $emisor = $this->getEmisor((int)$this->tenantId);
            $rucEmisor = trim((string)($emisor['ruc'] ?? ''));
            if ($rucEmisor !== '') {
                $baseName = $this->docBaseName($rucEmisor, (string)$doc['tipo_doc'], (string)$doc['serie'], (int)$doc['correlativo']);
                $suggested = [
                    'xml' => $baseName . '.xml',
                    'cdr' => 'R-' . $baseName . '.zip',
                    'pdf' => $baseName . '.pdf',
                ];
            }
        } catch (\Throwable $e) {
            // No romper estado si falta emisor. Simplemente no damos suggested_files.
        }

        return $this->json([
            'ok' => true,
            'uuid' => $doc['uuid'],
            'id_ies' => (int)$doc['id_ies'],
            'tipo_doc' => $doc['tipo_doc'],
            'serie' => $doc['serie'],
            'correlativo' => (int)$doc['correlativo'],
            'fecha_emision' => $doc['fecha_emision'],
            'estado' => $doc['estado'],
            'hash' => $doc['hash'],
            'totales' => [
                'moneda' => $doc['moneda'],
                'op_gravada' => (float)$doc['op_gravada'],
                'op_inafecta' => (float)$doc['op_inafecta'],
                'op_exonerada' => (float)$doc['op_exonerada'],
                'igv' => (float)$doc['igv'],
                'total' => (float)$doc['total'],
            ],
            'cliente' => [
                'doc_tipo' => $doc['cliente_doc_tipo'],
                'doc_nro' => $doc['cliente_doc_nro'],
                'nombre' => $doc['cliente_nombre'],
            ],
            'files' => [
                'xml_path' => $doc['xml_path'],
                'cdr_path' => $doc['cdr_path'],
                'pdf_path' => $doc['pdf_path'],
                'has_xml'  => !empty($doc['xml_path']),
                'has_cdr'  => !empty($doc['cdr_path']),
                'has_pdf'  => !empty($doc['pdf_path']),
            ],
            'suggested_files' => $suggested,
            'timestamps' => [
                'created_at' => $doc['created_at'],
                'updated_at' => $doc['updated_at'],
            ],
            'links' => [
                'registrar_archivos' => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/archivos/{$uuid}",
                'descargar_xml'      => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/descargar/{$uuid}/xml",
                'descargar_cdr'      => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/descargar/{$uuid}/cdr",
                'descargar_pdf'      => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/descargar/{$uuid}/pdf",
            ],
        ], 200);
    }

    private function sunatWsdl(string $modo): string
    {
        $path = __DIR__ . '/../../Wsdl/billService.wsdl';
        if (is_file($path)) return $path;
        // Fallback remoto (pero ya sabes que puede fallar)
        if (strtolower($modo) === 'prod') {
            return 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService?wsdl';
        }
        return 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService?wsdl';
    }


    private function loadSunatCredencial(int $idIes): array
    {
        // Usamos el modelo Admin porque ya tiene cifrado
        require_once __DIR__ . '/../../models/Admin/CpeSunatCredencial.php';
        $m = new \App\Models\Admin\CpeSunatCredencial();

        $sec = $m->getSecretsByIes($idIes);
        if (!$sec || (int)$sec['activo'] !== 1) {
            $this->error('Credenciales SUNAT no configuradas o inactivas', 422, 'SUNAT_CREDENTIALS_MISSING', [
                'id_ies' => $idIes
            ]);
        }
        if (empty($sec['sol_user']) || empty($sec['sol_pass']) || empty($sec['cert_pfx']) || empty($sec['cert_pass'])) {
            $this->error('Credenciales SUNAT incompletas (SOL + PFX + pass)', 422, 'SUNAT_CREDENTIALS_INCOMPLETE');
        }
        return $sec;
    }


    //==================== Helpers privados SUNAT ====================

    private function buildSunatUsername(string $rucEmisor, string $solUser): string
    {
        $rucEmisor = trim($rucEmisor);
        $solUser   = trim($solUser);

        // SUNAT normalmente exige: RUC + USUARIO_SOL
        if (str_starts_with($solUser, $rucEmisor)) return $solUser;
        return $rucEmisor . $solUser;
    }

    private function zipXmlBase64(string $xmlContent, string $baseName): array
    {
        $xmlName = $baseName . '.xml';
        $zipName = $baseName . '.zip';

        // 1) Resolver temp dir usable (fallback)
        $tmpDir = sys_get_temp_dir();
        if (!$tmpDir || !is_dir($tmpDir) || !is_writable($tmpDir)) {
            $tmpDir = '/tmp';
        }

        // (opcional) si /tmp tampoco sirve, usa un folder dentro del proyecto
        if (!is_dir($tmpDir) || !is_writable($tmpDir)) {
            $tmpDir = __DIR__ . '/../../storage/tmp';
            if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);
        }

        if (!is_dir($tmpDir) || !is_writable($tmpDir)) {
            throw new \RuntimeException("No hay directorio temporal escribible. tmpDir={$tmpDir}");
        }

        // 2) Crear archivo temporal
        $tmp = tempnam($tmpDir, 'cpezip_');
        if ($tmp === false || $tmp === '') {
            throw new \RuntimeException("tempnam() falló. tmpDir={$tmpDir}");
        }

        // 3) Crear zip
        $zip = new \ZipArchive();
        $ok = $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($ok !== true) {
            @unlink($tmp);
            throw new \RuntimeException("No se pudo crear ZIP. ZipArchive::open code={$ok}");
        }

        $zip->addFromString($xmlName, $xmlContent);
        $zip->close();

        $zipBin = file_get_contents($tmp);
        @unlink($tmp);

        if ($zipBin === false) {
            throw new \RuntimeException('No se pudo leer ZIP generado');
        }

        return [
            'zip_name' => $zipName,
            'zip_b64'  => base64_encode($zipBin),
        ];
    }


    private function parseCdrZip(string $cdrZipBinary): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cdr_');
        if ($tmp === false || $tmp === '') {
            throw new \RuntimeException('tempnam() falló creando archivo temporal para CDR');
        }
        file_put_contents($tmp, $cdrZipBinary);

        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            return ['ok' => false, 'message' => 'No se pudo abrir CDR ZIP'];
        }

        $cdrXml = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name && preg_match('/\.xml$/i', $name)) {
                $cdrXml = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();
        @unlink($tmp);

        if (!$cdrXml) return ['ok' => false, 'message' => 'CDR XML no encontrado en ZIP'];

        // Extraer ResponseCode + Description (tolerante)
        $doc = new \DOMDocument();
        $doc->loadXML($cdrXml);

        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $code = trim((string)$xp->evaluate("string(//cbc:ResponseCode)"));
        $desc = trim((string)$xp->evaluate("string(//cbc:Description)"));

        return [
            'ok' => true,
            'response_code' => $code,
            'description'   => $desc,
            'cdr_xml'       => $cdrXml,
        ];
    }

    //=========================== Firma XML ===========================
    private function signXmlWithPfx(string $xml, string $pfxBinary, string $pfxPass): string
    {
        
        error_log("PFX len=" . strlen((string)$pfxBinary));
        error_log("PFX head=" . substr((string)$pfxBinary, 0, 30));

        
        if (!openssl_pkcs12_read($pfxBinary, $certs, $pfxPass)) {
            throw new \RuntimeException('No se pudo leer el PFX (password incorrecto o archivo inválido).');
        }
        $pkey = $certs['pkey'] ?? null;
        $cert = $certs['cert'] ?? null;
        if (!$pkey || !$cert) {
            throw new \RuntimeException('PFX no contiene pkey/cert.');
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        // xmlseclibs
        $objDSig = new \RobRichards\XMLSecLibs\XMLSecurityDSig();
        $objDSig->setCanonicalMethod(\RobRichards\XMLSecLibs\XMLSecurityDSig::EXC_C14N);

        // En UBL real se firma un nodo específico.
        // Por ahora firmamos el documento completo (sirve como infraestructura; UBL lo ajustamos en el sub-paso).
        $objDSig->addReference(
            $dom,
            \RobRichards\XMLSecLibs\XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true]
        );

        $objKey = new \RobRichards\XMLSecLibs\XMLSecurityKey(
            \RobRichards\XMLSecLibs\XMLSecurityKey::RSA_SHA256,
            ['type' => 'private']
        );
        $objKey->loadKey($pkey, false);

        $objDSig->sign($objKey);
        $objDSig->add509Cert($cert, true, false, ['subjectName' => true]);

        // Adjunta <ds:Signature> al root
        $objDSig->appendSignature($dom->documentElement);

        return $dom->saveXML();
    }



//==================== Endpoint SUNAT ====================

    /**
     * POST /api/cpe/enviar/{uuid}
     * - Firma XML
     * - Envía a SUNAT (sendBill)
     * - Devuelve CDR zip en base64 para que el cliente lo guarde
     */
    public function enviar($uuid = null)
    {
        $this->requireApiKey();
        $this->maybeReplayIdem();

        $uuid = (string)$uuid;
        $this->validateUuid($uuid);

        // Traer doc básico
        $doc = $this->getDocByUuid($uuid);
        if (!$doc) {
            $this->error('Documento no encontrado', 404, 'NOT_FOUND', ['uuid' => $uuid]);
        }

        $idIes = (int)$this->tenantId;

        // Emisor + credenciales
        $emisor = $this->getEmisor($idIes);
        $rucEmisor = trim((string)$emisor['ruc']);

        // OJO: aquí sigues usando el modelo que ya desencripta secretos (correcto)
        $cred = $this->loadSunatCredencial($idIes);
        $modo = (string)($cred['modo'] ?? 'beta');

        $username = $this->buildSunatUsername($rucEmisor, (string)$cred['sol_user']);
        $password = (string)$cred['sol_pass'];

        // 1) Traer documento completo + items desde BD
        [$docFull, $items] = $this->getDocFullByUuid($uuid);

        if (!is_array($items) || count($items) === 0) {
            $this->error('Documento sin items', 422, 'ITEMS_EMPTY', ['uuid' => $uuid]);
        }

        // 2) Validar totales contra items (si descuadra, mejor detener antes de SUNAT)
        try {
            $this->assertTotals($docFull, $items);
        } catch (\Throwable $e) {
            $this->error('Descuadre de totales: ' . $e->getMessage(), 422, 'TOTALS_MISMATCH');
        }

        // 3) Construir UBL según tipo_doc (en orden)
        $tipoDoc = (string)($docFull['tipo_doc'] ?? '');
        try {
            switch ($tipoDoc) {
                case '01': // FACTURA
                    $xmlUbl = $this->buildUblInvoice21($docFull, $emisor, $items);
                    break;

                case '03': // BOLETA (siguiente paso)
                    $this->error('Boleta aún no implementada en UBL (siguiente paso)', 422, 'NOT_IMPLEMENTED');
                    break;

                case '07': // NOTA DE CRÉDITO (después)
                    $this->error('Nota de crédito aún no implementada en UBL', 422, 'NOT_IMPLEMENTED');
                    break;

                case '08': // NOTA DE DÉBITO (después)
                    $this->error('Nota de débito aún no implementada en UBL', 422, 'NOT_IMPLEMENTED');
                    break;

                default:
                    $this->error('Tipo de documento no soportado', 422, 'TIPO_DOC_INVALID', ['tipo_doc' => $tipoDoc]);
            }
        } catch (\Throwable $e) {
            $this->error('Error construyendo UBL: ' . $e->getMessage(), 422, 'UBL_BUILD_ERROR');
        }

        // 4) Firmar UBL dentro de ext:ExtensionContent (SUNAT)
        try {
            $xmlSigned = $this->signUblIntoExtension($xmlUbl, (string)$cred['cert_pfx'], (string)$cred['cert_pass']);
        } catch (\Throwable $e) {
            $this->error('Error firmando UBL: ' . $e->getMessage(), 500, 'UBL_SIGN_ERROR');
        }

        $hashSigned = hash('sha256', $xmlSigned);

        // 5) ZIP baseName estándar SUNAT: RUC-TIPO-SERIE-CORRELATIVO
        $baseName = $this->docBaseName(
            $rucEmisor,
            (string)$docFull['tipo_doc'],
            (string)$docFull['serie'],
            (int)$docFull['correlativo']
        );
        $z = $this->zipXmlBase64($xmlSigned, $baseName);

        // 6) SOAP sendBill
        $wsdl = $this->sunatWsdl($modo);

        // Definir el endpoint real según el modo
        $endpoint = ($modo === 'prod')
            ? 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService'
            : 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';

        try {
            // Opciones para el cliente SOAP
            $opts = [
                'cache_wsdl' => WSDL_CACHE_NONE,
                'trace' => 1,
                'exceptions' => true,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT // Requerido por SUNAT
                    ]
                ])
            ];

            $client = new \SoapClient($wsdl, $opts);
            // FORZAR la URL de destino (esto soluciona el problema del WSDL local)
            $client->__setLocation($endpoint);
            // WS-Security UsernameToken
            $ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
            $auth = '
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                <wsse:UsernameToken>
                    <wsse:Username>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</wsse:Username>
                    <wsse:Password>' . htmlspecialchars($password, ENT_QUOTES, 'UTF-8') . '</wsse:Password>
                </wsse:UsernameToken>
                </wsse:Security>';
            $header = new \SoapHeader($ns, 'Security', new \SoapVar($auth, XSD_ANYXML), true);
            $client->__setSoapHeaders([$header]);

            $resp = $client->__soapCall('sendBill', [[
                'fileName'    => $z['zip_name'],
                'contentFile' => $z['zip_b64'],
            ]]);

            $appRespB64 = $resp->applicationResponse ?? null;
            if (!$appRespB64) {
                $this->error('SUNAT no devolvió applicationResponse', 502, 'SUNAT_EMPTY_RESPONSE');
            }

            $cdrZipBin = base64_decode((string)$appRespB64, true);
            if ($cdrZipBin === false) {
                $this->error('CDR inválido (base64)', 502, 'SUNAT_BAD_CDR');
            }

            $cdrInfo = $this->parseCdrZip($cdrZipBin);

            // Estado final
            $estado = 'CDR_RECIBIDO';
            if (!empty($cdrInfo['response_code'])) {
                $estado = ((string)$cdrInfo['response_code'] === '0') ? 'ACEPTADO' : 'RECHAZADO';
            }

            // Guardar trazabilidad en BD (aprovecha tus columnas)
            $st = $this->db->prepare("
            UPDATE {$this->tableDocs}
               SET estado=?,
                   hash=?,
                   sunat_code=?,
                   sunat_message=?,
                   enviado_at = COALESCE(enviado_at, NOW()),
                   cdr_recibido_at = NOW(),
                   updated_at=NOW()
             WHERE uuid=? AND id_ies=?
             LIMIT 1
        ");
            $st->execute([
                $estado,
                $hashSigned,
                $cdrInfo['response_code'] ?? null,
                $cdrInfo['description'] ?? null,
                $uuid,
                $idIes
            ]);

            $payload = [
                'ok' => true,
                'uuid' => $uuid,
                'estado' => $estado,
                'hash' => $hashSigned,
                'sunat' => [
                    'modo' => $modo,
                    'wsdl' => $wsdl,
                    'response_code' => $cdrInfo['response_code'] ?? null,
                    'description'   => $cdrInfo['description'] ?? null,
                ],
                'suggested_files' => [
                    'xml' => $baseName . '.xml',
                    'cdr' => 'R-' . $baseName . '.zip',
                ],
                'files' => [
                    'xml_signed_b64' => base64_encode($xmlSigned),
                    'cdr_zip_b64'    => base64_encode($cdrZipBin),
                ],
                'links' => [
                    'registrar_archivos' => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/archivos/{$uuid}",
                    'estado'             => (defined('BASE_URL') ? BASE_URL : '') . "/api/cpe/estado/{$uuid}",
                ],
            ];

            return $this->respondIdem($payload, 200);
        } catch (\SoapFault $sf) {
            $msg = $sf->getMessage();
            $this->error('SUNAT SOAPFAULT: ' . $msg, 502, 'SUNAT_SOAPFAULT');
        } catch (\Throwable $e) {
            $this->error('EXCEPTION: ' . $e->getMessage(), 500, 'EXCEPTION');
        }
    }




    //==================== Fin Endpoint SUNAT ====================

    private function assertTotals(array $doc, array $items): void
    {
        $sumBase = 0.0;
        $sumIgv  = 0.0;
        $sumTot  = 0.0;

        foreach ($items as $it) {
            $qty = (float)($it['cantidad'] ?? 1);
            $vu  = (float)($it['valor_unit'] ?? 0);
            $sumBase += ($vu * $qty);

            $sumIgv += (float)($it['igv'] ?? 0);
            $sumTot += (float)($it['total'] ?? 0);
        }

        $lineExtBD = (float)($doc['op_gravada'] ?? 0) + (float)($doc['op_inafecta'] ?? 0) + (float)($doc['op_exonerada'] ?? 0);
        $igvBD     = (float)($doc['igv'] ?? 0);
        $totalBD   = (float)($doc['total'] ?? 0);

        $eps = 0.02; // tolerancia 2 cent
        if (abs($sumBase - $lineExtBD) > $eps) {
            throw new \RuntimeException("Descuadre base: items={$this->money($sumBase)} vs doc={$this->money($lineExtBD)}");
        }
        if (abs($sumIgv - $igvBD) > $eps) {
            throw new \RuntimeException("Descuadre IGV: items={$this->money($sumIgv)} vs doc={$this->money($igvBD)}");
        }
        if (abs($sumTot - $totalBD) > $eps) {
            throw new \RuntimeException("Descuadre TOTAL: items={$this->money($sumTot)} vs doc={$this->money($totalBD)}");
        }
    }


    //==================== Helpers privados UBL ====================

    private function buildUblInvoice21(array $doc, array $emisor, array $items): string
    {
        $tipoDoc = (string)$doc['tipo_doc']; // "01"
        if ($tipoDoc !== '01') {
            throw new \RuntimeException('buildUblInvoice21 solo aplica a tipo_doc=01');
        }

        $serie   = (string)$doc['serie'];
        $corr    = (int)$doc['correlativo'];
        $corrPad = str_pad((string)$corr, 8, '0', STR_PAD_LEFT);
        $idDoc   = "{$serie}-{$corrPad}";

        $moneda = (string)($doc['moneda'] ?: 'PEN');

        // fecha_emision datetime -> IssueDate + IssueTime
        $dt = !empty($doc['fecha_emision']) ? new \DateTime((string)$doc['fecha_emision']) : new \DateTime();
        $issueDate = $dt->format('Y-m-d');
        $issueTime = $dt->format('H:i:s');

        // Emisor desde cpe_emisores
        $rucEmi = trim((string)$emisor['ruc']);
        $rsEmi  = trim((string)($emisor['razon_social'] ?? ''));
        $ncEmi  = trim((string)($emisor['nombre_comercial'] ?? ''));

        // Cliente desde cpe_documentos
        $cliTipo = trim((string)($doc['cliente_doc_tipo'] ?? '')); // catalogo 06 (1 DNI, 6 RUC, etc)
        $cliNro  = trim((string)($doc['cliente_doc_nro'] ?? ''));
        $cliNom  = trim((string)($doc['cliente_nombre'] ?? 'CLIENTE'));

        // Totales (tal cual tu BD)
        $opGrav = (float)($doc['op_gravada'] ?? 0);
        $opInaf = (float)($doc['op_inafecta'] ?? 0);
        $opExo  = (float)($doc['op_exonerada'] ?? 0);
        $igv    = (float)($doc['igv'] ?? 0);
        $total  = (float)($doc['total'] ?? 0);

        // Para SUNAT, LineExtensionAmount suele ser suma de bases (sin IGV).
        $lineExt = $opGrav + $opInaf + $opExo;

        // Validación mínima: items no vacíos
        if (!is_array($items) || count($items) === 0) {
            throw new \RuntimeException('Documento sin items.');
        }

        // Afectación (por doc)
        $afect = $this->getAfectacionCode($doc);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $nsInv = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
        $nsCbc = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        $nsCac = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
        $nsExt = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

        $inv = $dom->createElementNS($nsInv, 'Invoice');
        $inv->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', $nsCbc);
        $inv->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', $nsCac);
        $inv->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', $nsExt);
        $dom->appendChild($inv);

        // UBLExtensions (firma aquí)
        $exts = $dom->createElementNS($nsExt, 'ext:UBLExtensions');
        $ext  = $dom->createElementNS($nsExt, 'ext:UBLExtension');
        $extC = $dom->createElementNS($nsExt, 'ext:ExtensionContent');
        $ext->appendChild($extC);
        $exts->appendChild($ext);
        $inv->appendChild($exts);

        // Cabecera UBL
        $inv->appendChild($dom->createElementNS($nsCbc, 'cbc:UBLVersionID', '2.1'));
        $inv->appendChild($dom->createElementNS($nsCbc, 'cbc:CustomizationID', '2.0'));
        // recomendado en PE:
        $inv->appendChild($dom->createElementNS($nsCbc, 'cbc:ProfileID', '0101'));

        $inv->appendChild($dom->createElementNS($nsCbc, 'cbc:ID', $idDoc));
        $inv->appendChild($dom->createElementNS($nsCbc, 'cbc:IssueDate', $issueDate));
        $inv->appendChild($dom->createElementNS($nsCbc, 'cbc:IssueTime', $issueTime));

        $itc = $dom->createElementNS($nsCbc, 'cbc:InvoiceTypeCode', $tipoDoc);
        $inv->appendChild($itc);

        $inv->appendChild($dom->createElementNS($nsCbc, 'cbc:DocumentCurrencyCode', $moneda));

        // Signature reference (#SignatureKG)
        $sig = $dom->createElementNS($nsCac, 'cac:Signature');
        $sig->appendChild($dom->createElementNS($nsCbc, 'cbc:ID', 'IDSignKG'));
        $sp = $dom->createElementNS($nsCac, 'cac:SignatoryParty');

        $spId = $dom->createElementNS($nsCac, 'cac:PartyIdentification');
        $cbcId = $dom->createElementNS($nsCbc, 'cbc:ID', $rucEmi);
        $spId->appendChild($cbcId);
        $sp->appendChild($spId);

        $spName = $dom->createElementNS($nsCac, 'cac:PartyName');
        $spName->appendChild($dom->createElementNS($nsCbc, 'cbc:Name', $ncEmi !== '' ? $ncEmi : $rsEmi));
        $sp->appendChild($spName);

        $sig->appendChild($sp);

        $att = $dom->createElementNS($nsCac, 'cac:DigitalSignatureAttachment');
        $er  = $dom->createElementNS($nsCac, 'cac:ExternalReference');
        $er->appendChild($dom->createElementNS($nsCbc, 'cbc:URI', '#SignatureKG'));
        $att->appendChild($er);
        $sig->appendChild($att);

        $inv->appendChild($sig);

        // ===================== Supplier (Emisor) UBL 2.1 =====================
        $sup = $dom->createElementNS($nsCac, 'cac:AccountingSupplierParty');
        $party = $dom->createElementNS($nsCac, 'cac:Party');

        $pid = $dom->createElementNS($nsCac, 'cac:PartyIdentification');
        $pidId = $dom->createElementNS($nsCbc, 'cbc:ID', $rucEmi);
        $pidId->setAttribute('schemeID', '6'); // RUC
        $pid->appendChild($pidId);
        $party->appendChild($pid);

        $pname = $dom->createElementNS($nsCac, 'cac:PartyName');
        $pname->appendChild($dom->createElementNS($nsCbc, 'cbc:Name', $ncEmi !== '' ? $ncEmi : $rsEmi));
        $party->appendChild($pname);

        $ple = $dom->createElementNS($nsCac, 'cac:PartyLegalEntity');
        $ple->appendChild($dom->createElementNS($nsCbc, 'cbc:RegistrationName', $rsEmi));
        $party->appendChild($ple);

        // Dirección emisor (mínimo)
        $addr = $dom->createElementNS($nsCac, 'cac:PostalAddress');
        if (!empty($emisor['ubigeo'])) $addr->appendChild($dom->createElementNS($nsCbc, 'cbc:ID', (string)$emisor['ubigeo']));
        if (!empty($emisor['direccion'])) $addr->appendChild($dom->createElementNS($nsCbc, 'cbc:StreetName', (string)$emisor['direccion']));
        if (!empty($emisor['departamento'])) $addr->appendChild($dom->createElementNS($nsCbc, 'cbc:CountrySubentity', (string)$emisor['departamento']));
        if (!empty($emisor['provincia'])) $addr->appendChild($dom->createElementNS($nsCbc, 'cbc:CityName', (string)$emisor['provincia']));
        if (!empty($emisor['distrito'])) $addr->appendChild($dom->createElementNS($nsCbc, 'cbc:District', (string)$emisor['distrito']));
        $country = $dom->createElementNS($nsCac, 'cac:Country');
        $country->appendChild($dom->createElementNS($nsCbc, 'cbc:IdentificationCode', 'PE'));
        $addr->appendChild($country);
        $party->appendChild($addr);

        $sup->appendChild($party);
        $inv->appendChild($sup);

        // ===================== Customer (Cliente) UBL 2.1 =====================
        $cus = $dom->createElementNS($nsCac, 'cac:AccountingCustomerParty');
        $cparty = $dom->createElementNS($nsCac, 'cac:Party');

        if ($cliNro !== '') {
            $cpid = $dom->createElementNS($nsCac, 'cac:PartyIdentification');
            $cpidId = $dom->createElementNS($nsCbc, 'cbc:ID', $cliNro);
            if ($cliTipo !== '') $cpidId->setAttribute('schemeID', $cliTipo); // 1 DNI, 6 RUC, etc.
            $cpid->appendChild($cpidId);
            $cparty->appendChild($cpid);
        }

        $cple = $dom->createElementNS($nsCac, 'cac:PartyLegalEntity');
        $cple->appendChild($dom->createElementNS($nsCbc, 'cbc:RegistrationName', $cliNom));
        $cparty->appendChild($cple);

        $cus->appendChild($cparty);
        $inv->appendChild($cus);

        // ===================== TaxTotal =====================
        $taxTotal = $dom->createElementNS($nsCac, 'cac:TaxTotal');
        $taxAmount = $dom->createElementNS($nsCbc, 'cbc:TaxAmount', $this->money($igv));
        $taxAmount->setAttribute('currencyID', $moneda);
        $taxTotal->appendChild($taxAmount);

        // Subtotal IGV (si gravada)
        $sub = $dom->createElementNS($nsCac, 'cac:TaxSubtotal');
        $taxableBase = ($afect === '10') ? $opGrav : 0;

        $taxable = $dom->createElementNS($nsCbc, 'cbc:TaxableAmount', $this->money($taxableBase));
        $taxable->setAttribute('currencyID', $moneda);
        $sub->appendChild($taxable);

        $subTaxAmount = $dom->createElementNS($nsCbc, 'cbc:TaxAmount', $this->money($igv));
        $subTaxAmount->setAttribute('currencyID', $moneda);
        $sub->appendChild($subTaxAmount);

        $cat = $dom->createElementNS($nsCac, 'cac:TaxCategory');
        $cat->appendChild($dom->createElementNS($nsCbc, 'cbc:ID', 'S'));
        if ($afect === '10') $cat->appendChild($dom->createElementNS($nsCbc, 'cbc:Percent', '18.00'));
        $cat->appendChild($dom->createElementNS($nsCbc, 'cbc:TaxExemptionReasonCode', $afect));

        $scheme = $dom->createElementNS($nsCac, 'cac:TaxScheme');
        $scheme->appendChild($dom->createElementNS($nsCbc, 'cbc:ID', '1000'));
        $scheme->appendChild($dom->createElementNS($nsCbc, 'cbc:Name', 'IGV'));
        $scheme->appendChild($dom->createElementNS($nsCbc, 'cbc:TaxTypeCode', 'VAT'));
        $cat->appendChild($scheme);

        $sub->appendChild($cat);
        $taxTotal->appendChild($sub);
        $inv->appendChild($taxTotal);

        // ===================== MonetaryTotal =====================
        $mt = $dom->createElementNS($nsCac, 'cac:LegalMonetaryTotal');

        $le = $dom->createElementNS($nsCbc, 'cbc:LineExtensionAmount', $this->money($lineExt));
        $le->setAttribute('currencyID', $moneda);
        $mt->appendChild($le);

        $te = $dom->createElementNS($nsCbc, 'cbc:TaxExclusiveAmount', $this->money($lineExt));
        $te->setAttribute('currencyID', $moneda);
        $mt->appendChild($te);

        $ti = $dom->createElementNS($nsCbc, 'cbc:TaxInclusiveAmount', $this->money($total));
        $ti->setAttribute('currencyID', $moneda);
        $mt->appendChild($ti);

        $pa = $dom->createElementNS($nsCbc, 'cbc:PayableAmount', $this->money($total));
        $pa->setAttribute('currencyID', $moneda);
        $mt->appendChild($pa);

        $inv->appendChild($mt);

        // ===================== Lines =====================
        $n = 1;
        foreach ($items as $it) {
            $qty = (float)($it['cantidad'] ?? 1);
            $vu  = (float)($it['valor_unit'] ?? 0);
            $pu  = (float)($it['precio_unit'] ?? 0);
            $igvIt = (float)($it['igv'] ?? 0);

            $desc = trim((string)($it['descripcion'] ?? ''));
            if ($desc === '') $desc = "ITEM {$n}";

            $lineExtIt = $vu * $qty; // base sin IGV

            $line = $dom->createElementNS($nsCac, 'cac:InvoiceLine');
            $line->appendChild($dom->createElementNS($nsCbc, 'cbc:ID', (string)$n));

            $iq = $dom->createElementNS($nsCbc, 'cbc:InvoicedQuantity', $this->money($qty));
            $iq->setAttribute('unitCode', (string)($it['unidad'] ?: 'NIU'));
            $line->appendChild($iq);

            $lea = $dom->createElementNS($nsCbc, 'cbc:LineExtensionAmount', $this->money($lineExtIt));
            $lea->setAttribute('currencyID', $moneda);
            $line->appendChild($lea);

            // PricingReference (precio con IGV)
            $pr = $dom->createElementNS($nsCac, 'cac:PricingReference');
            $acp = $dom->createElementNS($nsCac, 'cac:AlternativeConditionPrice');
            $pa1 = $dom->createElementNS($nsCbc, 'cbc:PriceAmount', $this->money($pu));
            $pa1->setAttribute('currencyID', $moneda);
            $acp->appendChild($pa1);
            $acp->appendChild($dom->createElementNS($nsCbc, 'cbc:PriceTypeCode', '01'));
            $pr->appendChild($acp);
            $line->appendChild($pr);

            // TaxTotal línea
            $tt = $dom->createElementNS($nsCac, 'cac:TaxTotal');
            $ta = $dom->createElementNS($nsCbc, 'cbc:TaxAmount', $this->money($igvIt));
            $ta->setAttribute('currencyID', $moneda);
            $tt->appendChild($ta);

            $ts = $dom->createElementNS($nsCac, 'cac:TaxSubtotal');
            $taxable2 = $dom->createElementNS($nsCbc, 'cbc:TaxableAmount', $this->money($lineExtIt));
            $taxable2->setAttribute('currencyID', $moneda);
            $ts->appendChild($taxable2);

            $taxAmt2 = $dom->createElementNS($nsCbc, 'cbc:TaxAmount', $this->money($igvIt));
            $taxAmt2->setAttribute('currencyID', $moneda);
            $ts->appendChild($taxAmt2);

            $cat2 = $dom->createElementNS($nsCac, 'cac:TaxCategory');
            $cat2->appendChild($dom->createElementNS($nsCbc, 'cbc:ID', 'S'));
            if ($afect === '10') $cat2->appendChild($dom->createElementNS($nsCbc, 'cbc:Percent', '18.00'));
            $cat2->appendChild($dom->createElementNS($nsCbc, 'cbc:TaxExemptionReasonCode', $afect));

            $scheme2 = $dom->createElementNS($nsCac, 'cac:TaxScheme');
            $scheme2->appendChild($dom->createElementNS($nsCbc, 'cbc:ID', '1000'));
            $scheme2->appendChild($dom->createElementNS($nsCbc, 'cbc:Name', 'IGV'));
            $scheme2->appendChild($dom->createElementNS($nsCbc, 'cbc:TaxTypeCode', 'VAT'));
            $cat2->appendChild($scheme2);

            $ts->appendChild($cat2);
            $tt->appendChild($ts);
            $line->appendChild($tt);

            // Item
            $item = $dom->createElementNS($nsCac, 'cac:Item');
            $item->appendChild($dom->createElementNS($nsCbc, 'cbc:Description', $desc));

            $codigo = trim((string)($it['codigo'] ?? ''));
            if ($codigo !== '') {
                $sid = $dom->createElementNS($nsCac, 'cac:SellersItemIdentification');
                $sid->appendChild($dom->createElementNS($nsCbc, 'cbc:ID', $codigo));
                $item->appendChild($sid);
            }
            $line->appendChild($item);

            // Price (valor sin IGV)
            $price = $dom->createElementNS($nsCac, 'cac:Price');
            $pa2 = $dom->createElementNS($nsCbc, 'cbc:PriceAmount', $this->money($vu));
            $pa2->setAttribute('currencyID', $moneda);
            $price->appendChild($pa2);
            $line->appendChild($price);

            $inv->appendChild($line);
            $n++;
        }

        return $dom->saveXML();
    }




    //==================== Firma UBL ====================

    private function signUblIntoExtension(string $ublXml, string $pfxBinary, string $pfxPass): string
    {
        if (!openssl_pkcs12_read($pfxBinary, $certs, $pfxPass)) {
            throw new \RuntimeException('No se pudo leer el PFX (password incorrecto o archivo inválido).');
        }
        $pkey = $certs['pkey'] ?? null;
        $cert = $certs['cert'] ?? null;
        if (!$pkey || !$cert) throw new \RuntimeException('PFX no contiene pkey/cert.');

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($ublXml);

        // Encontrar ext:ExtensionContent
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');

        $extContent = $xp->query('//ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent')->item(0);
        if (!$extContent) throw new \RuntimeException('No se encontró ext:ExtensionContent para insertar la firma.');

        // Crear firma
        $objDSig = new \RobRichards\XMLSecLibs\XMLSecurityDSig();
        $objDSig->setCanonicalMethod(\RobRichards\XMLSecLibs\XMLSecurityDSig::EXC_C14N);

        // Referencia al documento completo (URI="")
        $objDSig->addReference(
            $dom,
            \RobRichards\XMLSecLibs\XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true, 'uri' => '']
        );

        $objKey = new \RobRichards\XMLSecLibs\XMLSecurityKey(
            \RobRichards\XMLSecLibs\XMLSecurityKey::RSA_SHA256,
            ['type' => 'private']
        );
        $objKey->loadKey($pkey, false);

        $objDSig->sign($objKey);
        $objDSig->add509Cert($cert, true, false, ['subjectName' => true]);

        // Poner Id consistente con cac:Signature/cbc:URI -> #SignatureKG
        $sigNode = $objDSig->sigNode;
        if ($sigNode instanceof \DOMElement) {
            $sigNode->setAttribute('Id', 'SignatureKG');
        }

        // Insertar ds:Signature dentro de ExtensionContent
        $objDSig->appendSignature($extContent);

        return $dom->saveXML();
    }

    private function normalizePfxBinary(string $pfx): string
    {
        $pfx = trim($pfx);
        if ($pfx === '') return '';

        // Si parece base64 (sin caracteres raros) y al decodificar inicia con 0x30 (ASN.1),
        // lo tratamos como base64.
        if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $pfx)) {
            $bin = base64_decode($pfx, true);
            if ($bin !== false && $bin !== '' && ord($bin[0]) === 0x30) {
                return $bin;
            }
        }
        return $pfx; // ya es binario
    }
}
