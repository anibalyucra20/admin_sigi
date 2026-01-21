<?php

namespace Core;

class Auth
{
    public static function start()
    {
        if (defined('IS_API') && IS_API) {
            // En API no usamos sesión/cookies
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    public static function login(array $user)
    {
        self::start();
        $_SESSION['admin_sigi_session_id']   = $user['id_session'];
        $_SESSION['admin_sigi_user_id']   = $user['id'];
        $_SESSION['admin_sigi_user_name'] = $user['apellidos_nombres'];
        $_SESSION['admin_sigi_token'] = $user['token'];
        // Regenerar ID para evitar session fixation
        session_regenerate_id(true);
    }
    public static function crearPassword(int $longitud = 8)
    {
        $parteAleatoria = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $longitud);
        $password = 'Sigi.' . $parteAleatoria . '!';
        return $password;
    }
    public static function user(): ?array
    {
        self::start();
        return (isset($_SESSION['admin_sigi_user_id']) && $_SESSION['admin_sigi_user_id']) ? $_SESSION : null;
    }

    public static function logout()
    {
        self::start();

        if (!empty($_SESSION['admin_sigi_session_id'])) {
            $db = (new Model())->getDB();
            $stmt = $db->prepare("UPDATE sesiones SET estado = 0 WHERE id = ?");
            $stmt->execute([$_SESSION['admin_sigi_session_id']]);
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, '/');
        }
        session_destroy();
    }

    public static function validarSesion()
    {
        if (defined('IS_API') && IS_API) return true;
        self::start();
        if (!empty($_SESSION['admin_sigi_session_id'])) {
            $session_id = $_SESSION['admin_sigi_session_id'] ?? null;
            $user_id = $_SESSION['admin_sigi_user_id'] ?? null;

            if (!$session_id || !$user_id) {
                self::logout();
                return false;
            }
            $db = (new Model())->getDB();
            $stmt = $db->prepare("SELECT * FROM sesiones 
                           WHERE id = ? AND id_usuario = ? AND estado = 1");
            $stmt->execute([$session_id, $user_id]);
            $sesion = $stmt->fetch();

            $tiempoSession = 240 * 60; // 240 minutos de sesion

            if (!$sesion) {
                self::logout(); // sesión expirada o cerrada desde admin
                return false;
            }
            // Verificar inactividad (ej. 30 minutos)
            $ultima = strtotime($sesion['fecha_hora_fin']);
            if ((time() - $ultima) > $tiempoSession) {
                // Expira sesión
                $db->prepare("UPDATE sesiones SET estado = 0 WHERE id = ?")
                    ->execute([$session_id]);
                self::logout();
                return false;
            }
            // Actualizar última actividad
            $db->prepare("UPDATE sesiones SET fecha_hora_fin = NOW() WHERE id = ?")
                ->execute([$session_id]);
            return true;
        }
        return false;
    }
}
