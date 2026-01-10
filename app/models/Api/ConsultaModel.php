<?php

namespace App\Models\Api;

use Core\Model;
use PDO;

class ConsultaModel extends Model
{
    /**
     * 1. GESTIÓN DE TOKENS (Round Robin)
     * Obtiene el token que no se ha usado por más tiempo de nuestra "bolsa" de tokens.
     */
    public function obtenerSiguienteToken()
    {
        // Seleccionamos el activo con fecha más antigua (o nula) para rotarlos equitativamente
        $sql = "SELECT id, token, url 
                FROM config_api_tokens 
                WHERE estado = 1 
                ORDER BY ultimo_uso_at ASC 
                LIMIT 1";

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute();
        $res = $stmt->fetch();

        return $res; // Retorna array ['id' => X, 'token' => '...', 'url' => '...'] o false
    }

    /**
     * Actualiza la fecha de uso del token para mandarlo al "final de la cola".
     */
    public function actualizarUsoToken($idToken)
    {
        $sql = "UPDATE config_api_tokens 
                SET usos_totales = usos_totales + 1, 
                    ultimo_uso_at = NOW() 
                WHERE id = :id";

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute([':id' => $idToken]);
    }

    /**
     * 2. VERIFICACIÓN DE CUOTA DE CLIENTE (IES)
     * Revisa si la institución tiene un plan activo y saldo disponible.
     */
    public function verificarCuotaCliente($id_ies, $endpoint)
    {
        // Si es Admin Global (null), permitimos todo (opcional, depende de tu lógica)
        if (empty($id_ies))
            return false;

        $db = self::getDB();

        // PASO A: Obtener el límite del plan activo
        // Buscamos en 'subscriptions' unida con 'planes'
        // Validamos que el estado sea 'activa' y que la fecha actual esté dentro del rango
        $sqlPlan = "SELECT p.limite_reniec 
                    FROM subscriptions s
                    INNER JOIN planes p ON s.id_plan = p.id
                    WHERE s.id_ies = :id_ies 
                      AND s.estado = 'activa' 
                      AND CURDATE() BETWEEN s.inicia AND s.vence
                    LIMIT 1";

        $stmt = $db->prepare($sqlPlan);
        $stmt->execute([':id_ies' => $id_ies]);
        $plan = $stmt->fetch();

        // Si no hay plan activo o venció, bloqueamos acceso
        if (!$plan) {
            return false;
        }

        $limitePermitido = intval($plan['limite_reniec']);

        // PASO B: Calcular consumo actual del mes
        $periodo = date('Ym'); // Formato de tu tabla usage_counters (ej: 202501)

        $sqlUso = "SELECT requests FROM usage_counters 
                   WHERE id_ies = :id_ies 
                     AND periodo_aaaamm = :periodo 
                     AND endpoint = '$endpoint'
                   LIMIT 1";

        $stmt = $db->prepare($sqlUso);
        $stmt->execute([':id_ies' => $id_ies, ':periodo' => $periodo]);
        $uso = $stmt->fetch();

        $consumoActual = $uso ? intval($uso['requests']) : 0;

        // PASO C: Comparar
        // Retorna TRUE si tiene saldo, FALSE si excedió
        return $consumoActual < $limitePermitido;
    }

    /**
     * 3. REGISTRO DE CONSUMO
     * Incrementa el contador para la facturación a fin de mes.
     */
    public function registrarConsumoCliente($id_ies)
    {
        $periodo = date('Ym');
        $endpoint = 'consulta_externa'; // Identificador fijo para este servicio

        // Usamos ON DUPLICATE KEY UPDATE para insertar o sumar en una sola consulta atómica
        $sql = "INSERT INTO usage_counters (id_ies, periodo_aaaamm, endpoint, requests, created_at)
                VALUES (:id_ies, :periodo, :endpoint, 1, NOW())
                ON DUPLICATE KEY UPDATE requests = requests + 1, updated_at = NOW()";

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute([
            ':id_ies' => $id_ies,
            ':periodo' => $periodo,
            ':endpoint' => $endpoint
        ]);
    }


