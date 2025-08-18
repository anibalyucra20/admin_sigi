<?php

namespace App\Controllers\Api;

class LibraryController extends BaseApiController
{
    public function items()
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $off  = ($page - 1) * $per;

        $sql = "SELECT id, titulo, autor, isbn, tipo_libro, portada, libro, anio
                FROM biblioteca_libros WHERE id_ies=:id_ies";
        $params = [':id_ies' => $this->tenantId];
        if (!empty($_GET['search'])) {
            $sql .= " AND (titulo LIKE :q OR autor LIKE :q)";
            $params[':q'] = "%" . $_GET['search'] . "%";
        }
        if (!empty($_GET['tipo'])) {
            $sql .= " AND tipo_libro = :tipo";
            $params[':tipo'] = $_GET['tipo'];
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

        $cfg = $this->cfg();
        $c = rtrim($cfg['library']['covers_base_url'], '/');
        $f = rtrim($cfg['library']['files_base_url'], '/');

        $data = array_map(fn($r) => [
            'id' => $r['id'],
            'titulo' => $r['titulo'],
            'autor' => $r['autor'],
            'isbn' => $r['isbn'],
            'tipo_libro' => $r['tipo_libro'],
            'anio' => $r['anio'],
            'portada_url' => $c . '/' . $r['portada'],
            'archivo_url' => $f . '/' . $r['libro'],
        ], $rows);

        $this->json(['data' => $data, 'page' => $page, 'per_page' => $per]);
    }

    public function show($id)
    {
        $st = $this->db->prepare("SELECT * FROM biblioteca_libros WHERE id=? AND id_ies=?");
        $st->execute([(int)$id, $this->tenantId]);
        $r = $st->fetch();
        if (!$r) $this->json(['error' => 'Not found'], 404);

        $cfg = $this->cfg();
        $r['portada_url'] = rtrim($cfg['library']['covers_base_url'], '/') . '/' . $r['portada'];
        $r['archivo_url'] = rtrim($cfg['library']['files_base_url'], '/') . '/' . $r['libro'];
        $r['owner_ies'] = (int)$r['id_ies'];
        $this->json($r);
    }

    public function search()
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $off  = ($page - 1) * $per;

        $q    = trim($_GET['search'] ?? '');
        $tipo = $_GET['tipo'] ?? null;
        $owner = isset($_GET['owner']) ? (int)$_GET['owner'] : null; // opcional, filtra por IES

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

        $cfg = require __DIR__ . '/../../../config/app.php';
        $c = rtrim($cfg['library']['covers_base_url'], '/');
        $f = rtrim($cfg['library']['files_base_url'], '/');

        $data = array_map(fn($r) => [
            'id' => $r['id'],
            'owner_ies' => $r['id_ies'],          // 👈 importante para adopción local
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
}
