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
            'firstname'     => $nombres ?? '-',
            'lastname'      => $apellidos ?? '-',
            'email'         => $email,
            'idnumber'      => (string)$sigiId ?? '0', // <--- AQUÍ ESTÁ TU VÍNCULO SAGRADO
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
                            'id_sigi'   => $usuarios[$key]['idnumber'],
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
                'tipo' => 'MASIVA'
            ];
        }

        // -------------------------------------------------------
        // PLAN B: UNO POR UNO (Lento pero seguro + Fallback)
        // -------------------------------------------------------
        $creadosMoodle = 0;
        $erroresMoodle = [];

        foreach ($usuarios as $singleUser) {
            $sigiId = $singleUser['idnumber'] ?? '0';
            $dni = $singleUser['username'] ?? '-';
            $email = $singleUser['email'] ?? '-';
            $nombres = $singleUser['firstname'] ?? '-';
            $apellidos = $singleUser['lastname'] ?? '-';
            $passwordPlano = $singleUser['password'] ?? '-';

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
            'data' => $usuarios_vinculados, // <--- AQUÍ VAN LOS IDS
            'tipo' => 'INDIVIDUAL'
        ];
    }



    // ======================== Crea o busca una categoría en Moodle ========================
    public function getOrCreateCategory($nombre, $idnumber, $parentId = 0, $MOODLE_URL, $MOODLE_TOKEN)
    {
        // 1) Buscar solo la categoría exacta (sin subcategorías)
        $cat = $this->call('core_course_get_categories', [
            'criteria' => [
                ['key' => 'idnumber', 'value' => (string)$idnumber]
            ],
            'addsubcategories' => 0
        ], $MOODLE_URL, $MOODLE_TOKEN);

        // 2) Validar exactitud por seguridad
        if (!empty($cat)) {
            foreach ($cat as $c) {
                if (($c['idnumber'] ?? '') === (string)$idnumber) {
                    return (int)$c['id'];
                }
            }
        }

        // 3) Crear si no existe
        $newCat = [
            'name' => $nombre,
            'parent' => (int)$parentId,
            'idnumber' => (string)$idnumber,
            'descriptionformat' => 1
        ];

        $resp = $this->call('core_course_create_categories', ['categories' => [$newCat]], $MOODLE_URL, $MOODLE_TOKEN);

        if (isset($resp[0]['id'])) {
            return (int)$resp[0]['id'];
        }

        throw new \Exception("No se pudo crear la categoría '$nombre'. Error Moodle: " . json_encode($resp));
    }



    /**
     * Crea o actualiza un curso en Moodle.
     * @param array $data [fullname, shortname, categoryId, idnumber, summary]
     */
    public function createCourse($data, $MOODLE_URL, $MOODLE_TOKEN)
    {
        $courses = $this->call('core_course_get_courses_by_field', [
            'field' => 'idnumber',
            'value' => (string)$data['idnumber']
        ], $MOODLE_URL, $MOODLE_TOKEN);

        $coursePayload = [
            'fullname'   => $data['fullname'] ?? '-',
            'shortname'  => $data['shortname'] ?? '-',
            'categoryid' => (int)$data['categoryId'], // OK en tu diseño
            'idnumber'   => (string)$data['idnumber'],
            'summary'    => $data['summary'] ?? '-',
            'format'     => 'topics',
            'visible'    => 1,
            'courseformatoptions' => [
                ['name' => 'numsections', 'value' => (int)($data['numsections'] ?? 4)]
            ],
        ];


        // CASO A: ACTUALIZAR
        if (!empty($courses) && isset($courses['courses'][0]['id'])) {
            $moodleCourseId = (int)$courses['courses'][0]['id'];
            $coursePayload['id'] = $moodleCourseId;

            $respUp = $this->call('core_course_update_courses', ['courses' => [$coursePayload]], $MOODLE_URL, $MOODLE_TOKEN);

            // Solo es error si Moodle devuelve exception/errorcode
            if (is_array($respUp) && (isset($respUp['exception']) || isset($respUp['errorcode']))) {
                throw new \Exception("Error Moodle Update Course: " . json_encode($respUp));
            }

            // Si quieres, registra warnings como “nota”, no como error
            // if (!empty($respUp['warnings'])) { ... }

            return $moodleCourseId;
        }

        // CASO B: CREAR
        $resp = $this->call('core_course_create_courses', ['courses' => [$coursePayload]], $MOODLE_URL, $MOODLE_TOKEN);

        if (isset($resp[0]['id'])) {
            return (int)$resp[0]['id'];
        }

        // Si Moodle devuelve un objeto de error
        if (is_array($resp) && (isset($resp['exception']) || isset($resp['errorcode']))) {
            throw new \Exception("Error Moodle Create Course: " . json_encode($resp));
        }

        return false;
    }

    public function setSectionNames($courseId, array $sections, $MOODLE_URL, $MOODLE_TOKEN)
    {
        return $this->call('local_sigiws_update_sections', [
            'courseid' => (int)$courseId,
            'sections' => $sections
        ], $MOODLE_URL, $MOODLE_TOKEN);
    }

    //======================== matricular usuario =======================
    public function enrolUserToCourse(int $courseId, int $userId, int $roleId, string $MOODLE_URL, string $MOODLE_TOKEN): bool
    {
        $resp = $this->call('enrol_manual_enrol_users', [
            'enrolments' => [[
                'roleid'   => $roleId,
                'userid'   => $userId,
                'courseid' => $courseId,
            ]]
        ], $MOODLE_URL, $MOODLE_TOKEN);

        // Normalmente no retorna nada “útil” si todo OK (o warnings)
        if (is_array($resp) && (isset($resp['exception']) || isset($resp['errorcode']))) {
            return false;
        }
        return true;
    }
}