    /**
     * Búsqueda paginada en ESCALE
     */
    public function buscarColegiosLocal($termino, $limit, $offset, $departamento = '', $provincia = '', $distrito = '')
    {
        $db = self::getDB();
        $term = "%{$termino}%";

        // Campos solicitados para la búsqueda
        $where = "WHERE CodigoModular LIKE :t1 
                     OR CEN_EDU LIKE :t2 ";

        if ($departamento != '') {
            $where .= "AND D_DPTO = :t3";
        } else {
            $where .= "AND D_DPTO LIKE :t3";
        }
        if ($provincia != '') {
            $where .= "AND D_PROV = :t4";
        } else {
            $where .= "AND D_PROV LIKE :t4";
        }
        if ($distrito != '') {
            $where .= "AND D_DIST = :t5";
        } else {
            $where .= "AND D_DIST LIKE :t5";
        }

        // 1. Obtener Total de Registros (para paginación)
        $sqlCount = "SELECT COUNT(*) as total FROM escale_colegios $where";
        $stmtCount = $db->prepare($sqlCount);
        // Bind de parámetros (repetimos la variable para cada ? o placeholder)
        $stmtCount->execute([
            ':t1' => $term,
            ':t2' => $term,
            ':t3' => $departamento,
            ':t4' => $provincia,
            ':t5' => $distrito
        ]);
        $total = $stmtCount->fetchColumn();

        // 2. Obtener Data Paginada
        $sqlData = "SELECT * 
                    FROM escale_colegios 
                    $where 
                    ORDER BY CEN_EDU ASC 
                    LIMIT $limit OFFSET $offset";

        $stmt = $db->prepare($sqlData);
        $stmt->execute([
            ':t1' => $term,
            ':t2' => $term,
            ':t3' => $departamento,
            ':t4' => $provincia,
            ':t5' => $distrito
        ]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => (int)$total,
            'items' => $items
        ];
    }

    public function departamentos()
    {
        $db = self::getDB();

        // 1. Obtener Total de Registros (para paginación)
        $sqlCount = "SELECT COUNT(DISTINCT D_DPTO) as total FROM escale_colegios";
        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute();
        $total = $stmtCount->fetchColumn();

        // 2. Obtener Data Paginada
        $sqlData = "SELECT DISTINCT D_DPTO FROM escale_colegios ORDER BY D_DPTO ASC";
        $stmt = $db->prepare($sqlData);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => (int)$total,
            'items' => $items
        ];
    }
    public function provincias($departamento)
    {
        $db = self::getDB();

        // 1. Obtener Total de Registros (para paginación)
        $sqlCount = "SELECT COUNT(DISTINCT D_PROV) as total FROM escale_colegios WHERE D_DPTO = :departamento";
        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute([':departamento' => $departamento]);
        $total = $stmtCount->fetchColumn();

        // 2. Obtener Data Paginada
        $sqlData = "SELECT DISTINCT D_PROV FROM escale_colegios WHERE D_DPTO = :departamento ORDER BY D_PROV ASC";
        $stmt = $db->prepare($sqlData);
        $stmt->execute([':departamento' => $departamento]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => (int)$total,
            'items' => $items
        ];
    }
    public function distritos($departamento, $provincia)
    {
        $db = self::getDB();

        // 1. Obtener Total de Registros (para paginación)
        $sqlCount = "SELECT COUNT(DISTINCT D_DIST) as total FROM escale_colegios WHERE D_DPTO = :departamento AND D_PROV = :provincia";
        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute([
            ':departamento' => $departamento,
            ':provincia' => $provincia
        ]);
        $total = $stmtCount->fetchColumn();

        // 2. Obtener Data Paginada
        $sqlData = "SELECT DISTINCT D_DIST FROM escale_colegios WHERE D_DPTO = :departamento AND D_PROV = :provincia ORDER BY D_DIST ASC";
        $stmt = $db->prepare($sqlData);
        $stmt->execute([
            ':departamento' => $departamento,
            ':provincia' => $provincia
        ]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'total' => (int)$total,
            'items' => $items
        ];
    }
}
