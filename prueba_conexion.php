<?php
// Conexión con mysqli
$conexion = new mysqli("localhost", "root", "", "test", 3307);

// Verificar conexión
if ($conexion->connect_error) {
    die("❌ Conexión fallida: " . $conexion->connect_error);
}

$conexion->set_charset("utf8");
echo "✅ Conexión exitosa con mysqli";
?>
