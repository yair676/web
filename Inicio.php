<!DOCTYPE html>
<html lang="es">
<head>
    <?php include "./Inc/head.php"; ?>
</head>
    <body>
        <?php
        include "./Inc/menu.php";

        if (isset($_GET['page'])) {
            $pagina = $_GET['page'];
            $ruta = "vistas/" . $pagina . ".php";

            if (file_exists($ruta)) {
                include $ruta;
            } else {
                echo "<div class='notification is-danger'>La página '$pagina' no existe.</div>";
            }
        }

        include "./Inc/script.php";
        ?>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script> <script src="./ajax.js"></script>
    </body>
</html>
