<?php
namespace App\Models\Library;

use Core\Model;
use PDO;

class Libro extends Model
{
    public function crear(array $d): int {
        $sql = "INSERT INTO biblioteca_libros
        (titulo,autor,editorial,edicion,tomo,tipo_libro,isbn,paginas,anio,temas_relacionados,tags,portada,libro,id_ies,fecha_registro)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
        $st = self::getDB()->prepare($sql);
        $st->execute([
            $d['titulo'],$d['autor'],$d['editorial'],$d['edicion'],$d['tomo'],$d['tipo_libro'],
            $d['isbn'],$d['paginas'],$d['anio'],$d['temas_relacionados'],$d['tags'],
            $d['portada'],$d['libro'],$d['id_ies']
        ]);
        return (int)self::getDB()->lastInsertId();
    }

    public function listar(array $f=[], int $limit=50, int $offset=0): array {
        $w=[];$p=[];
        if (!empty($f['q'])) { $w[]="(titulo LIKE ? OR autor LIKE ? OR tags LIKE ?)"; $q='%'.$f['q'].'%'; $p[]=$q;$p[]=$q;$p[]=$q; }
        if (!empty($f['id_ies'])) { $w[]="id_ies=?"; $p[]=(int)$f['id_ies']; }
        $where = $w ? 'WHERE '.implode(' AND ',$w) : '';
        $sql = "SELECT * FROM biblioteca_libros $where ORDER BY fecha_registro DESC LIMIT ? OFFSET ?";
        $st  = self::getDB()->prepare($sql);
        $i=1; foreach ($p as $v) { $st->bindValue($i++, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
        $st->bindValue($i++, $limit, PDO::PARAM_INT);
        $st->bindValue($i,   $offset, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
