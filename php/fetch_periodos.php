<?php
    header('Content-Type: application/json');
    include '../inc/db.php';

    if (!isset($_GET['empresa_id'])) {
        echo json_encode(['error' => 'No se proporcionó el ID de la empresa.']);
        exit;
    }

    $empresa_id = $_GET['empresa_id'];

    try {
        $sql = "SELECT id, fecha_inicio, fecha_fin FROM periodos WHERE empresa_id = ? ORDER BY fecha_inicio ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$empresa_id]);
        $periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($periodos);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Error al consultar la base de datos: ' . $e->getMessage()]);
    }
?>