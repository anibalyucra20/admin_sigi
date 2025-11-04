<?php

namespace App\Controllers\Api;

require_once __DIR__ . '/BaseApiController.php';

class LibraryController extends BaseApiController
{
    /* ---------- helpers ---------- */
    private function mapRow(array $r, ?array $cfg = null): array
    {
        $cfg = $cfg ?? $this->cfg();
        return [
            'id'          => (int)$r['id'],
            'owner_ies'   => (int)$r['id_ies'],
            'titulo'      => $r['titulo'],
            'autor'       => $r['autor'],
            'isbn'        => $r['isbn'],
            'tipo_libro'  => $r['tipo_libro'],
            'anio'        => $r['anio'],
            'portada_url' => !empty($r['portada']) ? ($cfg['library']['covers_base_url'] . '/' . $r['portada']) : null,
            'archivo_url' => $cfg['library']['files_base_url'] . '/' . $r['libro'],
        ];
    }

    /* ========== POST /api/library/upload (multipart/form-data) ========== */
    public function upload()
    {
        $this->requireApiKey(); // protege el endpoint

        // --- Config de almacenamiento ---
        $publicRoot = realpath(__DIR__ . '/../../../public');
        if ($publicRoot === false) {
            return $this->json(['ok' => false, 'error' => ['code' => 'PATH_ERROR', 'message' => 'No se ubicó /public']], 500);
        }
        $booksDir  = $publicRoot . DIRECTORY_SEPARATOR . 'books';
        $coversDir = $publicRoot . DIRECTORY_SEPARATOR . 'covers';
        if (!is_dir($booksDir) && !@mkdir($booksDir, 0755, true)) {
            return $this->json(['ok' => false, 'error' => ['code' => 'MKDIR_FAIL', 'message' => 'No se pudo crear /public/books']], 500);
        }
        if (!is_dir($coversDir) && !@mkdir($coversDir, 0755, true)) {
            return $this->json(['ok' => false, 'error' => ['code' => 'MKDIR_FAIL', 'message' => 'No se pudo crear /public/covers']], 500);
        }

        // --- Entrada: soporta multipart o JSON ---
        $isMultipart = !empty($_FILES) || (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false);
        $in = $isMultipart ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);

        $titulo = trim($in['titulo'] ?? '');
        $tipo   = trim($in['tipo_libro'] ?? '');
        if ($titulo === '' || $tipo === '') {
            return $this->json(['ok' => false, 'error' => ['code' => 'MISSING_FIELDS', 'message' => 'Faltan campos: titulo, tipo_libro']], 422);
        }

        // --- Validaciones de archivos ---
        $bookFilename   = null;
        $coverFilename  = null;
        $maxBytesBook   = 80 * 1024 * 1024;  // 80MB
        $maxBytesCover  = 5  * 1024 * 1024;  // 5MB
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        // Helper para sanear nombre
        $sanitize = function (string $name, string $fallbackExt) {
            $name = basename($name);
            // quita espacios raros y caracteres peligrosos
            $name = preg_replace('~[^A-Za-z0-9._-]+~', '-', $name) ?? 'file';
            // evita nombres vacíos/extensión perdida
            if (!preg_match('/\.[A-Za-z0-9]{1,5}$/', $name)) {
                $name .= '.' . $fallbackExt;
            }
            // prefijo único para colisiones y límite 100 chars
            $pref = substr(uniqid('', true), 0, 8) . '-';
            $name = $pref . $name;
            return substr($name, 0, 100);
        };

