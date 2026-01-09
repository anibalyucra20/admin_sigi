<?php

namespace Core;

use PDO;
use PDOException;

class Model
{
    /** @var ?PDO */
    protected static ?PDO $db = null;

    public function __construct()
    {
        // Gate solo para MVC (no para API)
        /*if (
            (!defined('IS_API') || !IS_API)
            && !Auth::user()
        ) {
            throw new \Exception('Acceso no autorizado');
        }*/

        if (self::$db === null) {
            self::connect();
        }
    }

    /** Siempre devuelve un PDO válido */
    public static function getDB(): PDO
    {
        if (self::$db === null) {
            self::connect();
        }
        return self::$db;
    }

    /** Conecta una sola vez (singleton) */
    protected static function connect(): void
    {
        $host    = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $port    = defined('DB_PORT') ? DB_PORT : 3306;
        $dbname  = defined('DB_NAME') ? DB_NAME : '';
        $user    = defined('DB_USER') ? DB_USER : '';
        $pass    = defined('DB_PASS') ? DB_PASS : '';
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        self::$db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /** En MVC pedimos sesión; en API no bloqueamos */
    public function __call($name, $arguments)
    {
        if (!defined('IS_API') || !IS_API) {
            if (!Auth::user()) {
                throw new \Exception('Acceso no autorizado. Debes iniciar sesión.');
            }
        }
        return \call_user_func_array([$this, $name], $arguments);
    }

    public static function log($id_usuario, $accion, $descripcion = '', $tabla = null, $id_registro = null)
    {
        $db = self::getDB();
        $sql = "INSERT INTO logs (id_usuario, accion, descripcion, tabla_afectada, id_registro, ip_usuario)
                VALUES (:id_usuario, :accion, :descripcion, :tabla_afectada, :id_registro, :ip_usuario)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':id_usuario'    => $id_usuario,
            ':accion'        => $accion,
            ':descripcion'   => $descripcion,
            ':tabla_afectada' => $tabla,
            ':id_registro'   => $id_registro,
            ':ip_usuario'    => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
