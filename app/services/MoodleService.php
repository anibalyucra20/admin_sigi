<?php

namespace App\Services;

use App\Models\Api\ConsultaModel;

class MoodleService
{
    private $objConsulta;

    public function __construct()
    {
        $this->objConsulta = new ConsultaModel();
    }
    /**
     * Sincroniza (Crea o Actualiza) un usuario de SIGI hacia Moodle.
     * Utiliza el ID de SIGI como 'idnumber' en Moodle para mantener el vínculo.
     * * @param int $sigiId El ID primario de la tabla sigi_usuarios
     * @param string $passwordPlano La contraseña SIN hash (solo si se está creando/cambiando)
     */
    public function syncUser($MOODLE_URL, $MOODLE_TOKEN, $id_ies, $sigiId, $dni, $email, $nombres, $apellidos, $passwordPlano = null)
    {
        // 1. Buscamos en Moodle por tu ID de SIGI (campo 'idnumber')
        // Esto es mucho más seguro que buscar por DNI
        $respuesta = [];
        $moodleUser = $this->call('core_user_get_users_by_field', [
            'field' => 'idnumber',
            'values' => [(string)$sigiId]
        ], $MOODLE_URL, $MOODLE_TOKEN);

        $userPayload = [
            'username'      => $dni,
            'firstname'     => $nombres,
            'lastname'      => $apellidos,
            'email'         => $email,
            'idnumber'      => (string)$sigiId, // <--- AQUÍ ESTÁ TU VÍNCULO SAGRADO
            'auth'          => 'manual',
        ];


        // Si enviamos contraseña (creación o cambio de clave)
        if ($passwordPlano) {
            $userPayload['password'] = $passwordPlano;
            $userPayload['preferences'] = [['type' => 'auth_forcepasswordchange', 'value' => 0]];
        }

        // CASO A: ACTUALIZAR (Si ya existe)
        if (!empty($moodleUser) && !isset($moodleUser['exception'])) {
            $moodleInternalId = $moodleUser[0]['id'];

            // Para actualizar, Moodle exige el ID interno
            $userPayload['id'] = $moodleInternalId;

            // Llamamos a la función de UPDATE
            $this->call('core_user_update_users', ['users' => [$userPayload]], $MOODLE_URL, $MOODLE_TOKEN);
            $respuesta['message_success'] .= '<br>Usuario actualizado en Moodle.';
            $respuesta['id'] = $moodleInternalId;
            return $respuesta;
        }
        // Generar contraseña aleatoria en caso que no enviaron para actualizar y no enviaron contraseña
        if ($passwordPlano == null) {
            $passwordPlano = \Core\Auth::crearPassword(8);
            $userPayload['password'] = $passwordPlano;
        }
        // CASO B: CREAR (Si no existe)
        if ($passwordPlano) {
            // Solo creamos si tenemos contraseña. Si no, fallará.
            $resp = $this->call('core_user_create_users', ['users' => [$userPayload]], $MOODLE_URL, $MOODLE_TOKEN);
            $respuesta['message_success'] .= '<br>Usuario creado en Moodle.';
            $respuesta['id'] = isset($resp[0]['id']) ? $resp[0]['id'] : false;
            return $respuesta;
        }
        $respuesta['message_error'] .= '<br>Usuario no creado en Moodle.';
        $respuesta['id'] = false;
        return $respuesta;
    }

    public function enroll($MOODLE_URL, $MOODLE_TOKEN, $userId, $courseId, $roleId)
    {
        $resp = $this->call('enrol_manual_enrol_users', ['enrolments' => [[
            'roleid' => $roleId,
            'userid' => $userId,
            'courseid' => $courseId
        ]]], $MOODLE_URL, $MOODLE_TOKEN);
        return empty($resp);
    }

    /* Utilitario cURL Privado */
    private function call($function, $params, $MOODLE_URL, $MOODLE_TOKEN)
    {
        $url = $MOODLE_URL . '/webservice/rest/server.php' .
            '?wstoken=' . $MOODLE_TOKEN .
            '&wsfunction=' . $function .
            '&moodlewsrestformat=json';


        // 2. Iniciar cURL con manejo de errores
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);

        // IMPORTANTE: http_build_query maneja correctamente los índices para arrays anidados
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

