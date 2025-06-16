<?php
    $host = 'localhost';
    $dbname = 'sistema_financiero';
    $username = 'root';
    $password = '';
    $port = 3307;

    try {
        $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // echo "✅ Conexión exitosa a la base de datos";
    } catch(PDOException $e) {
        die("❌ Error de conexión: " . $e->getMessage());
    }
?>
