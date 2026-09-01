<?php

namespace App\Controllers\Api;

require_once __DIR__ . '/BaseApiController.php';
require_once __DIR__ . '/../../models/Api/RubricaModel.php';

use App\Models\Api\RubricaModel;

class RubricasController extends BaseApiController
{
    private $model;
    
    // Definimos el endpoint para el tracking de métricas y facturación
    private $endpointRubricas = "/api/rubricas/";

    public function __construct()
    {
        // 1. Inicializamos BaseApiController (Carga DB, Headers, etc.)
        parent::__construct();

        // 2. Instanciamos el modelo de solo lectura
        $this->model = new RubricaModel();
    }

    /**
     * Endpoint Automático: GET /api/rubricas o /api/rubricas/index
     * Retorna el catálogo institucional de rúbricas activas.
     */
    public function index()
    {
        // 1. Seguridad: Valida X-Api-Key, Suscripción y obtiene Tenant ID (registra uso)
        $this->requireApiKey($this->endpointRubricas);

        try {
            // 2. Lógica: Obtener el catálogo
            $lista = $this->model->obtenerListadoActivas();

            // 3. Respuesta JSON estándar
            $this->json([
                'ok'    => true,
                'count' => count($lista),
                'data'  => $lista
            ]);
        } catch (\Throwable $e) {
            $this->error('Error interno del servidor', 500, 'SERVER_ERROR', ['detail' => $e->getMessage()]);
        }
    }

    /**
     * Endpoint Automático: GET /api/rubricas/detalle/{id}
     * Retorna una rúbrica específica con todo su contenido JSON para ser clonada.
     */
    public function detalle($id = null)
    {
        // Validación básica de parámetro URL
        if (empty($id) || !is_numeric($id)) {
            $this->error('Debe especificar un ID válido de la rúbrica en la URL.', 400, 'BAD_REQUEST');
        }

        // 1. Seguridad: Tracking y validación
        $this->requireApiKey($this->endpointRubricas);

        try {
            // 2. Lógica: Buscar la rúbrica
            $rubrica = $this->model->obtenerPorId((int)$id);

            if (!$rubrica) {
                $this->error('Rúbrica no encontrada o inactiva.', 404, 'NOT_FOUND');
            }

            // [Decisión Arquitectónica Clave]
            // El campo contenido_json viene como string desde MySQL.
            // Lo decodificamos a Array para que al pasarlo por $this->json() 
            // se incruste como un objeto JSON nativo y no como un String escapado,
            // facilitando la lectura directa por parte del SIGI Local.
            if (!empty($rubrica['contenido_json'])) {
                $rubrica['contenido_json'] = json_decode($rubrica['contenido_json'], true);
            }

            // 3. Respuesta JSON estándar
            $this->json([
                'ok'   => true,
                'data' => $rubrica
            ]);
        } catch (\Throwable $e) {
            $this->error('Error interno del servidor', 500, 'SERVER_ERROR', ['detail' => $e->getMessage()]);
        }
    }
}