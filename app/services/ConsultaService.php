<?php

namespace App\Services;

use App\Models\Api\ConsultaModel;

class ConsultaService
{
    private $model;

    public function __construct()
    {
        $this->model = new ConsultaModel();
    }

    /**
     * Proceso principal de consulta
     */
    public function consultar($tipo, $numero, $id_ies, $endpoint)
    {
        $tipo = strtoupper($tipo);

        // 1. VALIDACIONES PREVIAS
        if ($tipo === 'DNI' && !preg_match('/^\d{8}$/', $numero)) {
            return ['error' => 'Formato de DNI inválido', 'code' => 400];
        }
        if ($tipo === 'RUC' && !preg_match('/^\d{11}$/', $numero)) {
            return ['error' => 'Formato de RUC inválido', 'code' => 400];
        }

        // 2. CONTROL DE CUOTAS Y PLANES (IES)
        if (!empty($id_ies)) {
            $tieneSaldo = $this->model->verificarCuotaCliente($id_ies, $endpoint);
            if (!$tieneSaldo) {
                return [
                    'error' => 'Tu institución ha superado el límite de consultas contratado.',
                    'code' => 402 // Payment Required
                ];
            }
        }

        // 3. OBTENCIÓN DE TOKEN (ROUND ROBIN)
        // Busca en BD el token: eyJ0eXAiOiJKV1Qi...
        $tokenData = $this->model->obtenerSiguienteToken();

        if (!$tokenData) {
            return [
                'error' => 'Error de configuración: No hay tokens disponibles.',
                'code' => 503
            ];
        }

        // 4. CONSUMO DE API EXTERNA
        $respuesta = $this->llamarApiExterna($tipo, $numero, $tokenData['token'], $tokenData['url']);

        // 5. ROTACIÓN DE TOKEN
        // Solo rotamos si el token funcionó (no dio 401 Unauthorized)
        if ($respuesta['http_code'] != 401) {
            $this->model->actualizarUsoToken($tokenData['id']);
        }

        // 6. RESPUESTA
        if ($respuesta['success']) {
            // Cobro al cliente
            if (!empty($id_ies)) {
                //$this->model->registrarConsumoCliente($id_ies);
            }

            return [
                'success' => true,
                'data' => $respuesta['data'],
            ];
        } else {
            $code = ($respuesta['http_code'] == 404) ? 404 : 500;
            return [
                'error' => 'No se encontraron resultados.',
                'code' => $code
            ];
        }
    }

    /**
     * Realiza la petición cURL con el formato:
     * https://dniruc.apisperu.com/api/v1/ruc/20131312955?token=...
     */
    private function llamarApiExterna($tipo, $numero, $token, $apiUrl)
    {
        // Definimos el segmento de URL: 'dni' o 'ruc' (minúsculas)
        $segmento = ($tipo == 'DNI') ? 'dni' : 'ruc';

        // CONSTRUCCIÓN DE LA URL
        $url = "{$apiUrl}/{$segmento}/{$numero}?api_token={$token}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        // Verificamos éxito (200 OK y que data no sea null)
        if ($httpCode == 200 && $data) {
            return ['success' => true, 'data' => $data, 'http_code' => 200];
        }

        return ['success' => false, 'http_code' => $httpCode, 'curl_error' => $curlError];
    }


    /**
     * Lógica para Colegios (Escale)
     */
    public function buscarColegios($data, $id_ies, $page = 1, $limit = 10, $endpoint = '/api/consulta/colegios/')
    {
        // 1. Validaciones
        if (strlen($data) < 3) {
            return ['error' => 'Ingrese al menos 3 caracteres para buscar.', 'code' => 400];
        }

        // 2. Control de Cuotas (Opcional: ¿Quieres cobrar por buscar colegios?)
        // Si la data es local, quizás no quieras cobrar, o quizás sí. 
        // Descomenta si quieres limitar también estas consultas.

        if (!empty($id_ies)) {
            if (!$this->model->verificarCuotaCliente($id_ies, $endpoint)) {
                return ['error' => 'Límite de consultas excedido.', 'code' => 402];
            }
            // $this->model->registrarConsumoCliente($id_ies); // Registrar consumo
        }

        // 3. Configuración de Paginación
        $page = ($page < 1) ? 1 : (int)$page;
        $offset = ($page - 1) * $limit;

        // 4. Llamada al Modelo
        $resultado = $this->model->buscarColegiosLocal($data, $limit, $offset);

        // 5. Estructurar Respuesta JSON Paginada
        $meta = [
            'total_registros' => $resultado['total'],
            'total_paginas'   => ceil($resultado['total'] / $limit),
            'pagina_actual'   => $page,
            'registros_por_pagina' => $limit
        ];

        return [
            'success' => true,
            'data'    => $resultado['items'],
            'meta'    => $meta
        ];
    }
}
