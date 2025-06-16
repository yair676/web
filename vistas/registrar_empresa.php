<div class="container mt-5">
  <h1 class="title is-3">Registrar Nueva Empresa</h1>

  <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
      define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/FinanzasProyecto/');
      include 'inc/head.php';
      include 'inc/head.php';
      include 'inc/db.php';

      $nombre = $_POST['nombre'];
      $rfc = $_POST['rfc'];
      $direccion = $_POST['direccion'];
      $telefono = $_POST['telefono'];

      try {
        $sql = "INSERT INTO empresas (nombre, rfc, direccion, telefono) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nombre, $rfc, $direccion, $telefono]);
        
        echo "<div class='notification is-success'>Empresa registrada exitosamente.</div>";
      } catch(PDOException $e) {
        echo "<div class='notification is-danger'>Error al registrar empresa: " . $e->getMessage() . "</div>";
      }
    }
  ?>

  <div class="info-wrapper"> <div class="info">
      <form method="post">
        <div class="field">
          <label class="label">Nombre de la Empresa</label>
          <div class="control">
            <input class="input" type="text" name="nombre" required style="width: 300px;">
          </div>
        </div>

        <div class="field">
          <label class="label">RFC</label>
          <div class="control">
            <input class="input" type="text" name="rfc" required style="width: 300px;">
          </div>
        </div>

        <div class="field">
          <label class="label">Dirección</label>
          <div class="control">
            <input class="input" type="text" name="direccion" required style="width: 300px;">
          </div>
        </div>

        <div class="field">
          <label class="label">Telefono</label>
          <div class="control">
            <input class="input" type="text" name="telefono" required style="width: 300px;">
          </div>
        </div>

        <div class="field mt-4">
          <div class="control">
            <button type="submit" class="button is-primary">Registrar Empresa</button>
          </div>
        </div>
      </form>
    </div>

    <div class="info">
      <img src="./img/Mascotas_UV.png" alt="Mascotas de la UV" style="width: 560px; height: 560px;">
    </div>
  </div> </div>