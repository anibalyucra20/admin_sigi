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
        // 1. Seguridad: Valida X-Api-Key, Suscripción y obtiene Tenant ID
        $this->requireApiKey($this->endpointSynUserIntegraciones);
        $id_ies = $this->tenantId;
        $ies = $this->objIes->find($id_ies);
        $MICROSOFT_SUFIJO_EMAIL = $ies['MICROSOFT_SUFIJO_EMAIL'];

        $sigiId = $_POST['sigiId'];
        $dni = $_POST['dni'];
        $email = $dni . $MICROSOFT_SUFIJO_EMAIL;
        $nombres = $_POST['nombres'];
        $apellidos = $_POST['apellidos'];
        $passwordPlano = $_POST['passwordPlano'] ?? null;

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
            $programa_estudios = $_POST['programa_estudios'] ?? '';
            $tipo_usuario = $_POST['tipo_usuario'] ?? 'ESTUDIANTE';
            $estado_post = $_POST['estado'] ?? 1;
            $estado = $estado_post == 1 ? true : false;
            try {
                $MICROSOFT_CLIENT_ID = $ies['MICROSOFT_CLIENT_ID'];
                $MICROSOFT_CLIENT_SECRET = $ies['MICROSOFT_CLIENT_SECRET'];
                $MICROSOFT_TENANT_ID = $ies['MICROSOFT_TENANT_ID'];

                $MICROSOFT_SKU_ID_DOCENTE = $ies['MICROSOFT_SKU_ID_DOCENTE'];
                $MICROSOFT_SKU_ID_ESTUDIANTE = $ies['MICROSOFT_SKU_ID_ESTUDIANTE'];
                //obtencion de token

                $resultado = $this->serviceMicrosoft->syncUserMicrosoft($id_ies, $sigiId, $dni, $email, $nombres, $apellidos, $passwordPlano, $programa_estudios, $tipo_usuario, $estado, $MICROSOFT_CLIENT_ID, $MICROSOFT_CLIENT_SECRET, $MICROSOFT_TENANT_ID, $MICROSOFT_SKU_ID_DOCENTE, $MICROSOFT_SKU_ID_ESTUDIANTE);
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
    //=============================== FIN INTEGRACIONES ===============================
}
