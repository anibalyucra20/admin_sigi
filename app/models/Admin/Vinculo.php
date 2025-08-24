<?php

namespace App\Models\Library;

use Core\Model;
use PDO;

class Vinculo extends Model
{
    // app/models/Library/Vinculo.php

    /**
     * Adopta un libro con cadena académica completa.
     * Devuelve: ['status' => inserted|duplicate|own|not_found]
     */
    public function adoptar(int $idIes, int $idLibro, array $cad): array
    {
        // Inserta SOLO si el libro existe y NO es propio. Idempotente por uq_vinc_det.
        $sql = "INSERT INTO biblioteca_vinculos
                (id_ies, id_libro, id_programa_estudio, id_plan, id_modulo_formativo, id_semestre, id_unidad_didactica, created_at)
                SELECT :ies, :lib, :prog, :plan, :mod, :sem, :ud, NOW()
                  FROM biblioteca_libros bl
                 WHERE bl.id = :lib AND bl.id_ies <> :ies
                ON DUPLICATE KEY UPDATE id = id";
        $st = self::getDB()->prepare($sql);
        $st->execute([
            ':ies'  => $idIes,
            ':lib'  => $idLibro,
            ':prog' => (int)$cad['id_programa_estudio'],
            ':plan' => (int)$cad['id_plan'],
            ':mod'  => (int)$cad['id_modulo_formativo'],
            ':sem'  => (int)$cad['id_semestre'],
            ':ud'   => (int)$cad['id_unidad_didactica'],
        ]);

        if ($st->rowCount() === 1) {
            return ['status' => 'inserted'];
        }

        // Si no insertó: o no existe, o es propio, o duplicado
        $q = self::getDB()->prepare("SELECT id_ies FROM biblioteca_libros WHERE id=?");
        $q->execute([$idLibro]);
        $owner = $q->fetchColumn();

        if ($owner === false)     return ['status' => 'not_found'];
        if ((int)$owner === $idIes) return ['status' => 'own'];
        return ['status' => 'duplicate'];
    }

    public function desvincular(int $idIes, int $idLibro, ?array $cad = null): int
    {
        if ($cad && isset($cad['id_programa_estudio'], $cad['id_plan'], $cad['id_modulo_formativo'], $cad['id_semestre'], $cad['id_unidad_didactica'])) {
            $sql = "DELETE FROM biblioteca_vinculos
                    WHERE id_ies=? AND id_libro=? AND id_programa_estudio=? AND id_plan=? AND id_modulo_formativo=? AND id_semestre=? AND id_unidad_didactica=?";
            $st = self::getDB()->prepare($sql);
            $st->execute([$idIes, $idLibro, (int)$cad['id_programa_estudio'], (int)$cad['id_plan'], (int)$cad['id_modulo_formativo'], (int)$cad['id_semestre'], (int)$cad['id_unidad_didactica']]);
            return $st->rowCount();
        }
        $st = self::getDB()->prepare("DELETE FROM biblioteca_vinculos WHERE id_ies=? AND id_libro=?");
        $st->execute([$idIes, $idLibro]);
        return $st->rowCount();
    }


    public function listarPorLibroIES(int $idLibro, int $idIes): array
    {
        $sql = "SELECT * FROM biblioteca_vinculos WHERE id_libro=? AND id_ies=? ORDER BY created_at DESC";
        $st  = self::getDB()->prepare($sql);
        $st->execute([$idLibro, $idIes]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
