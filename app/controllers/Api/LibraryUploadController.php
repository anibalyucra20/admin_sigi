<?php
namespace App\Controllers\Api;

require_once __DIR__ . '/BaseApiController.php';

class LibraryUploadController extends BaseApiController
{
    /**
     * POST /api/library/upload
     * Crea el registro del libro en el Maestro como PROPIO del IES (tenant).
     * Guarda solo metadatos; portada/libro son nombres de archivo (la ruta la arma config).
     */
    public function upload(){
        $data = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?? []);

        // Campos mínimos de ejemplo (ajusta a tu gusto)
        $titulo = trim($data['titulo'] ?? '');
        $autor  = trim($data['autor'] ?? '');
        $tipo   = trim($data['tipo_libro'] ?? '');
        $isbn   = trim($data['isbn'] ?? '');
        $portada = trim($data['portada'] ?? ''); // SOLO nombre/slug de archivo
        $libro   = trim($data['libro'] ?? '');   // SOLO nombre/slug de archivo

        if ($titulo==='' || $tipo==='' || $libro==='') {
            return $this->json(['error'=>'Faltan campos obligatorios (titulo, tipo_libro, libro)'], 422);
        }

        $sql = "INSERT INTO biblioteca_libros
                (titulo, autor, editorial, edicion, tomo, tipo_libro, isbn, paginas, anio,
                 temas_relacionados, tags, portada, libro, id_ies, fecha_registro)
                VALUES (:titulo,:autor,:editorial,:edicion,:tomo,:tipo_libro,:isbn,:paginas,:anio,
                        :temas,:tags,:portada,:libro,:id_ies,NOW())";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':titulo' => $titulo,
            ':autor'  => $autor,
            ':editorial' => $data['editorial'] ?? '',
            ':edicion'   => $data['edicion'] ?? '',
            ':tomo'      => $data['tomo'] ?? '',
            ':tipo_libro'=> $tipo,
            ':isbn'      => $isbn,
            ':paginas'   => (int)($data['paginas'] ?? 0),
            ':anio'      => (int)($data['anio'] ?? 0),
            ':temas'     => $data['temas_relacionados'] ?? '',
            ':tags'      => $data['tags'] ?? '',
            ':portada'   => $portada,
            ':libro'     => $libro,
            ':id_ies'    => $this->tenantId,
        ]);
        $id = (int)$this->db->lastInsertId();

        // Devuelve datos para que el SIGI del IES cree su mini-registro local
        return $this->json([
            'id' => $id,
            'titulo' => $titulo,
            'propietario_ies' => $this->tenantId,
        ], 201);
    }
}
