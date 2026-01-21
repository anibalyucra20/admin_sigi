<?php

namespace App\Controllers\Api;

require_once __DIR__ . '/BaseApiController.php';
require_once __DIR__ . '/../../services/MicrosoftService.php';
require_once __DIR__ . '/../../services/MoodleService.php';
require_once __DIR__ . '/../../models/Api/ConsultaModel.php';
require_once __DIR__ . '/../../models/Admin/Ies.php';

use App\Services\MicrosoftService;
use App\Services\MoodleService;
use App\Models\Api\ConsultaModel;
use App\Models\Admin\Ies;

class IntegracionController extends BaseApiController
{
    private $serviceMicrosoft;
    private $serviceMoodle;
    private $objConsulta;
    private $objIes;
    private $endpointSynUserIntegraciones = "/api/consulta/sync_user_integraciones/";
    private $endpointLoginMoodle = "/api/consulta/login_user_Moodle/";
    private $endpointCourseMoodle = "/api/consulta/course_moodle/";
    private $endpointCategoryMoodle = "/api/consulta/category_moodle/";
    private $endpointUserMicrosoft = "/api/consulta/sync_user_integraciones/";
    private $endpointMeetMicrosoft = "/api/consulta/meet_microsoft/";

    public function __construct()

    {
        // 1. Inicializamos BaseApiController (Carga DB, Headers, etc.)
        parent::__construct();

        // 2. Instanciamos el servicio de lógica de negocio
        $this->serviceMicrosoft = new MicrosoftService();
        $this->serviceMoodle = new MoodleService();
        $this->objConsulta = new ConsultaModel();
        $this->objIes = new Ies();
    }

    //=============================== INICIO INTEGRACIONES ===============================
    public function syncUserIntegraciones()
    {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);
        // 1. Seguridad: Valida X-Api-Key, Suscripción y obtiene Tenant ID
        $this->requireApiKey($this->endpointSynUserIntegraciones);
        $id_ies = $this->tenantId;
        $ies = $this->objIes->find($id_ies);
        $MICROSOFT_SUFIJO_EMAIL = $ies['MICROSOFT_SUFIJO_EMAIL'];

        $sigiId = $data['id'];
        $dni = $data['dni'];
        $email = $dni . $MICROSOFT_SUFIJO_EMAIL;
        $nombres = $data['nombres'];
        $apellidos = $data['apellidos'];
        $passwordPlano = $data['passwordPlano'] ?? \Core\Auth::crearPassword(8);

        $responseApi = [];
        //---------------------- INICIO INTEGRACION MOODLE --------------------------
        if ($ies['MOODLE_SYNC_ACTIVE'] > 0) {
            try {
                $MOODLE_URL = $ies['MOODLE_URL'];
                $MOODLE_TOKEN = $ies['MOODLE_TOKEN'];
                $resultado = $this->serviceMoodle->syncUser($MOODLE_URL, $MOODLE_TOKEN, $id_ies, $sigiId, $dni, $email, $nombres, $apellidos, $passwordPlano);
                if ($resultado['id'] > 0) {
                    $responseApi['moodle'] = $resultado;
                }
            } catch (\Exception $e) {
                $responseApi['moodle']['message_error'] = $e->getMessage();
            }
        } else {
            $responseApi['moodle']['message_error'] = 'No cuenta con integración con Moodle';
        }
        //---------------------- FIN INTEGRACION MOODLE --------------------------
        //---------------------- INICIO INTEGRACION MICROSOFT --------------------------
        if ($ies['MICROSOFT_SYNC_ACTIVE'] > 0) {
            $programa_estudios = $data['programa_estudios'];
            $tipo_usuario = $data['tipo_usuario'] ?? 'ESTUDIANTE';
            $estado_post = $data['estado'] ?? 1;
            $estado = $estado_post == 1 ? true : false;
            try {
                $MICROSOFT_SKU_ID_DOCENTE = $ies['MICROSOFT_SKU_ID_DOCENTE'];
                $MICROSOFT_SKU_ID_ESTUDIANTE = $ies['MICROSOFT_SKU_ID_ESTUDIANTE'];
                //obtencion de token

                $resultado = $this->serviceMicrosoft->syncUserMicrosoft($id_ies, $sigiId, $dni, $email, $nombres, $apellidos, $passwordPlano, $programa_estudios, $tipo_usuario, $estado, $MICROSOFT_SKU_ID_DOCENTE, $MICROSOFT_SKU_ID_ESTUDIANTE);
                if ($resultado['success']) {
                    $responseApi['microsoft'] = $resultado;
                } else {
                    $responseApi['microsoft'] = $resultado;
                }
            } catch (\Exception $e) {
                $responseApi['microsoft']['message_error'] = $e->getMessage();
            }
        } else {
            $responseApi['microsoft']['message_error'] = "No cuenta con integración con Microsoft";
        }
        //---------------------- FIN INTEGRACION MICROSOFT --------------------------