        // Si estás en localhost sin SSL válido, descomenta la siguiente línea temporalmente:
        // ... configuración previa ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // 👇 AGREGA ESTAS LÍNEAS PARA WAMP/LOCAL 👇
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    /**
     * Genera la URL de auto-login hacia Moodle.
     * @param int $sigiUserId El ID del usuario en SIGI (que es el idnumber en Moodle)
     * @return string URL completa para redirigir
     */
    public function getAutoLoginUrl($sigiUserId, $MOODLE_URL, $MOODLE_SSO_KEY, $hacia = null, $id = null)
    {
        if (!isset($MOODLE_SSO_KEY)) return '#';
        $datos = [
            'uid'  => $sigiUserId,
            'time' => time()
        ];
        if ($hacia) {
            $datos['hacia'] = $hacia;
        }
        if ($id) {
            $datos['id'] = $id;
        }
        // 1. Datos a encriptar: ID + Timestamp (para que el link caduque en 60 seg)
        $data = json_encode($datos);

        // 2. Encriptación AES-256-CBC
        $method = "AES-256-CBC";
        $key = hash('sha256', $MOODLE_SSO_KEY);
        $iv = substr(hash('sha256', 'iv_secret'), 0, 16); // Vector de inicialización fijo o dinámico

        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);

        // 3. Generar URL segura (urlencode es vital)
        $token = urlencode(base64_encode($encrypted));

        // Asumimos que crearemos una carpeta "local/sigi" en moodle
        return $MOODLE_URL . "/local/sigi/sso.php?token=" . $token;
    }
    // Método principal llamado por tu controlador
    public function registrarLoteMasivo($MOODLE_URL, $MOODLE_TOKEN, $usuarios)
    {
        // Array para guardar la relación: DNI (o SigiID) -> MoodleID
        $usuarios_vinculados = [];

        // -------------------------------------------------------
        // PLAN A: Intentar inserción masiva (RÁPIDO)
        // -------------------------------------------------------
        $response = $this->call('core_user_create_users', ['users' => $usuarios], $MOODLE_URL, $MOODLE_TOKEN);

        // Si NO hay excepción, todo salió perfecto
        if (!isset($response['exception'])) {

            // CAPTURA DE IDS (PLAN A):
            // Moodle devuelve un array: [['id' => 101, 'username' => '7019...'], ...]
            if (is_array($response)) {
                foreach ($response as $key => $moodleUser) {
                    if (isset($moodleUser['id'])) {
                        $usuarios_vinculados[] = [
                            'id_sigi'   => $usuarios[$key]['id'], // Usamos DNI como clave de búsqueda
                            'moodle_id' => $moodleUser['id']
                        ];
                    }
                }
            }

            return [
                'ok' => true,
                'total_recibidos' => count($usuarios),
                'moodle_procesados' => count($usuarios),
                'errores_moodle_detalle' => [],
                'data' => $usuarios_vinculados, // <--- AQUÍ VAN LOS IDS
                'response' => $response // <--- AQUÍ VAN LOS IDS
            ];
        }

        // -------------------------------------------------------
        // PLAN B: UNO POR UNO (Lento pero seguro + Fallback)
        // -------------------------------------------------------
        $creadosMoodle = 0;
        $erroresMoodle = [];

        foreach ($usuarios as $singleUser) {
            $sigiId = $singleUser['idnumber'];
            $dni = $singleUser['username'];
            $email = $singleUser['email'];
            $nombres = $singleUser['firstname'];
            $apellidos = $singleUser['lastname'];
            $passwordPlano = $singleUser['password'];

            // Tu llamada syncUser intacta
            $indivResp = $this->syncUser($MOODLE_URL, $MOODLE_TOKEN, '', $sigiId, $dni, $email, $nombres, $apellidos, $passwordPlano);

            if ($indivResp['id'] !== false) {
                $creadosMoodle++;

                // CAPTURA DE IDS (PLAN B):
                $usuarios_vinculados[] = [
                    'id_sigi'   => $sigiId,
                    'moodle_id' => $indivResp['id']
                ];
            } else {
                $msg = $indivResp['message_error'] ?? 'Error desconocido';
                $erroresMoodle[] = "DNI {$dni}: " . $msg;
            }
        }

        return [
            'ok' => true,
            'total_recibidos' => count($usuarios),
            'moodle_procesados' => $creadosMoodle,
            'errores_moodle_detalle' => $erroresMoodle,
            'data' => $usuarios_vinculados // <--- AQUÍ VAN LOS IDS
        ];
    }
}
