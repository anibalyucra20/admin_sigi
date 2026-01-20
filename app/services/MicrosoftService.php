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
            // Ejecutar
            $raw_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Opcional: para verificar si fue 200 OK
            curl_close($ch);
            // 1. Decodificar PRIMERO para convertir el texto a Array
            $data = json_decode($raw_response, true);
            // 2. Verificar si obtuvimos el token correctamente
            if (isset($data['access_token'])) {
                // Calcular expiración (restando 5 min de seguridad para no quedar justos)
                // expires_in suele ser 3599 segundos.
                $seconds_to_expire = $data['expires_in'] - 600;
                $new_expire_in = date('Y-m-d H:i:s', time() + $seconds_to_expire);
                // Aquí ya puedes guardar en BD sin errores
                $this->objIes->actualizarTokenMicrosoft($id_ies, $data['access_token'], $new_expire_in);
                return ['success' => true, 'token' => $data['access_token'], 'msg' => 'Token obtenido correctamente']; // Retornamos el array limpio
            } else {
                // Manejo de error si Microsoft rechaza las credenciales
                return ['success' => false, 'token' => $data['error_description'], 'msg' => 'No se pudo obtener el token'];
            }
        } else {
            return ['success' => true, 'token' => $ies['MICROSOFT_TOKEN_DINAMICO'], 'msg' => 'Token encontrado en la base de datos'];
        }
    }
    public function syncUserMicrosoft($id_ies, $sigiId, $dni, $email, $nombres, $apellidos, $passwordPlano, $programa_estudios, $tipo_usuario, $estado, $MICROSOFT_CLIENT_ID, $MICROSOFT_CLIENT_SECRET, $MICROSOFT_TENANT_ID, $MICROSOFT_SKU_ID_DOCENTE, $MICROSOFT_SKU_ID_ESTUDIANTE)
    {
        $token = $this->getToken($id_ies);
        $respuesta = [];
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
                'displayName' => $nombres . ' ' . $apellidos,
                'mailNickname' => $dni,
                'userPrincipalName' => $email,
                'surname' => $apellidos,
                'givenName' => $nombres,
                'jobTitle' => $tipo_usuario,
                'departament' => $programa_estudios,
                'preferredLanguage' => 'es-ES',
                'usageLocation' => 'PE',
            ];
            if ($passwordPlano != null) {
                $userPayload['passwordProfile'] = [
                    'forceChangePasswordNextSignIn' => false,
                    'password' => $passwordPlano
                ];
            }
            if ($passwordPlano == null && $id_microsoft == null) {
                $parteAleatoria = bin2hex(random_bytes(6)); // Esta es la que enviaremos a Moodle
                $passforMicrosoft = 'Sigi.' . $parteAleatoria;
                $userPayload['passwordProfile'] = [
                    'forceChangePasswordNextSignIn' => false,
                    'password' => $passforMicrosoft
                ];
            }
            if ($id_microsoft && strlen($id_microsoft) > 10) {
                $url = "https://graph.microsoft.com/v1.0/users/" . $id_microsoft;
                $method = "PATCH";
            } else {
                $url = "https://graph.microsoft.com/v1.0/users/";
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
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userPayload));
            } else {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userPayload));
            }
            $raw_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = json_decode($raw_response, true);
            if ($id_microsoft && strlen($id_microsoft) > 10) {
                if ($http_code != 204) {
                    return ['success' => false, 'details' => $data['error']['message']];
                } else {
                    $license = $this->assignLicenseMicrosoft($id_microsoft, $skuId, $id_ies);
                    return ['success' => true, 'id_microsoft' => $data['id'], 'license' => $license];
                }
            } else {
                if ($http_code != 201) {
                    return ['success' => false, 'details' => $data['error']['message']];
                } else {
                    $license = $this->assignLicenseMicrosoft($data['id'], $skuId, $id_ies);
                    return ['success' => true, 'id_microsoft' => $data['id'], 'license' => $license];
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
            // convertir a string
            $params = [
                '$filter' => "mail eq '$email'",
                '$select' => 'id,displayName,mail'
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
            if ($http_code != 200) {
                return ['success' => false, 'details' => $data['error']['message']];
            } else {
                if (isset($data['value'][0])) {
                    return ['success' => true, 'user' => $data['value'][0]];
                } else {
                    return ['success' => false, 'user' => null];
                }
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
            if ($http_code != 200) {
                return ['success' => false, 'details' => $data['error']['message']];
            } else {
                return ['success' => true, 'details' => $data];
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
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = json_decode($raw_response, true);
            if ($http_code != 200) {
                return ['success' => false, 'details' => $data['error']['message']];
            } else {
                return ['success' => true, 'details' => $data];
            }
        } else {
            return ['success' => false, 'details' => $token['msg']];
        }
    }
}
