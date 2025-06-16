<?php

function getEmpresas($conn) {
    return $conn->query("SELECT id, nombre FROM empresas ORDER BY nombre")->fetchAll();
}

function getPeriodos($conn, $empresa_id = null) {
    $sql = "SELECT id FROM periodos";   
    if ($empresa_id) {
        $sql .= " WHERE empresa_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$empresa_id]);
        return $stmt->fetchAll();
    }
    return $conn->query($sql)->fetchAll();
}


function calcularTotales($datos) {
    $totales = [];
    foreach ($datos as $cuenta) {
        $tipo = $cuenta['tipo'];
        if (!isset($totales[$tipo])) {
            $totales[$tipo] = 0;
        }
        $totales[$tipo] += $cuenta['valor'];
    }
    return $totales;
}

?>