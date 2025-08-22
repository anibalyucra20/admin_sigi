<?php

namespace App\Controllers\Api;

require_once __DIR__ . '/BaseApiController.php';

class LibraryController extends BaseApiController
{
    /** GET /api/library/items (propios + adoptados del tenant) */
    public function items()
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $off  = ($page - 1) * $per;
        $q    = trim($_GET['search'] ?? '');
        $tipo = $_GET['tipo'] ?? null;

        $sql = "
          SELECT bl.id, bl.id_ies, bl.titulo, bl.autor, bl.isbn, bl.tipo_libro, bl.portada, bl.libro, bl.anio
          FROM biblioteca_libros bl
          WHERE bl.id_ies = :ies
          /**f1**/
          UNION
          SELECT bl.id, bl.id_ies, bl.titulo, bl.autor, bl.isbn, bl.tipo_libro, bl.portada, bl.libro, bl.anio
          FROM biblioteca_vinculos v
          INNER JOIN biblioteca_libros bl ON bl.id = v.id_libro
          WHERE v.id_ies = :ies
          /**f2**/
          ORDER BY id DESC
          LIMIT :off, :per
        ";
        $flt = ($q || $tipo)
            ? " AND (" .
            ($q   ? " (bl.titulo LIKE :q OR bl.autor LIKE :q) " : " 1 ") .
            ($q && $tipo ? " AND " : "") .
            ($tipo ? " bl.tipo_libro = :tipo " : " ") .
            ") "
            : "";
        $sql = str_replace('/**f1**/', $flt, $sql);
        $sql = str_replace('/**f2**/', $flt, $sql);

        $st = $this->db->prepare($sql);
        $st->bindValue(':ies', $this->tenantId, \PDO::PARAM_INT);
        if ($q)   $st->bindValue(':q', "%$q%");
        if ($tipo) $st->bindValue(':tipo', $tipo);
        $st->bindValue(':off', (int)$off, \PDO::PARAM_INT);
        $st->bindValue(':per', (int)$per, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();

        $cfg = file_exists(__DIR__ . '/../../../config/app.php') ? require __DIR__ . '/../../../config/app.php' : ['library' => ['covers_base_url' => BASE_URL . '/covers', 'files_base_url' => BASE_URL . '/books']];
        $c = rtrim($cfg['library']['covers_base_url'], '/');
        $f = rtrim($cfg['library']['files_base_url'], '/');

        $data = array_map(fn($r) => [
            'id'         => (int)$r['id'],
            'owner_ies'  => (int)$r['id_ies'],
            'titulo'     => $r['titulo'],
            'autor'      => $r['autor'],
            'isbn'       => $r['isbn'],
            'tipo_libro' => $r['tipo_libro'],
            'anio'       => $r['anio'],
            'portada_url' => $c . '/' . $r['portada'],
            'archivo_url' => $f . '/' . $r['libro'],
        ], $rows);

        return $this->json(['data' => $data, 'page' => $page, 'per_page' => $per]);
    }

    /** GET /api/library/search (global: todos los IES) */
    public function search()
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $off  = ($page - 1) * $per;

        $q     = trim($_GET['search'] ?? '');
        $tipo  = $_GET['tipo'] ?? null;
        $owner = isset($_GET['owner']) ? (int)$_GET['owner'] : null;

