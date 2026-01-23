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
    private $endpointSyncCourseMoodle = "/api/consulta/sync_course_moodle/";
    private $endpointSyncCategoryMoodle = "/api/consulta/sync_category_moodle/";
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

    public function sincronizarCursosMoodle()
    {
        $this->requireApiKey($this->endpointSyncCourseMoodle);
        $id_periodo = $_SESSION['sigi_periodo_actual_id'];

        $json_data = file_get_contents('php://input');
        $programacion = json_decode($json_data, true);
        $ies = $this->objIes->find($this->tenantId);
        $MOODLE_URL = $ies['MOODLE_URL'];
        $MOODLE_TOKEN = $ies['MOODLE_TOKEN'];

        $cacheCats = []; // Evita llamadas repetidas a Moodle
        $errores = [];
        $cursosCreados = 0;
        $listaCursos = [];
        $responseApi = [];

        // Mapeo de Turnos (Tu BD guarda M, T, N)
        $turnoMap = ['M' => 'MAÑANA', 'T' => 'TARDE', 'N' => 'NOCHE'];
        foreach ($programacion as $row) {
            try {
                // ======================================================
                // NIVEL 1: PERIODO (Raíz)
                // ======================================================
                $idNum_Per = 'P_' . $row['id_periodo'];
                if (!isset($cacheCats[$idNum_Per])) {
                    $cacheCats[$idNum_Per] = $this->serviceMoodle->getOrCreateCategory('PERIODO ' . $row['nombre_periodo'], $idNum_Per, 0, $MOODLE_URL, $MOODLE_TOKEN);
                }
                $idCat_Periodo = $cacheCats[$idNum_Per]; // <--- GUARDAMOS EN VARIABLE ESPECÍFICA

                // ======================================================
                // NIVEL 2: SEDE (Padre: Periodo)
                // ======================================================
                $idNum_Sede = $idNum_Per . '_S_' . $row['id_sede'];
                if (!isset($cacheCats[$idNum_Sede])) {
                    // Usamos $idCat_Periodo explícitamente
                    $cacheCats[$idNum_Sede] = $this->serviceMoodle->getOrCreateCategory('SEDE ' . $row['nombre_sede'], $idNum_Sede, $idCat_Periodo, $MOODLE_URL, $MOODLE_TOKEN);
                }
                $idCat_Sede = $cacheCats[$idNum_Sede]; // <--- GUARDAMOS EN VARIABLE ESPECÍFICA

                // ======================================================
                // NIVEL 3: PROGRAMA DE ESTUDIOS (Padre: Sede)
                // ======================================================
                $idNum_Prog = $idNum_Sede . '_PR_' . $row['id_programa'];
                if (!isset($cacheCats[$idNum_Prog])) {
                    $cacheCats[$idNum_Prog] = $this->serviceMoodle->getOrCreateCategory($row['nombre_programa'], $idNum_Prog, $idCat_Sede, $MOODLE_URL, $MOODLE_TOKEN);
                }
                $idCat_Programa = $cacheCats[$idNum_Prog]; // <--- GUARDAMOS EN VARIABLE ESPECÍFICA

                // ======================================================
                // NIVEL 4: PLAN DE ESTUDIOS (Padre: Programa)
                // ======================================================
                $idNum_Plan = $idNum_Prog . '_PL_' . $row['id_plan'];
                if (!isset($cacheCats[$idNum_Plan])) {
                    $cacheCats[$idNum_Plan] = $this->serviceMoodle->getOrCreateCategory('Plan ' . $row['nombre_plan'], $idNum_Plan, $idCat_Programa, $MOODLE_URL, $MOODLE_TOKEN);
                }
                $idCat_Plan = $cacheCats[$idNum_Plan]; // <--- GUARDAMOS EN VARIABLE ESPECÍFICA

                // ======================================================
                // NIVEL 5: MÓDULO FORMATIVO (Padre: Plan)
                // ======================================================
                $idNum_Mod = $idNum_Plan . '_MF_' . $row['id_modulo'];
                if (!isset($cacheCats[$idNum_Mod])) {
                    // Recortamos nombre por si es muy largo
                    $nomMod = mb_strimwidth($row['nombre_modulo'], 0, 100, "...");
                    // Aquí forzamos que el padre sea $idCat_Plan. Es imposible que se meta dentro de otro módulo.
                    $cacheCats[$idNum_Mod] = $this->serviceMoodle->getOrCreateCategory($nomMod, $idNum_Mod, $idCat_Plan, $MOODLE_URL, $MOODLE_TOKEN);
                }
                $idCat_Modulo = $cacheCats[$idNum_Mod]; // <--- GUARDAMOS EN VARIABLE ESPECÍFICA

                // ======================================================
                // NIVEL 6: SEMESTRE (Padre: Módulo)
                // ======================================================
                $semNombre = 'SEMESTRE ' . $row['nombre_semestre'];
                $idNum_Sem = $idNum_Mod . '_SEM_' . $row['id_semestre'];
                if (!isset($cacheCats[$idNum_Sem])) {
                    $cacheCats[$idNum_Sem] = $this->serviceMoodle->getOrCreateCategory($semNombre, $idNum_Sem, $idCat_Modulo, $MOODLE_URL, $MOODLE_TOKEN);
                }
                $idCat_Semestre = $cacheCats[$idNum_Sem]; // <--- GUARDAMOS EN VARIABLE ESPECÍFICA

                // ======================================================
                // NIVEL 7: TURNO (Padre: Semestre)
                // ======================================================
                $turNombre = 'TURNO ' . ($turnoMap[$row['turno']] ?? $row['turno']);
                $idNum_Tur = $idNum_Sem . '_T_' . $row['turno'];

                if (!isset($cacheCats[$idNum_Tur])) {
                    $cacheCats[$idNum_Tur] = $this->serviceMoodle->getOrCreateCategory($turNombre, $idNum_Tur, $idCat_Semestre, $MOODLE_URL, $MOODLE_TOKEN);
                }
                $idCat_Turno = $cacheCats[$idNum_Tur]; // <--- GUARDAMOS EN VARIABLE ESPECÍFICA

                // ======================================================
                // NIVEL 8: SECCIÓN (Padre: Turno)
                // ======================================================
                $secNombre = 'SECCIÓN ' . $row['seccion'];
                $idNum_Sec = $idNum_Tur . '_SEC_' . $row['seccion'];
                if (!isset($cacheCats[$idNum_Sec])) {
                    $cacheCats[$idNum_Sec] = $this->serviceMoodle->getOrCreateCategory($secNombre, $idNum_Sec, $idCat_Turno, $MOODLE_URL, $MOODLE_TOKEN);
                }
                $idCat_Seccion = $cacheCats[$idNum_Sec]; // <--- GUARDAMOS EN VARIABLE ESPECÍFICA

                // ======================================================
                // NIVEL 9: CURSO (Padre: Sección)
                // ======================================================
                $idnumber_Curso = 'PROG_' . $row['id_programacion'];
                $codProg = !empty($row['codigo_programa']) ? $row['codigo_programa'] : 'PR' . $row['id_programa'];
                $shortname = $codProg . '-UD' . $row['id_ud'] . '-' . $row['seccion'] . '-' . $row['turno'];
                $fullname = $row['nombre_ud'] . ' - ' . $row['seccion'] . ' ' . $row['turno'];

                $moodleCourseId = $this->serviceMoodle->createCourse([
                    'fullname'   => $fullname,
                    'shortname'  => $shortname,
                    'categoryId' => $idCat_Seccion,      // Usamos explícitamente la categoría Sección
                    'idnumber'   => $idnumber_Curso,
                    'summary'    => "Unidad Didáctica: {$row['nombre_ud']}."
                ], $MOODLE_URL, $MOODLE_TOKEN);

                if ($moodleCourseId) {
                    $cursosCreados++;
                    $listaCursos[$row['id_programacion']] = $moodleCourseId;
                } else {
                    $errores[] = "Error creando curso: $shortname";
                }
            } catch (\Exception $e) {
                $errores[] = "Excepción en ID Prog {$row['id_programacion']}: " . $e->getMessage();
            }
        }

        unset($cacheCats);

        // Respuesta API
        $responseApi['success'] = ($cursosCreados > 0);
        $responseApi['message'] = ($cursosCreados > 0) ? $cursosCreados . ' Cursos creados exitosamente' : 'No se crearon cursos';
        $responseApi['cursosCreados'] = $cursosCreados;
        $responseApi['listaCursos'] = $listaCursos;
        $responseApi['errores'] = $errores;

        $this->json($responseApi);
        exit;
    }
    //=============================== FIN INTEGRACIONES ===============================
}