        try {
            // 1) LIBRO (multipart)
            if ($isMultipart && isset($_FILES['libro']) && $_FILES['libro']['error'] !== UPLOAD_ERR_NO_FILE) {
                $f = $_FILES['libro'];
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    return $this->json(['ok' => false, 'error' => ['code' => 'UPLOAD_ERROR', 'message' => 'Error subiendo libro', 'php' => $f['error']]], 422);
                }
                if ($f['size'] > $maxBytesBook) {
                    return $this->json(['ok' => false, 'error' => ['code' => 'FILE_TOO_LARGE', 'message' => 'Libro supera el tamaño permitido']], 413);
                }

                if (!is_uploaded_file($f['tmp_name'])) {
                    return $this->json(['ok' => false, 'error' => ['code' => 'NOT_UPLOADED', 'message' => 'El archivo no es una subida válida (multipart)']], 422);
                }

                $mime = $finfo->file($f['tmp_name']) ?: '';
                $allowed = ['application/pdf', 'application/epub+zip'];
                if (!in_array($mime, $allowed, true)) {
                    return $this->json(['ok' => false, 'error' => ['code' => 'BAD_MIME', 'message' => "Tipo de archivo no permitido ($mime)"]], 415);
                }
                $ext = $mime === 'application/pdf' ? 'pdf' : 'epub';

                $bookFilename = $sanitize($f['name'] ?: "libro.$ext", $ext);
                $dest = $booksDir . DIRECTORY_SEPARATOR . $bookFilename;

                clearstatcache(true, $booksDir);
                if (!is_dir($booksDir)) {
                    return $this->json(['ok' => false, 'error' => ['code' => 'DIR_MISSING', 'message' => 'Directorio /public/books no existe']], 500);
                }
                if (!is_writable($booksDir)) {
                    return $this->json(['ok' => false, 'error' => ['code' => 'DIR_NOT_WRITABLE', 'message' => '/public/books no es escribible']], 500);
                }

                if (!@move_uploaded_file($f['tmp_name'], $dest)) {
                    $err = error_get_last()['message'] ?? 'desconocido';
                    return $this->json([
                        'ok' => false,
                        'error' => [
                            'code' => 'MOVE_FAIL',
                            'message' => 'No se pudo guardar el libro',
                            'context' => [
                                'tmp'         => $f['tmp_name'],
                                'dest'        => $dest,
                                'existsTmp'   => file_exists($f['tmp_name']),
                                'writableDir' => is_writable($booksDir),
                                'lastError'   => $err,
                            ]
                        ]
                    ], 500);
                }
            }

