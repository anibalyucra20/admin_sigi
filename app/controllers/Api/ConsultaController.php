<?php

namespace App\Controllers\Api;

require_once __DIR__ . '/BaseApiController.php';
require_once __DIR__ . '/../../services/ConsultaService.php';

use App\Services\ConsultaService;

class ConsultaController extends BaseApiController
{
    private $service;
    private $endpointDniRuc = "/api/consulta/ruc_dni/";
    private $endpointColegios = "/api/consulta/colegios/";
    private $endpointDepartamentos = "/api/consulta/departamentos/";

    public function __construct()

    {
        // 1. Inicializamos BaseApiController (Carga DB, Headers, etc.)
        parent::__construct();

        // 2. Instanciamos el servicio de lógica de negocio
        $this->service = new ConsultaService();
    }

    /**
     * Endpoint Automático: GET /api/consulta/dni/{numero}
     * Tu Core\App llama a este método automáticamente por la URL.
     */
    public function dni($numero = null)
    {
        // Validación básica de parámetro URL
        if (empty($numero)) {
            $this->error('Debe especificar el número de DNI en la URL.', 400, 'BAD_REQUEST');
        }

        // 1. Seguridad: Valida X-Api-Key, Suscripción y obtiene Tenant ID
        $this->requireApiKey($this->endpointDniRuc);

        // 2. Lógica: Consulta (Cache -> API Externa -> Log)
        $resultado = $this->service->consultar('DNI', $numero, $this->tenantId, $this->endpointDniRuc);

        // 3. Respuesta
        if (isset($resultado['error'])) {
            $this->error($resultado['error'], $resultado['code'], 'CONSULTA_ERROR');
        } else {
            // Retorna JSON estándar
            $this->json(['ok' => true, 'data' => $resultado['data']]);
        }
    }

    /**
     * Endpoint Automático: GET /api/consulta/ruc/{numero}
     */
    public function ruc($numero = null)
    {
        if (empty($numero)) {
            $this->error('Debe especificar el número de RUC en la URL.', 400, 'BAD_REQUEST');
        }

        $this->requireApiKey($this->endpointDniRuc);

        $resultado = $this->service->consultar('RUC', $numero, $this->tenantId, $this->endpointDniRuc);

        if (isset($resultado['error'])) {
            $this->error($resultado['error'], $resultado['code'], 'CONSULTA_ERROR');
        } else {
            $this->json(['ok' => true, 'data' => $resultado['data']]);
        }
    }


    /**
     * Endpoint: GET /api/consulta/colegios?data=XX&page=1
     */
    public function colegios()
    {
        // 1. Seguridad
        $this->requireApiKey($this->endpointColegios);

        // 2. Obtener parámetros Query String
        // Nota: En Core\App no pasas $_GET automáticamente a params, 
        // así que los leemos directo de la superglobal.
        $data = $_GET['data'] ?? '';
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 10;
        $departamento = $_GET['departamento'] ?? '';
        $provincia = $_GET['provincia'] ?? '';
        $distrito = $_GET['distrito'] ?? '';

        if (empty($data)) {
            $this->error('El parámetro "data" es requerido.', 400, 'BAD_REQUEST');
        }

        // 3. Ejecutar Servicio
        $resultado = $this->service->buscarColegios($data, $this->tenantId, $page, $limit, $this->endpointColegios, $departamento, $provincia, $distrito);

        // 4. Respuesta
        if (isset($resultado['error'])) {
            $this->error($resultado['error'], $resultado['code'], 'SEARCH_ERROR');
        } else {
            // Estructura limpia con paginación
            $this->json([
                'ok' => true,
                'data' => $resultado['data'],
                'pagination' => $resultado['meta']
            ]);
        }
    }
    public function departamentos()
    {
        // 1. Seguridad
        $this->requireApiKey($this->endpointDepartamentos);
        // 1. Ejecutar Servicio
        $resultado = $this->service->departamentos();
        // 2. Respuesta
        if (isset($resultado['error'])) {
            $this->error($resultado['error'], $resultado['code'], 'SEARCH_ERROR');
        } else {
            // Estructura limpia con paginación
            $this->json([
                'ok' => true,
                'data' => $resultado['data']
            ]);
        }
    }
    public function provincias()
    {
        // 1. Seguridad
        $this->requireApiKey($this->endpointDepartamentos);

        $departamento = $_GET['departamento'] ?? '';

        // 1. Ejecutar Servicio
        $resultado = $this->service->provincias($departamento);
        // 2. Respuesta
        if (isset($resultado['error'])) {
            $this->error($resultado['error'], $resultado['code'], 'SEARCH_ERROR');
        } else {
            // Estructura limpia con paginación
            $this->json([
                'ok' => true,
                'data' => $resultado['data']
            ]);
        }
    }
    public function distritos()
    {
        // 1. Seguridad
        $this->requireApiKey($this->endpointDepartamentos);

        $departamento = $_GET['departamento'] ?? '';
        $provincia = $_GET['provincia'] ?? '';

        // 1. Ejecutar Servicio
        $resultado = $this->service->distritos($departamento, $provincia);
        // 2. Respuesta
        if (isset($resultado['error'])) {
            $this->error($resultado['error'], $resultado['code'], 'SEARCH_ERROR');
        } else {
            // Estructura limpia con paginación
            $this->json([
                'ok' => true,
                'data' => $resultado['data']
            ]);
        }
    }
}