        // Estructura limpia con paginación

        $this->json([
            'ok' => true,
            'data' => $responseApi
        ]);
    }
    public function sincronizarUsuariosMasivos()
    {
        $responseApi = [];
        $this->requireApiKey($this->endpointSynUserIntegraciones);
        $id_ies = $this->tenantId;
        $ies = $this->objIes->find($id_ies);
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);
        $SUFIJO_EMAIL = $ies['MICROSOFT_SUFIJO_EMAIL'];
        if ($ies['MOODLE_SYNC_ACTIVE'] > 0) {
            $MOODLE_URL = $ies['MOODLE_URL'];
            $MOODLE_TOKEN = $ies['MOODLE_TOKEN'];
            $usersPayload = [];
            foreach ($data as $usr) {
                // Validar correo para evitar error fatal en Moodle
                $email = $usr['dni'] . $SUFIJO_EMAIL;
                // Mapeo: Array que llega de SIGI Local -> Array que pide Moodle
                $usersPayload[] = [
                    'username'      => $usr['dni'],
                    'password'      => $usr['passwordPlano'], // La clave plana "Sigi..." que enviaste
                    'firstname'     => $usr['nombres'],
                    'lastname'      => $usr['apellidos'],
                    'email'         => $email,
                    'auth'          => 'manual',
                    'idnumber'      => (string)$usr['id'], // VINCULACIÓN: ID de SIGI Local
                    'preferences'   => [
                        ['type'  => 'auth_forcepasswordchange', 'value' => 0]
                    ]
                ];
            }
            $responseApi['moodle_ok'] = true;
            $responseApi['moodle'] = $this->serviceMoodle->registrarLoteMasivo($MOODLE_URL, $MOODLE_TOKEN, $usersPayload);
        } else {
            $responseApi['moodle_ok'] = false;
            $responseApi['moodle']['message_error'] = 'No cuenta con integración con Moodle';
        }
        if ($ies['MICROSOFT_SYNC_ACTIVE'] > 0) {
            $MICROSOFT_SKU_ID_DOCENTE = $ies['MICROSOFT_SKU_ID_DOCENTE'];
            $MICROSOFT_SKU_ID_ESTUDIANTE = $ies['MICROSOFT_SKU_ID_ESTUDIANTE'];
            $responseApi['microsoft_ok'] = true;
            $responseApi['microsoft'] = $this->serviceMicrosoft->registrarLoteMasivo($id_ies, $data, $MICROSOFT_SKU_ID_DOCENTE, $MICROSOFT_SKU_ID_ESTUDIANTE, $SUFIJO_EMAIL);
        } else {
            $responseApi['microsoft_ok'] = false;
            $responseApi['microsoft']['message_error'] = 'No cuenta con integración con Microsoft';
        }
        if ($responseApi['moodle_ok'] || $responseApi['microsoft_ok']) {
            $responseApi['success'] = true;
        } else {
            $responseApi['success'] = false;
        }
        $this->json($responseApi);
    }

    public function loginMoodle()
    {
        $this->requireApiKey($this->endpointLoginMoodle);
        $id_ies = $this->tenantId;
        $ies = $this->objIes->find($id_ies);
        if ($ies['MOODLE_SYNC_ACTIVE'] > 0) {
            $json_data = file_get_contents('php://input');
            $data = json_decode($json_data, true);
            $id_user = $data['idUsuarioSigi'];
            $hacia = $data['a'];
            $id = $data['id'];
            $this->json([
                'ok' => true,
                'url' => $this->serviceMoodle->getAutoLoginUrl($id_user, $ies['MOODLE_URL'], $ies['MOODLE_SSO_KEY'], $hacia, $id)
            ]);
        } else {
            $this->json([
                'ok' => false,
                'message_error' => 'No cuenta con integración con Moodle'
            ]);
        }
    }
    //=============================== FIN INTEGRACIONES ===============================
}
