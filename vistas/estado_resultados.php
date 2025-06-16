<div class="container mt-5">
  <h1 class="title is-3">Captura de Estado de Resultados</h1>

  <?php
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/FinanzasProyecto/');
    include 'inc/head.php';
    include 'inc/db.php';

    // Obtener empresas
    $empresas = $conn->query("SELECT id, nombre FROM empresas")->fetchAll();

    // Variables para periodos según empresa seleccionada
    $periodos = [];
    $empresa_id = null;

    if (isset($_POST['empresa_id'])) {
      $empresa_id = $_POST['empresa_id'];
      // Obtener periodos para la empresa seleccionada
      $stmt = $conn->prepare("SELECT id, fecha_inicio, fecha_fin FROM periodos WHERE empresa_id = ? ORDER BY fecha_inicio ASC");
      $stmt->execute([$empresa_id]);
      $periodos = $stmt->fetchAll();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_resultados'])) {
      $empresa_id = $_POST['empresa_id'];
      $cuentas = $_POST['cuenta'];
      $tipos = $_POST['tipo'];
      
      try {
          $conn->beginTransaction();
          
          // Eliminar registros anteriores
          $conn->prepare("DELETE FROM cuentas_resultados WHERE periodo_id IN 
                        (SELECT id FROM periodos WHERE empresa_id = ?)")
              ->execute([$empresa_id]);
          
          // Preparar la inserción de nuevas cuentas
          $stmt = $conn->prepare("INSERT INTO cuentas_resultados 
                                (periodo_id, cuenta_id, tipo, valor) 
                                VALUES (?, ?, ?, ?)");
          
          foreach ($cuentas as $index => $cuenta_nombre) {
              if (!empty($cuenta_nombre)) {
                  // 1. Buscar si la cuenta ya existe en el catálogo
                  $stmt_find = $conn->prepare("SELECT id FROM catalogo_cuentas 
                                          WHERE nombre = ? AND reporte = 'resultados' LIMIT 1");
                  $stmt_find->execute([$cuenta_nombre]);
                  $cuenta_id = $stmt_find->fetchColumn();
                  
                  // 2. Si no existe, crearla y asignarle un nuevo orden
                  if (!$cuenta_id) {
                      // Obtener el máximo orden actual para el reporte de resultados
                      $max_orden = $conn->query("SELECT MAX(orden) FROM catalogo_cuentas WHERE reporte = 'resultados'")->fetchColumn();
                      $nuevo_orden = ($max_orden === null) ? 0 : $max_orden + 1;
                      
                      // Insertar la nueva cuenta con el orden calculado
                      $stmt_create_cuenta = $conn->prepare("INSERT INTO catalogo_cuentas 
                                    (nombre, tipo, reporte, orden) 
                                    VALUES (?, ?, 'resultados', ?)");
                      $stmt_create_cuenta->execute([$cuenta_nombre, $tipos[$index], $nuevo_orden]);
                      $cuenta_id = $conn->lastInsertId();
                  }
                  
                  // 3. Insertar los valores para cada periodo
                  foreach ($periodos as $periodo) {
                      $valor_key = 'valor_'.$periodo['id'].'_'.$index;
                      if (isset($_POST[$valor_key]) && $_POST[$valor_key] !== '') {
                          $stmt->execute([
                              $periodo['id'], 
                              $cuenta_id, 
                              $tipos[$index], 
                              $_POST[$valor_key]
                          ]);
                      }
                  }
              }
          }
          
          $conn->commit();
          echo "<div class='notification is-success'>Estado de Resultados guardado exitosamente.</div>";
      } catch(PDOException $e) {
          $conn->rollBack();
          echo "<div class='notification is-danger'>Error al guardar: " . $e->getMessage() . "</div>";
      }
    }
  ?>

  <form method="post" class="form-wide">
    <div class="field">
      <label class="label">Seleccionar Empresa</label>
      <div class="select">
        <select name="empresa_id" onchange="this.form.submit()" required>
          <option value="">Seleccione una empresa...</option>
          <?php foreach ($empresas as $empresa): ?>
            <option value="<?= $empresa['id'] ?>" <?= (isset($empresa_id) && $empresa_id == $empresa['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($empresa['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if (!empty($periodos)): ?>
      <h2 class="subtitle is-5 mt-4">Cuentas del Estado de Resultados</h2>

      <div class="table-container" style="overflow-x: auto;">
        <table class="table is-bordered is-fullwidth" id="tablaCuentas">
          <thead>
            <tr>
              <th style="width: 25%">Cuenta</th>
              <th style="width: 10%">Tipo</th>
              <?php foreach ($periodos as $periodo): 
                // Extraer solo el año de la fecha de inicio
                $year = date('Y', strtotime($periodo['fecha_inicio']));
              ?>
                <th style="width: 12%;"><?= htmlspecialchars($year) ?></th>
              <?php endforeach; ?>
              <th style="width: 5%">Eliminar</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><input class="input" type="text" name="cuenta[]" placeholder="Ventas, Gastos, etc." required></td>
              <td>
                <div class="select is-fullwidth">
                  <select name="tipo[]" required>
                    <option value="ingreso">Ingreso</option>
                    <option value="costo">Costo</option>
                    <option value="gasto">Gasto</option>
                  </select>
                </div>
              </td>
              <?php foreach ($periodos as $periodo): ?>
                <td>
                  <input class="input" type="number" step="0.01" name="valor_<?= $periodo['id'] ?>_0" value="" style="min-width: 80px;">
                </td>
              <?php endforeach; ?>
              <td>
                <button type="button" id="BotonEliminar" class="button is-danger is-small" onclick="eliminarFila(this)" title="Eliminar fila">
                  ✖
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="field">
        <button type="button" id="BotonAgregar" class="button is-link is-light" onclick="agregarFila()">+ Añadir fila</button>
      </div>

      <div class="field mt-4">
        <button type="submit" name="guardar_resultados" class="button is-primary">Guardar Estado de Resultados</button>
      </div>
    <?php endif; ?>
  </form>
</div>

<script>
  function agregarFila() {
    const tabla = document.getElementById("tablaCuentas").getElementsByTagName('tbody')[0];
    const filas = tabla.getElementsByTagName('tr');
    const nuevaFila = filas[0].cloneNode(true);
    const nuevoIndice = filas.length;

    // Limpiar valores de inputs
    nuevaFila.querySelector('input[name="cuenta[]"]').value = "";
    nuevaFila.querySelector('select[name="tipo[]"]').value = "ingreso";

    // Actualizar nombres de los campos de valor
    const inputsValor = nuevaFila.querySelectorAll('input[type="number"]');
    inputsValor.forEach(input => {
      const nameParts = input.name.split('_');
      if (nameParts.length === 3) {
        input.name = `valor_${nameParts[1]}_${nuevoIndice}`;
        input.value = "";
      }
    });

    tabla.appendChild(nuevaFila);
  }

  function eliminarFila(boton) {
    const fila = boton.closest("tr");
    const tabla = fila.parentNode;
    if (tabla.rows.length > 1) {
      fila.remove();
    }
  }
</script>