        $sql = "SELECT id, id_ies, titulo, autor, isbn, tipo_libro, portada, libro, anio
                FROM biblioteca_libros WHERE 1=1";
        $params = [];
        if ($q) {
            $sql .= " AND (titulo LIKE :q OR autor LIKE :q)";
            $params[':q'] = "%$q%";
        }
        if ($tipo) {
            $sql .= " AND tipo_libro = :tipo";
            $params[':tipo'] = $tipo;
        }
        if ($owner) {
            $sql .= " AND id_ies = :owner";
            $params[':owner'] = $owner;
        }
        $sql .= " ORDER BY id DESC LIMIT :off,:per";

        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':off', (int)$off, \PDO::PARAM_INT);
        $st->bindValue(':per', (int)$per, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();

        $cfg = file_exists(__DIR__ . '/../../../config/app.php') ? require __DIR__ . '/../../../config/app.php' : ['library' => ['covers_base_url' => BASE_URL . '/covers', 'files_base_url' => BASE_URL . '/books']];
        $c = rtrim($cfg['library']['covers_base_url'], '/');
        $f = rtrim($cfg['library']['files_base_url'], '/');

        $data = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'owner_ies' => (int)$r['id_ies'],
            'titulo' => $r['titulo'],
            'autor' => $r['autor'],
            'isbn' => $r['isbn'],
            'tipo_libro' => $r['tipo_libro'],
            'anio' => $r['anio'],
            'portada_url' => $c . '/' . $r['portada'],
            'archivo_url' => $f . '/' . $r['libro'],
        ], $rows);

        return $this->json(['data' => $data, 'page' => $page, 'per_page' => $per]);
    }

    /** GET /api/library/show/{id} (detalle) */
    public function show($id)
    {
        $id = (int)$id;
        $st = $this->db->prepare("SELECT * FROM biblioteca_libros WHERE id=?");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) return $this->json(['error' => 'Not found'], 404);

        $cfg = file_exists(__DIR__ . '/../../../config/app.php') ? require __DIR__ . '/../../../config/app.php' : ['library' => ['covers_base_url' => BASE_URL . '/covers', 'files_base_url' => BASE_URL . '/books']];
        $r['owner_ies']  = (int)$r['id_ies'];
        $r['portada_url'] = rtrim($cfg['library']['covers_base_url'], '/') . '/' . $r['portada'];
        $r['archivo_url'] = rtrim($cfg['library']['files_base_url'], '/') . '/' . $r['libro'];
        return $this->json($r);
    }

    /** POST /api/library/upload (crear libro propio del tenant) */
    public function upload()
    {
        $data = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?? []);
        $titulo = trim($data['titulo'] ?? '');
        $tipo   = trim($data['tipo_libro'] ?? '');
        $libro  = trim($data['libro'] ?? '');

        if ($titulo === '' || $tipo === '' || $libro === '') {
            return $this->json(['error' => 'Faltan campos (titulo, tipo_libro, libro)'], 422);
        }

        $sql = "INSERT INTO biblioteca_libros
                (titulo, autor, editorial, edicion, tomo, tipo_libro, isbn, paginas, anio,
                 temas_relacionados, tags, portada, libro, id_ies, fecha_registro)
                VALUES (:titulo,:autor,:editorial,:edicion,:tomo,:tipo_libro,:isbn,:paginas,:anio,
                        :temas,:tags,:portada,:libro,:id_ies,NOW())";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':titulo' => $titulo,
            ':autor' => $data['autor'] ?? '',
            ':editorial' => $data['editorial'] ?? '',
            ':edicion' => $data['edicion'] ?? '',
            ':tomo' => $data['tomo'] ?? '',
            ':tipo_libro' => $tipo,
            ':isbn' => $data['isbn'] ?? '',
            ':paginas' => (int)($data['paginas'] ?? 0),
            ':anio'   => (int)($data['anio'] ?? 0),
            ':temas'  => $data['temas_relacionados'] ?? '',
            ':tags'   => $data['tags'] ?? '',
            ':portada' => $data['portada'] ?? '',
            ':libro'  => $libro,
            ':id_ies' => $this->tenantId,
        ]);
        $id = (int)$this->db->lastInsertId();
        return $this->json(['id' => $id, 'owner_ies' => $this->tenantId], 201);
    }

    /** POST /api/library/adopt/{libro_id} (crear vínculo en el Maestro) */
    public function adopt($libroId)
    {
        $libroId = (int)$libroId;

        // Si ya es propio, no crees vínculo
        $st = $this->db->prepare("SELECT 1 FROM biblioteca_libros WHERE id=? AND id_ies=?");
        $st->execute([$libroId, $this->tenantId]);
        if ($st->fetch()) return $this->json(['ok' => true, 'message' => 'Ya es propio'], 200);

        // Validar libro existente
        $st = $this->db->prepare("SELECT 1 FROM biblioteca_libros WHERE id=?");
        $st->execute([$libroId]);
        if (!$st->fetch()) return $this->json(['error' => 'Libro no existe'], 404);

        // Crear vínculo (idempotente)
        $sql = "INSERT IGNORE INTO biblioteca_vinculos (id_ies, id_libro) VALUES (?,?)";
        $this->db->prepare($sql)->execute([$this->tenantId, $libroId]);

        return $this->json(['ok' => true, 'adopted_libro_id' => $libroId], 201);
    }

    /** POST /api/library/unadopt/{libro_id} (eliminar vínculo) */
    public function unadopt($libroId)
    {
        $libroId = (int)$libroId;
        $st = $this->db->prepare("DELETE FROM biblioteca_vinculos WHERE id_ies=? AND id_libro=?");
        $st->execute([$this->tenantId, $libroId]);
        return $this->json(['ok' => true, 'unadopted_libro_id' => $libroId], 200);
    }

    /** GET /api/library/adopted (solo adoptados del tenant) */
    public function adopted()
    {
        $st = $this->db->prepare("
            SELECT bl.id, bl.id_ies, bl.titulo, bl.autor, bl.isbn, bl.tipo_libro, bl.portada, bl.libro, bl.anio
            FROM biblioteca_vinculos v
            INNER JOIN biblioteca_libros bl ON bl.id = v.id_libro
            WHERE v.id_ies = ?
            ORDER BY bl.id DESC
            LIMIT 1000
        ");
        $st->execute([$this->tenantId]);
        $rows = $st->fetchAll();

        $cfg = file_exists(__DIR__ . '/../../../config/app.php') ? require __DIR__ . '/../../../config/app.php' : ['library' => ['covers_base_url' => BASE_URL . '/covers', 'files_base_url' => BASE_URL . '/books']];
        $c = rtrim($cfg['library']['covers_base_url'], '/');
        $f = rtrim($cfg['library']['files_base_url'], '/');

        $data = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'owner_ies' => (int)$r['id_ies'],
            'titulo' => $r['titulo'],
            'autor' => $r['autor'],
            'isbn' => $r['isbn'],
            'tipo_libro' => $r['tipo_libro'],
            'anio' => $r['anio'],
            'portada_url' => $c . '/' . $r['portada'],
            'archivo_url' => $f . '/' . $r['libro'],
        ], $rows);

        return $this->json(['data' => $data]);
    }
}