            // 2) PORTADA (multipart) opcional
            if ($isMultipart && isset($_FILES['portada']) && $_FILES['portada']['error'] !== UPLOAD_ERR_NO_FILE) {
                $f = $_FILES['portada'];
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    return $this->json(['ok' => false, 'error' => ['code' => 'UPLOAD_ERROR', 'message' => 'Error subiendo portada', 'php' => $f['error']]], 422);
                }
                if ($f['size'] > $maxBytesCover) {
                    return $this->json(['ok' => false, 'error' => ['code' => 'FILE_TOO_LARGE', 'message' => 'Portada supera el tamaño permitido']], 413);
                }
                $mime = $finfo->file($f['tmp_name']) ?: '';
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($mime, $allowed, true)) {
                    return $this->json(['ok' => false, 'error' => ['code' => 'BAD_MIME', 'message' => "Tipo de portada no permitido ($mime)"]], 415);
                }
                $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
                $coverFilename = $sanitize($f['name'] ?: "portada.$ext", $ext);
                if (!@move_uploaded_file($f['tmp_name'], $coversDir . DIRECTORY_SEPARATOR . $coverFilename)) {
                    return $this->json(['ok' => false, 'error' => ['code' => 'MOVE_FAIL', 'message' => 'No se pudo guardar la portada']], 500);
                }
            }

            // 3) Modo JSON: nombres de archivo
            if (!$bookFilename)  $bookFilename  = substr(basename(trim($in['libro'] ?? '')), 0, 100);
            if (!$coverFilename) $coverFilename = substr(basename(trim($in['portada'] ?? '')), 0, 100);
            if ($bookFilename === '') {
                return $this->json(['ok' => false, 'error' => ['code' => 'MISSING_BOOK', 'message' => 'Falta archivo de libro (libro)']], 422);
            }

            // 4) Insert en DB
            $sql = "INSERT INTO biblioteca_libros
                (titulo, autor, editorial, edicion, tomo, tipo_libro, isbn, paginas, anio,
                 temas_relacionados, tags, portada, libro, id_ies, fecha_registro)
                VALUES (:titulo,:autor,:editorial,:edicion,:tomo,:tipo_libro,:isbn,:paginas,:anio,
                        :temas,:tags,:portada,:libro,:id_ies,NOW())";
            $st = $this->db->prepare($sql);
            $st->execute([
                ':titulo'     => $titulo,
                ':autor'      => $in['autor']      ?? '',
                ':editorial'  => $in['editorial']  ?? '',
                ':edicion'    => $in['edicion']    ?? '',
                ':tomo'       => $in['tomo']       ?? '',
                ':tipo_libro' => $tipo,
                ':isbn'       => $in['isbn']       ?? '',
                ':paginas'    => (int)($in['paginas'] ?? 0),
                ':anio'       => (int)($in['anio'] ?? 0),
                ':temas'      => $in['temas_relacionados'] ?? '',
                ':tags'       => $in['tags'] ?? '',
                ':portada'    => $coverFilename ?? '',
                ':libro'      => $bookFilename,
                ':id_ies'     => $this->tenantId,
            ]);

            $id = (int)$this->db->lastInsertId();

            // Adoptar inmediatamente si viene la cadena completa
            $keys = ['id_programa_estudio', 'id_plan', 'id_modulo_formativo', 'id_semestre', 'id_unidad_didactica'];
            $hasChain = !array_diff_key(array_flip($keys), $in);

            $adopted = false;
            $duplicated = null;

            if ($hasChain) {
                $sqlV = "INSERT INTO biblioteca_vinculos
            (id_ies, id_libro, id_programa_estudio, id_plan, id_modulo_formativo, id_semestre, id_unidad_didactica, created_at)
            VALUES (?,?,?,?,?,?,?, NOW())
            ON DUPLICATE KEY UPDATE id=id";
                $stV = $this->db->prepare($sqlV);
                $stV->execute([
                    $this->tenantId,
                    $id,
                    (int)$in['id_programa_estudio'],
                    (int)$in['id_plan'],
                    (int)$in['id_modulo_formativo'],
                    (int)$in['id_semestre'],
                    (int)$in['id_unidad_didactica'],
                ]);
                $duplicated = ($stV->rowCount() === 0);
                $adopted = true;
            }

            return $this->json([
                'ok'         => true,
                'id'         => $id,
                'owner_ies'  => $this->tenantId,
                'adopted'    => $adopted,
                'duplicated' => $duplicated,
            ], 201);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => ['code' => 'EXCEPTION', 'message' => $e->getMessage()]], 500);
        }
    }

    /* ========== POST /api/library/adopt/{libro_id} ========== */
    public function adopt($libroId)
    {
        $this->requireApiKey();
        $this->maybeReplayIdem();

        $libroId = (int)$libroId;
        if ($libroId <= 0) {
            $this->error('libro_id inválido', 422, 'VALIDATION');
        }

        // 1) El libro debe existir
        $st = $this->db->prepare("SELECT id_ies FROM biblioteca_libros WHERE id=? LIMIT 1");
        $st->execute([$libroId]);
        $ownerIes = $st->fetchColumn();
        if ($ownerIes === false) {
            $this->error('Libro no existe', 404, 'NOT_FOUND');
        }

        // 2) Cadena académica obligatoria
        $body = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?? []);
        $req  = ['id_programa_estudio', 'id_plan', 'id_modulo_formativo', 'id_semestre', 'id_unidad_didactica'];
        foreach ($req as $k) {
            if (empty($body[$k])) $this->error("Falta $k", 422, 'VALIDATION');
            $body[$k] = (int)$body[$k];
            if ($body[$k] <= 0) $this->error("$k inválido", 422, 'VALIDATION');
        }

        // 4) Insert idempotente en biblioteca_vinculos
        $sql = "INSERT INTO biblioteca_vinculos
            (id_ies, id_libro, id_programa_estudio, id_plan, id_modulo_formativo, id_semestre, id_unidad_didactica, created_at)
            VALUES (?,?,?,?,?,?,?, NOW())
            ON DUPLICATE KEY UPDATE id = id";
        try {
            $st = $this->db->prepare($sql);
            $st->execute([
                $this->tenantId,
                $libroId,
                $body['id_programa_estudio'],
                $body['id_plan'],
                $body['id_modulo_formativo'],
                $body['id_semestre'],
                $body['id_unidad_didactica'],
            ]);

            $duplicated = ($st->rowCount() === 0);
            $payload = ['ok' => true, 'adopted_libro_id' => $libroId, 'duplicated' => $duplicated];
            return $this->respondIdem($payload, $duplicated ? 200 : 201);
        } catch (\PDOException $e) {
            $sqlState = $e->errorInfo[1] ?? null;
            if ($sqlState === 1452) {
                return $this->json(['ok' => false, 'error' => ['code' => 'FK_VIOLATION', 'message' => 'Alguna referencia académica no existe']], 422);
            }
            return $this->json(['ok' => false, 'error' => ['code' => 'DB_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    /* ========== DELETE /api/library/adopt/{libro_id} ========== */
    public function unadopt($libroId)
    {
        $this->requireApiKey();
        $libroId = (int)$libroId;
        $body = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?? []);

        $hasChain = isset($body['id_programa_estudio'], $body['id_plan'], $body['id_modulo_formativo'], $body['id_semestre'], $body['id_unidad_didactica']);

        if ($hasChain) {
            $sql = "DELETE FROM biblioteca_vinculos
                    WHERE id_ies=? AND id_libro=? AND
                          id_programa_estudio=? AND id_plan=? AND id_modulo_formativo=? AND id_semestre=? AND id_unidad_didactica=?";
            $st = $this->db->prepare($sql);
            $st->execute([
                $this->tenantId,
                $libroId,
                (int)$body['id_programa_estudio'],
                (int)$body['id_plan'],
                (int)$body['id_modulo_formativo'],
                (int)$body['id_semestre'],
                (int)$body['id_unidad_didactica'],
            ]);
            $this->json(['ok' => true, 'deleted' => $st->rowCount()], 200);
        } else {
            $st = $this->db->prepare("DELETE FROM biblioteca_vinculos WHERE id_ies=? AND id_libro=?");
            $st->execute([$this->tenantId, $libroId]);
            $this->json(['ok' => true, 'deleted' => $st->rowCount()], 200);
        }
    }

    /* ========== GET /api/library/items (propios + adoptados) ========== */
    public function items()
    {
        $this->requireApiKey();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $off  = ($page - 1) * $per;

        $q = trim($_GET['search'] ?? '');

        // Un solo input: titulo, autor, temas_relacionados
        $cond = [];
        $pars = [];
        if ($q !== '') {
            $cond[] = "(bl.titulo LIKE ? OR bl.autor LIKE ? OR bl.temas_relacionados LIKE ?)";
            $like = "%$q%";
            $pars[] = $like;
            $pars[] = $like;
            $pars[] = $like;
        }
        $W = $cond ? (' AND ' . implode(' AND ', $cond)) : '';

        $sql = "
        SELECT bl.id, bl.id_ies, bl.titulo, bl.autor, bl.isbn, bl.tipo_libro, bl.portada, bl.libro, bl.anio
          FROM biblioteca_libros bl
         WHERE bl.id_ies = ? $W
        UNION
        SELECT bl.id, bl.id_ies, bl.titulo, bl.autor, bl.isbn, bl.tipo_libro, bl.portada, bl.libro, bl.anio
          FROM biblioteca_vinculos v
          JOIN biblioteca_libros bl ON bl.id = v.id_libro
         WHERE v.id_ies = ? $W
         ORDER BY 1 DESC
         LIMIT " . (int)$off . ", " . (int)$per;

        // bind = [tenant, filtros..., tenant, filtros...]
        $bind = array_merge([$this->tenantId], $pars, [$this->tenantId], $pars);

        $st = $this->db->prepare($sql);
        $i = 1;
        foreach ($bind as $val) {
            $st->bindValue($i++, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        $data = array_map([$this, 'mapRow'], $rows); // sin cfg
        return $this->json(['data' => $data, 'page' => $page, 'per_page' => $per]);
    }

    /* ========== GET /api/library/search (global) ========== */
    public function search()
    {
        $this->requireApiKey();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $off  = ($page - 1) * $per;
        $q    = trim($_GET['search'] ?? '');

        $sql = "SELECT id, id_ies, titulo, autor, isbn, tipo_libro, portada, libro, anio
              FROM biblioteca_libros
             WHERE 1=1";

        if ($q !== '') {
            $sql .= " AND (titulo LIKE :q1 OR autor LIKE :q2 OR temas_relacionados LIKE :q3)";
        }

        $sql .= " ORDER BY id DESC LIMIT " . (int)$off . ", " . (int)$per;

        $st = $this->db->prepare($sql);

        if ($q !== '') {
            $like = "%$q%";
            $st->bindValue(':q1', $like, \PDO::PARAM_STR);
            $st->bindValue(':q2', $like, \PDO::PARAM_STR);
            $st->bindValue(':q3', $like, \PDO::PARAM_STR);
        }

        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        $data = array_map([$this, 'mapRow'], $rows);
        return $this->json(['data' => $data, 'page' => $page, 'per_page' => $per], 200);
    }

    /* ========== GET /api/library/show/{id} ========== */
    public function show($id)
    {
        $this->requireApiKey();
        $id = (int)$id;
        $st = $this->db->prepare("SELECT * FROM biblioteca_libros WHERE id=?");
        $st->execute([$id]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$r) $this->error('Not found', 404, 'NOT_FOUND');

        $cfg = $this->cfg();
        $r['owner_ies']   = (int)$r['id_ies'];
        $r['portada_url'] = $r['portada'] ? ($cfg['library']['covers_base_url'] . '/' . $r['portada']) : null;
        $r['archivo_url'] = $cfg['library']['files_base_url'] . '/' . $r['libro'];
        $this->json($r, 200);
    }

    /* ========== GET /api/library/adopted ========== */
    /* ========== GET /api/library/adopted ========== */
    public function adopted()
    {
        $this->requireApiKey();

        // --- paginación ---
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $off  = ($page - 1) * $per;

        // --- filtros académicos ---
        $p = [
            'id_programa_estudio' => isset($_GET['id_programa_estudio']) ? (int)$_GET['id_programa_estudio'] : null,
            'id_plan'             => isset($_GET['id_plan']) ? (int)$_GET['id_plan'] : null,
            'id_modulo_formativo' => isset($_GET['id_modulo_formativo']) ? (int)$_GET['id_modulo_formativo'] : null,
            'id_semestre'         => isset($_GET['id_semestre']) ? (int)$_GET['id_semestre'] : null,
            'id_unidad_didactica' => isset($_GET['id_unidad_didactica']) ? (int)$_GET['id_unidad_didactica'] : null,
        ];

        // --- búsqueda libre (titulo/autor/temas_relacionados) ---
        $search = trim($_GET['search'] ?? '');
        $like   = ($search !== '') ? '%' . $search . '%' : null;

        // WHERE dinámico (solo placeholders posicionales para evitar HY093)
        $where = ["v.id_ies = ?"];
        $bind  = [$this->tenantId];

        foreach ($p as $col => $val) {
            if ($val) {
                $where[] = "v.$col = ?";
                $bind[] = $val;
            }
        }
        if ($like !== null) {
            $where[] = "(bl.titulo LIKE ? OR bl.autor LIKE ? OR bl.temas_relacionados LIKE ?)";
            $bind[] = $like;
            $bind[] = $like;
            $bind[] = $like;
        }
        $W = implode(' AND ', $where);

        // --- total (para paginación) ---
        $sqlCount = "
        SELECT COUNT(*) AS t
          FROM biblioteca_vinculos v
          JOIN biblioteca_libros bl ON bl.id = v.id_libro
         WHERE $W
    ";
        $st = $this->db->prepare($sqlCount);
        // bind posicional
        foreach ($bind as $i => $val) {
            $st->bindValue($i + 1, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $st->execute();
        $total = (int)($st->fetchColumn() ?: 0);

        // --- datos de la página ---
        // Nota: interpolamos off/per ya casteados a int para evitar problemas con LIMIT en PDO.
        $sql = "
      SELECT 
        bl.id, bl.id_ies, bl.titulo, bl.autor, bl.isbn, bl.tipo_libro, bl.portada, bl.libro, bl.anio,
        v.id_programa_estudio, v.id_plan, v.id_modulo_formativo, v.id_semestre, v.id_unidad_didactica
      FROM biblioteca_vinculos v
      JOIN biblioteca_libros bl ON bl.id = v.id_libro
      WHERE $W
      ORDER BY bl.id DESC
      LIMIT " . (int)$off . ", " . (int)$per;

        $st = $this->db->prepare($sql);
        foreach ($bind as $i => $val) {
            $st->bindValue($i + 1, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        // mapear + adjuntar cadena académica
        $data = array_map(function (array $r): array {
            $row = $this->mapRow($r);   // usa covers_base_url / files_base_url del cfg
            $row['vinculo'] = [
                'id_programa_estudio' => (int)($r['id_programa_estudio'] ?? 0),
                'id_plan'             => (int)($r['id_plan'] ?? 0),
                'id_modulo_formativo' => (int)($r['id_modulo_formativo'] ?? 0),
                'id_semestre'         => (int)($r['id_semestre'] ?? 0),
                'id_unidad_didactica' => (int)($r['id_unidad_didactica'] ?? 0),
            ];
            return $row;
        }, $rows);

        return $this->json([
            'data' => $data,
            'pagination' => [
                'page'     => $page,
                'per_page' => $per,
                'total'    => $total
            ]
        ], 200);
    }



    public function update($id)
    {
        $this->requireApiKey();           // protege con API key
        $this->maybeReplayIdem();         // soporta idempotencia (si lo usas)

        $id = (int)$id;

        // 1) Verifica que el libro exista y sea del IES dueño
        $st = $this->db->prepare("SELECT * FROM biblioteca_libros WHERE id=? AND id_ies=?");
        $st->execute([$id, $this->tenantId]);
        $book = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$book) {
            $this->error('No encontrado o no eres el propietario', 404, 'NOT_FOUND');
        }

        // 2) Rutas de almacenamiento
        $publicRoot = realpath(__DIR__ . '/../../../public');
        if ($publicRoot === false) {
            $this->error('No se ubicó /public', 500, 'PATH_ERROR');
        }
        $booksDir  = $publicRoot . DIRECTORY_SEPARATOR . 'books';
        $coversDir = $publicRoot . DIRECTORY_SEPARATOR . 'covers';
        if (!is_dir($booksDir))  @mkdir($booksDir, 0755, true);
        if (!is_dir($coversDir)) @mkdir($coversDir, 0755, true);
        if (!is_writable($booksDir) || !is_writable($coversDir)) {
            $this->error('Directorios no escribibles', 500, 'DIR_NOT_WRITABLE');
        }

        // 3) Detecta JSON vs multipart
        $isMultipart = !empty($_FILES) || (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false);
        $in = $isMultipart ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);

        // 4) Campos editables (solo actualiza los que llegan)
        $fields = [
            'titulo',
            'autor',
            'editorial',
            'edicion',
            'tomo',
            'tipo_libro',
            'isbn',
            'paginas',
            'anio',
            'temas_relacionados',
            'tags'
        ];
        $set = [];
        $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $in)) {
                $set[] = "$f = :$f";
                $params[":$f"] = ($f === 'paginas' || $f === 'anio') ? (int)$in[$f] : trim((string)$in[$f]);
            }
        }

        // 5) Validación/guardado de archivos opcionales
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $sanitize = function (string $name, string $fallbackExt) {
            $name = basename($name);
            $name = preg_replace('~[^A-Za-z0-9._-]+~', '-', $name) ?? 'file';
            if (!preg_match('/\.[A-Za-z0-9]{1,5}$/', $name)) $name .= '.' . $fallbackExt;
            $pref = substr(uniqid('', true), 0, 8) . '-';
            return substr($pref . $name, 0, 100);
        };

        // Portada
        if ($isMultipart && isset($_FILES['portada']) && $_FILES['portada']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['portada'];
            if ($f['error'] !== UPLOAD_ERR_OK) $this->error('Error subiendo portada', 422, 'UPLOAD_ERROR');
            if ($f['size'] > 5 * 1024 * 1024) $this->error('Portada supera 5MB', 413, 'FILE_TOO_LARGE');
            $mime = $finfo->file($f['tmp_name']) ?: '';
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowed, true)) $this->error("Tipo de portada no permitido ($mime)", 415, 'BAD_MIME');
            $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
            $coverFilename = $sanitize($f['name'] ?: "portada.$ext", $ext);
            if (!@move_uploaded_file($f['tmp_name'], $coversDir . DIRECTORY_SEPARATOR . $coverFilename)) {
                $this->error('No se pudo guardar la portada', 500, 'MOVE_FAIL');
            }
            // elimina portada anterior si existía
            if (!empty($book['portada'])) {
                @unlink($coversDir . DIRECTORY_SEPARATOR . $book['portada']);
            }
            $set[] = "portada = :portada";
            $params[':portada'] = $coverFilename;
        }

        // Libro
        if ($isMultipart && isset($_FILES['libro']) && $_FILES['libro']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['libro'];
            if ($f['error'] !== UPLOAD_ERR_OK) $this->error('Error subiendo libro', 422, 'UPLOAD_ERROR');
            if ($f['size'] > 25 * 1024 * 1024) $this->error('Libro supera 25MB', 413, 'FILE_TOO_LARGE');
            if (!is_uploaded_file($f['tmp_name'])) $this->error('Upload inválido', 422, 'NOT_UPLOADED');
            $mime = $finfo->file($f['tmp_name']) ?: '';
            $allowed = ['application/pdf', 'application/epub+zip'];
            if (!in_array($mime, $allowed, true)) $this->error("Tipo de archivo no permitido ($mime)", 415, 'BAD_MIME');
            $ext = $mime === 'application/pdf' ? 'pdf' : 'epub';
            $bookFilename = $sanitize($f['name'] ?: "libro.$ext", $ext);
            if (!@move_uploaded_file($f['tmp_name'], $booksDir . DIRECTORY_SEPARATOR . $bookFilename)) {
                $this->error('No se pudo guardar el libro', 500, 'MOVE_FAIL');
            }
            // elimina archivo anterior si existía
            if (!empty($book['libro'])) {
                @unlink($booksDir . DIRECTORY_SEPARATOR . $book['libro']);
            }
            $set[] = "libro = :libro";
            $params[':libro'] = $bookFilename;
        }

        if (empty($set)) {
            return $this->json(['ok' => true, 'message' => 'Sin cambios'], 200);
        }

        // 6) Ejecuta UPDATE
        $sql = "UPDATE biblioteca_libros SET " . implode(', ', $set) . " WHERE id = :id AND id_ies = :id_ies";
        $params[':id'] = $id;
        $params[':id_ies'] = $this->tenantId;
        $up = $this->db->prepare($sql);
        $up->execute($params);

        // 7) Respuesta con datos actualizados
        $cfg = $this->cfg();
        $st = $this->db->prepare("SELECT * FROM biblioteca_libros WHERE id=?");
        $st->execute([$id]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);

        $payload = [
            'ok' => true,
            'data' => [
                'id'          => (int)$r['id'],
                'owner_ies'   => (int)$r['id_ies'],
                'titulo'      => $r['titulo'],
                'autor'       => $r['autor'],
                'isbn'        => $r['isbn'],
                'tipo_libro'  => $r['tipo_libro'],
                'anio'        => $r['anio'],
                'portada_url' => $r['portada'] ? ($cfg['library']['covers_base_url'] . '/' . $r['portada']) : null,
                'archivo_url' => $cfg['library']['files_base_url'] . '/' . $r['libro'],
            ]
        ];
        return $this->respondIdem($payload, 200);
    }
}
