<?php

namespace App\Services;

require_once __DIR__ . '/../models/Admin/Ies.php';

use App\Models\Api\ConsultaModel;
use App\Models\Admin\Ies;

class MicrosoftService
{
    private $objConsulta;
    private $objIes;

    public function __construct()
    {
        $this->objConsulta = new ConsultaModel();
        $this->objIes = new Ies();
    }

    public function getToken($id_ies)
    {
        $ies = $this->objIes->find($id_ies);
        //validar tiempo de vida de token 50 minutos
        $fecha_actual = date('Y-m-d H:i:s');
        $fecha_token = $ies['MICROSOFT_TOKEN_EXPIRE'];

        if ($fecha_actual > $fecha_token) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://login.microsoftonline.com/" . $ies['MICROSOFT_TENANT_ID'] . "/oauth2/v2.0/token");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'client_id' => $ies['MICROSOFT_CLIENT_ID'],
                'client_secret' => $ies['MICROSOFT_CLIENT_SECRET'],
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials'
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $raw_response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($raw_response, true);

            if (isset($data['access_token'])) {
                // Restar 10 min (600 seg) por seguridad
                $seconds_to_expire = $data['expires_in'] - 600;
                $new_expire_in = date('Y-m-d H:i:s', time() + $seconds_to_expire);

                $this->objIes->actualizarTokenMicrosoft($id_ies, $data['access_token'], $new_expire_in);
                return ['success' => true, 'token' => $data['access_token'], 'msg' => 'Token obtenido correctamente'];
            } else {
                return ['success' => false, 'token' => isset($data['error_description']) ? $data['error_description'] : 'Error desconocido', 'msg' => 'No se pudo obtener el token'];
            }
        } else {
            return ['success' => true, 'token' => $ies['MICROSOFT_TOKEN_DINAMICO'], 'msg' => 'Token encontrado en la base de datos'];
        }
    }

    public function syncUserMicrosoft($id_ies, $sigiId, $dni, $email, $nombres, $apellidos, $passwordPlano, $programa_estudios, $tipo_usuario, $estado, $MICROSOFT_SKU_ID_DOCENTE, $MICROSOFT_SKU_ID_ESTUDIANTE)
    {
        $token = $this->getToken($id_ies);

        if ($token['success']) {
            if ($tipo_usuario != 'ESTUDIANTE') {
                $skuId = $MICROSOFT_SKU_ID_DOCENTE;
            } else {
                $skuId = $MICROSOFT_SKU_ID_ESTUDIANTE;
            }

            // buscar usuario en Microsoft
            $user = $this->searchUserMicrosoft($email, $id_ies);
            if ($user['success']) {
                $id_microsoft = $user['user']['id'];
            } else {
                $id_microsoft = null;
            }

            $userPayload = [
                'accountEnabled' => $estado,
                'displayName' => ($nombres . ' ' . $apellidos) ?? '-',
                'mailNickname' => $dni, // Usamos DNI como alias para evitar errores con puntos o espacios
                'userPrincipalName' => $email,
                'surname' => $apellidos ?? '-',
                'givenName' => $nombres ?? '-',
                'jobTitle' => $tipo_usuario ?? '-',
                'department' => $programa_estudios ?? '-',
                'preferredLanguage' => 'es-ES',
                'usageLocation' => 'PE', // OBLIGATORIO PARA LICENCIAS
            ];

            // Solo agregamos password si es CREACION o si enviaron uno nuevo
            if ($passwordPlano != null) {
                $userPayload['passwordProfile'] = [
                    'forceChangePasswordNextSignIn' => false,
                    'password' => $passwordPlano
                ];
            } elseif ($id_microsoft == null) {
                // Si es usuario nuevo y no vino password, generar uno aleatorio
                $passforMicrosoft = \Core\Auth::crearPassword(8);
                $userPayload['passwordProfile'] = [
                    'forceChangePasswordNextSignIn' => false,
                    'password' => $passforMicrosoft
                ];
            }

            if ($id_microsoft && strlen($id_microsoft) > 10) {
                $url = "https://graph.microsoft.com/v1.0/users/" . $id_microsoft;
                $method = "PATCH";
                // En PATCH no se envía userPrincipalName ni mailNickname a menos que sea necesario cambiarlos
                unset($userPayload['mailNickname']);
                unset($userPayload['userPrincipalName']);
                unset($userPayload['passwordProfile']); // Generalmente no se cambia el pass en sync simple
            } else {
                $url = "https://graph.microsoft.com/v1.0/users"; // Sin barra al final
                $method = "POST";
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $token['token'],
                'Content-Type: application/json'
            ));

            if ($method == "PATCH") {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
            } else {
                curl_setopt($ch, CURLOPT_POST, 1);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userPayload));

            $raw_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = json_decode($raw_response, true);
            // LOGICA DE RESPUESTA
            if ($method == "PATCH") {
                if ($http_code == 204 || $http_code == 200) {
                    // Usuario actualizado, verificamos licencia por si acaso
                    $this->assignLicenseMicrosoft($id_microsoft, $skuId, $id_ies);
                    return ['success' => true, 'id_microsoft' => $id_microsoft, 'msg' => 'Usuario actualizado'];
                } else {
                    return ['success' => false, 'details' => isset($data['error']) ? $data['error']['message'] : 'Error update'];
                }
            } else { // POST
                if ($http_code == 201) {
                    // CREADO CORRECTAMENTE, AHORA LA LICENCIA
                    $new_id = $data['id'];
                    $license = $this->assignLicenseMicrosoft($new_id, $skuId, $id_ies);
                    return ['success' => true, 'id_microsoft' => $new_id, 'license' => $license];
                } else {
                    return ['success' => false, 'details' => isset($data['error']) ? $data['error']['message'] : 'Error create'];
                }
            }
        } else {
            return ['success' => false, 'details' => $token['msg']];
        }
    }

    public function searchUserMicrosoft($email, $id_ies)
    {
        $token = $this->getToken($id_ies);
        if ($token['success']) {
            $params = [
                '$filter' => "mail eq '$email' or userPrincipalName eq '$email'", // Buscar por ambos
                '$select' => 'id,displayName,mail,userPrincipalName'
            ];
            $url = "https://graph.microsoft.com/v1.0/users?" . http_build_query($params);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $token['token'],
                'Content-Type: application/json'
            ));
            $raw_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = json_decode($raw_response, true);

            if ($http_code == 200 && isset($data['value'][0])) {
                return ['success' => true, 'user' => $data['value'][0]];
            } else {
                return ['success' => false, 'user' => null];
            }
        } else {
            return ['success' => false, 'user' => null];
        }
    }

    public function assignLicenseMicrosoft($id_microsoft, $skuId, $id_ies)
    {
        $token = $this->getToken($id_ies);
        if ($token['success']) {
            $licensePayload = [
                'addLicenses' => [
                    [
                        "disabledPlans" => [],
                        "skuId" => $skuId
                    ]
                ],
                'removeLicenses' => []
            ];
            $url = "https://graph.microsoft.com/v1.0/users/" . $id_microsoft . "/assignLicense";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $token['token'],
                'Content-Type: application/json'
            ));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($licensePayload));

            $raw_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = json_decode($raw_response, true);

            if ($http_code == 200 || $http_code == 201) {
                return ['success' => true, 'details' => $data];
            } else {
                // Si el error es que ya tiene la licencia, lo consideramos éxito
                if (isset($data['error']['message']) && strpos($data['error']['message'], 'User already has') !== false) {
                    return ['success' => true, 'msg' => 'Ya tenia licencia'];
                }
                return ['success' => false, 'details' => isset($data['error']) ? $data['error']['message'] : 'Error licencia'];
            }
        } else {
            return ['success' => false, 'details' => $token['msg']];
        }
    }

    public function verLicencias($id_ies)
    {
        $token = $this->getToken($id_ies);
        if ($token['success']) {
            $url = "https://graph.microsoft.com/v1.0/subscribedSkus";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $token['token'],
                'Content-Type: application/json'
            ));
            $raw_response = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($raw_response, true);

            if (isset($data['value'])) {
                return ['success' => true, 'details' => $data];
            }
            return ['success' => false, 'details' => 'No data'];
        }
        return ['success' => false, 'details' => $token['msg']];
    }

    // -------------------------------------------------------------------------
    // REGISTRO MASIVO CON ASIGNACIÓN DE LICENCIA
    // -------------------------------------------------------------------------
    // NOTA: Agregué $skuId como parámetro obligatorio para saber qué licencia dar
    public function registrarLoteMasivo($id_ies, $listaEstudiantes, $skuIdDocente, $skuIdEstudiante, $sufijoCorreo)
    {
        $tokenResponse = $this->getToken($id_ies);

        if ($tokenResponse['success']) {
            $token = $tokenResponse['token'];
            $urlBatch = "https://graph.microsoft.com/v1.0/\$batch";

            // 1. Dividir en grupos de 20
            $lotes = array_chunk($listaEstudiantes, 20);
            $informeFinal = [];

            foreach ($lotes as $grupo) {

                // --- PARTE A: Preparar peticiones de CREACIÓN ---
                $requestsCreacion = [];

                // $grupo se reindexa automáticamente de 0 a 19 en cada lote
                foreach ($grupo as $i => $estudiante) {

                    $estado = $estudiante['estado'] == 1 ? true : false;

                    $requestsCreacion[] = [
                        "id" => (string)$i, // Este ID "0", "1"... es nuestro enlace vital
                        "method" => "POST",
                        "url" => "/users",
                        "headers" => ["Content-Type" => "application/json"],
                        "body" => [
                            "accountEnabled" => $estado,
                            "displayName" => ($estudiante['nombres'] . ' ' . $estudiante['apellidos']) ?? '-',
                            "mailNickname" => $estudiante['dni'],
                            "userPrincipalName" => $estudiante['dni'] . $sufijoCorreo,
                            'surname' => $estudiante['apellidos'] ?? '-',
                            'givenName' => $estudiante['nombres'] ?? '-',
                            'jobTitle' => $estudiante['tipo_usuario'] ?? '-',
                            'department' => $estudiante['programa_estudios'] ?? '-',
                            'preferredLanguage' => 'es-ES',
                            "usageLocation" => "PE",
                            "passwordProfile" => [
                                "forceChangePasswordNextSignIn" => false,
                                "password" => $estudiante['passwordPlano']
                            ]
                        ]
                    ];
                }

                // Enviar Lote de Creación
                $payloadCreacion = json_encode(["requests" => $requestsCreacion]);

                $ch = curl_init($urlBatch);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadCreacion);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $token",
                    "Content-Type: application/json"
                ]);
                $responseCreacion = curl_exec($ch);
                curl_close($ch);

                $resultadosCreacion = json_decode($responseCreacion, true);

                // --- PARTE B: Procesar respuestas y vincular IDs ---
                $requestsLicencias = [];

                if (isset($resultadosCreacion['responses'])) {
                    foreach ($resultadosCreacion['responses'] as $resp) {

                        $reqId = $resp['id']; // Obtenemos el ID "0", "1"... de la petición

                        // --- VINCULACIÓN CRÍTICA ---
                        // Usamos el reqId para obtener los datos originales del estudiante en este grupo
                        $datosOriginales = $grupo[$reqId];
                        $idSigi = $datosOriginales['id']; // <--- TU ID DE SIGI

                        // Recalculamos la licencia correcta para ESTE usuario específico
                        // (Corrección del bug de licencias mezcladas)
                        $skuParaEsteUsuario = ($datosOriginales['tipo_usuario'] != 'ESTUDIANTE') ? $skuIdDocente : $skuIdEstudiante;

                        // Si se creó correctamente (201 Created)
                        if ($resp['status'] == 201 && isset($resp['body']['id'])) {
                            $newUserId = $resp['body']['id']; // ID Microsoft

                            // Guardamos ambos IDs en el informe final
                            $informeFinal[] = [
                                'status' => true,
                                'id_sigi' => $idSigi,          // <--- Vinculación para tu BD
                                'id_microsoft' => $newUserId,  // <--- Vinculación para tu BD
                                'correo' => $resp['body']['userPrincipalName']
                            ];

                            // Preparamos licencia
                            $requestsLicencias[] = [
                                "id" => "lic-" . $newUserId,
                                "method" => "POST",
                                "url" => "/users/" . $newUserId . "/assignLicense",
                                "headers" => ["Content-Type" => "application/json"],
                                "body" => [
                                    "addLicenses" => [
                                        ["disabledPlans" => [], "skuId" => $skuParaEsteUsuario]
                                    ],
                                    "removeLicenses" => []
                                ]
                            ];
                        } else {
                            $correosearch = $datosOriginales['dni'] . $sufijoCorreo;
                            //se podria realizar llamar a la funcion syncUserMicrosoft para sincronizar el usuario
                            //$user = $this->searchUserMicrosoft($correosearch, $id_ies);
                            $estado_user = $datosOriginales['estado'] == 1 ? true : false;
                            $user = $this->syncUserMicrosoft($id_ies, $idSigi, $datosOriginales['dni'], $correosearch, $datosOriginales['nombres'], $datosOriginales['apellidos'], $datosOriginales['passwordPlano'], $datosOriginales['programa_estudios'], $datosOriginales['tipo_usuario'], $estado_user, $skuIdDocente, $skuIdEstudiante);
                            if ($user['success'] == true) {
                                $informeFinal[] = [
                                    'status' => true,
                                    'id_sigi' => $idSigi,          // <--- Vinculación para tu BD
                                    'id_microsoft' => $user['id_microsoft'],  // <--- Vinculación para tu BD
                                    'correo' => $correosearch
                                ];
                            } else {
                                // Guardar error con el ID de SIGI para saber quién falló
                                $errorMsg = isset($resp['body']['error']['message']) ? $resp['body']['error']['message'] : 'Error desconocido';
                                $informeFinal[] = [
                                    'status' => false,
                                    'id_sigi' => $idSigi, // <--- Importante para reportar error
                                    'id_microsoft' => $user['id_microsoft'],
                                    'msg' => $errorMsg
                                ];
                            }
                        }
                    }
                }

                // --- PARTE C: Enviar Lote de LICENCIAS ---
                if (count($requestsLicencias) > 0) {
                    $payloadLicencias = json_encode(["requests" => $requestsLicencias]);
                    sleep(1);
                    $ch2 = curl_init($urlBatch);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_POST, true);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, $payloadLicencias);
                    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer $token",
                        "Content-Type: application/json"
                    ]);
                    curl_exec($ch2);
                    curl_close($ch2);
                }

                sleep(1);
            }

            return ['success' => true, 'reporte' => $informeFinal];
        } else {
            return ['success' => false, 'details' => $tokenResponse['msg']];
        }
    }
}
