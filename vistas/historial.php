<div class="container mt-5">
  <h1 class="title is-3">Historial Financiero</h1>

  <?php
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/FinanzasProyecto/');
    include 'inc/head.php';
    include 'inc/db.php';
    
    $empresas = $conn->query("SELECT id, nombre FROM empresas")->fetchAll();
    $empresa_id = $_POST['empresa_id'] ?? null;
    
    // Se inicializa la variable $periodos como un array vacío.
    $periodos = []; 

    // Esto asegura que $periodos esté disponible tanto para el bloque de guardado como para el de visualización.
    if ($empresa_id) {
        $stmt_periodos = $conn->prepare("SELECT id, fecha_inicio, fecha_fin FROM periodos WHERE empresa_id = ? ORDER BY fecha_inicio ASC");
        $stmt_periodos->execute([$empresa_id]);
        $periodos = $stmt_periodos->fetchAll();
    }
    
    // Procesar guardado de cambios
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_cambios'])) {
        // Ahora el bloque de guardado puede usar la variable $periodos sin error.
        try {
            $conn->beginTransaction();
            
            // Procesar balance general
            if (isset($_POST['cuenta_balance'])) {
                $stmt_delete = $conn->prepare("DELETE FROM cuentas_balance WHERE periodo_id IN (SELECT id FROM periodos WHERE empresa_id = ?)");
                $stmt_delete->execute([$empresa_id]);
                
                $stmt_insert = $conn->prepare("INSERT INTO cuentas_balance (periodo_id, cuenta_id, tipo, valor, orden) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($_POST['cuenta_balance'] as $index => $cuenta_nombre) {
                    if (!empty($cuenta_nombre)) {
                        // Buscar o crear la cuenta en el catálogo
                        $cuenta_id = $conn->query("SELECT id FROM catalogo_cuentas WHERE nombre = '".addslashes($cuenta_nombre)."' AND reporte = 'balance' LIMIT 1")
                                        ->fetchColumn();
                        
                        if (!$cuenta_id) {
                            $conn->prepare("INSERT INTO catalogo_cuentas (nombre, tipo, reporte, orden) VALUES (?, ?, 'balance', ?)")
                                ->execute([$cuenta_nombre, $_POST['tipo_balance'][$index], $index]);
                            $cuenta_id = $conn->lastInsertId();
                        } else {
                            // Actualizar el orden si la cuenta ya existe
                            $conn->prepare("UPDATE catalogo_cuentas SET orden = ? WHERE id = ?")
                                ->execute([$index, $cuenta_id]);
                        }
                        
                        foreach ($periodos as $periodo) {
                            $valor_key = 'valor_balance_'.$periodo['id'].'_'.$index;
                            if (isset($_POST[$valor_key])) {
                                $stmt_insert->execute([
                                    $periodo['id'], 
                                    $cuenta_id, 
                                    $_POST['tipo_balance'][$index], 
                                    $_POST[$valor_key],
                                    $index // Guardar el orden
                                ]);
                            }
                        }
                    }
                }
            }

            // Procesar estado de resultados
            if (isset($_POST['cuenta_resultados'])) {
                $stmt_delete = $conn->prepare("DELETE FROM cuentas_resultados WHERE periodo_id IN (SELECT id FROM periodos WHERE empresa_id = ?)");
                $stmt_delete->execute([$empresa_id]);
                
                $stmt_insert = $conn->prepare("INSERT INTO cuentas_resultados (periodo_id, cuenta_id, tipo, valor) VALUES (?, ?, ?, ?)");
                
                foreach ($_POST['cuenta_resultados'] as $index => $cuenta_nombre) {
                    if (!empty($cuenta_nombre)) {
                        // Buscar o crear la cuenta en el catálogo
                        $cuenta_id = $conn->query("SELECT id FROM catalogo_cuentas WHERE nombre = '".addslashes($cuenta_nombre)."' AND reporte = 'resultados' LIMIT 1")
                                        ->fetchColumn();
                        
                        if (!$cuenta_id) {
                            $conn->prepare("INSERT INTO catalogo_cuentas (nombre, tipo, reporte) VALUES (?, ?, 'resultados')")
                                ->execute([$cuenta_nombre, $_POST['tipo_resultados'][$index]]);
                            $cuenta_id = $conn->lastInsertId();
                        }
                        
                        foreach ($periodos as $periodo) {
                            $valor_key = 'valor_resultados_'.$periodo['id'].'_'.$index;
                            if (isset($_POST[$valor_key])) {
                                $stmt_insert->execute([
                                    $periodo['id'], 
                                    $cuenta_id, 
                                    $_POST['tipo_resultados'][$index], 
                                    $_POST[$valor_key]
                                ]);
                            }
                        }
                    }
                }
            }
            
            $conn->commit();
            echo "<div class='notification is-success'>Cambios guardados exitosamente.</div>";
        } catch(PDOException $e) {
            $conn->rollBack();
            echo "<div class='notification is-danger'>Error al guardar cambios: " . $e->getMessage() . "</div>";
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
            <option value="<?= $empresa['id'] ?>" <?= ($empresa_id == $empresa['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($empresa['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </form>

  <?php if ($empresa_id): ?>

    <?php if (!empty($periodos)): ?>
      <style>
        .financial-table {
          width: 100%;
          table-layout: fixed;
        }
        .financial-table th:first-child,
        .financial-table td:first-child {
          width: 25%;
        }
        .financial-table th:nth-child(2),
        .financial-table td:nth-child(2) {
          width: 10%;
        }
        .financial-table th:not(:first-child):not(:nth-child(2)),
        .financial-table td:not(:first-child):not(:nth-child(2)) {
          width: calc(65% / <?= count($periodos) ?>);
          text-align: right;
        }
        .sortable-handle {
          cursor: move;
          color: #666;
        }
      </style>

      <form method="post">
        <input type="hidden" name="empresa_id" value="<?= $empresa_id ?>">
        
        <div class="box mt-5">
          <div class="level">
            <div class="level-left">
              <h2 class="subtitle is-5">Balance General</h2>
            </div>
            <div class="BotonAgregar">
              <button type="button" id="BotonAgregar" class="button is-small is-link" onclick="agregarFila('tbodyBalance', 'balance')">
                + Agregar Cuenta
              </button>
            </div>
          </div>
          
          <?php
            // La consulta para obtener los datos del balance se mantiene igual.
            $balance = $conn->prepare("
              SELECT cc.nombre as cuenta, p.id as periodo_id, cb.valor, cc.tipo, cc.orden
              FROM cuentas_balance cb
              JOIN catalogo_cuentas cc ON cb.cuenta_id = cc.id
              JOIN periodos p ON cb.periodo_id = p.id
              WHERE p.empresa_id = ?
              ORDER BY cc.tipo, cc.orden, cc.nombre
            ");
            $balance->execute([$empresa_id]);
            $balance_data = $balance->fetchAll();
          ?>

          <?php if (!empty($balance_data)): ?>
            <div class="table-container" style="overflow-x: auto;">
              <table class="table is-bordered is-fullwidth financial-table" id="tablaBalance">
                <thead>
                  <tr>
                    <th style="width: 23%">Cuenta</th>
                    <th style="width: 10%">Tipo</th>
                    <?php foreach ($periodos as $periodo): ?>
                      <th style="width: 12%"><?= date('Y', strtotime($periodo['fecha_inicio'])) ?></th>
                    <?php endforeach; ?>
                    <th style="width: 7%">Eliminar</th>
                  </tr>
                </thead>
                <tbody id="tbodyBalance">
                  <?php
                    $cuentas = [];
                    foreach ($balance_data as $row) {
                      $cuentas[$row['cuenta']]['tipo'] = $row['tipo'];
                      $cuentas[$row['cuenta']]['valores'][$row['periodo_id']] = $row['valor'];
                    }
                    
                    // Usamos array_keys para obtener los índices numéricos correctos para el formulario
                    $cuenta_keys = array_keys($cuentas);
                    foreach ($cuenta_keys as $i => $cuenta_nombre):
                      $cuenta = $cuentas[$cuenta_nombre];
                  ?>
                    <tr>
                      <td>
                        <input type="text" class="input" name="cuenta_balance[]" value="<?= htmlspecialchars($cuenta_nombre) ?>" required>
                      </td>
                      <td>
                        <div class="select is-fullwidth">
                          <select name="tipo_balance[]" required>
                            <option value="activo" <?= ($cuenta['tipo'] == 'activo') ? 'selected' : '' ?>>Activo</option>
                            <option value="pasivo" <?= ($cuenta['tipo'] == 'pasivo') ? 'selected' : '' ?>>Pasivo</option>
                            <option value="capital" <?= ($cuenta['tipo'] == 'capital') ? 'selected' : '' ?>>Capital</option>
                          </select>
                        </div>
                      </td>
                      <?php foreach ($periodos as $periodo): ?>
                        <td>
                          <input class="input" type="number" step="0.01" 
                                 name="valor_balance_<?= $periodo['id'] ?>_<?= $i ?>" 
                                 value="<?= isset($cuenta['valores'][$periodo['id']]) ? $cuenta['valores'][$periodo['id']] : '' ?>" 
                                 style="min-width: 80px;">
                        </td>
                      <?php endforeach; ?>
                      <td>
                        <button type="button" id="BotonEliminar" class="button is-small is-danger" onclick="eliminarFila(this)">
                          ✖
                        </button>
                        <span class="sortable-handle">☰</span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p>No hay datos de Balance General para esta empresa.</p>
          <?php endif; ?>
        </div>

        <div class="box mt-5">
          <div class="level">
            <div class="level-left">
              <h2 class="subtitle is-5">Estado de Resultados</h2>
            </div>
            <div class="BotonAgregar">
              <button type="button" id="BotonAgregar" class="button is-small is-link" onclick="agregarFila('tbodyResultados', 'resultados')">
                + Agregar Cuenta
              </button>
            </div>
          </div>
          
          <?php
            $resultados = $conn->prepare("
              SELECT cc.nombre as cuenta, p.id as periodo_id, cr.valor, cc.tipo, cc.orden
              FROM cuentas_resultados cr
              JOIN catalogo_cuentas cc ON cr.cuenta_id = cc.id
              JOIN periodos p ON cr.periodo_id = p.id
              WHERE p.empresa_id = ?
              ORDER BY cc.tipo, cc.orden, cc.nombre
          ");
            $resultados->execute([$empresa_id]);
            $resultados_data = $resultados->fetchAll();
          ?>

          <?php if (!empty($resultados_data)): ?>
            <div class="table-container" style="overflow-x: auto;">
              <table class="table is-bordered is-fullwidth financial-table" id="tablaResultados">
                <thead>
                  <tr>
                    <th style="width: 23%">Cuenta</th>
                    <th style="width: 10%">Tipo</th>
                    <?php foreach ($periodos as $periodo): ?>
                      <th style="width: 12%;"><?= date('Y', strtotime($periodo['fecha_inicio'])) ?></th>
                    <?php endforeach; ?>
                    <th style="width: 7%">Eliminar</th>
                  </tr>
                </thead>
                <tbody id="tbodyResultados">
                  <?php
                    $cuentas = [];
                    foreach ($resultados_data as $row) {
                      $cuentas[$row['cuenta']]['tipo'] = $row['tipo'];
                      $cuentas[$row['cuenta']]['valores'][$row['periodo_id']] = $row['valor'];
                    }
                    
                    $cuenta_keys = array_keys($cuentas);
                    foreach ($cuenta_keys as $i => $cuenta_nombre):
                      $cuenta = $cuentas[$cuenta_nombre];
                  ?>
                    <tr>
                      <td>
                        <input type="text" class="input" name="cuenta_resultados[]" value="<?= htmlspecialchars($cuenta_nombre) ?>" required>
                      </td>
                      <td>
                        <div class="select is-fullwidth">
                          <select name="tipo_resultados[]" required>
                            <option value="ingreso" <?= ($cuenta['tipo'] == 'ingreso') ? 'selected' : '' ?>>Ingreso</option>
                            <option value="costo" <?= ($cuenta['tipo'] == 'costo') ? 'selected' : '' ?>>Costo</option>
                            <option value="gasto" <?= ($cuenta['tipo'] == 'gasto') ? 'selected' : '' ?>>Gasto</option>
                          </select>
                        </div>
                      </td>
                      <?php foreach ($periodos as $periodo): ?>
                        <td>
                          <input class="input" type="number" step="0.01" 
                                 name="valor_resultados_<?= $periodo['id'] ?>_<?= $i ?>" 
                                 value="<?= isset($cuenta['valores'][$periodo['id']]) ? $cuenta['valores'][$periodo['id']] : '' ?>" 
                                 style="min-width: 80px;">
                        </td>
                      <?php endforeach; ?>
                      <td>
                        <button type="button" id="BotonEliminar" class="button is-small is-danger" onclick="eliminarFila(this)">
                          ✖
                        </button>
                        <span class="sortable-handle">☰</span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p>No hay datos de Estado de Resultados para esta empresa.</p>
          <?php endif; ?>
        </div>

        <div class="field mt-4">
          <button type="submit" name="guardar_cambios" class="button is-primary">Guardar Cambios</button>
        </div>
      </form>

      <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
      <script>
        // 1. Pasar los periodos de PHP a JavaScript
        const periodos = <?= json_encode($periodos) ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const tbodyBalance = document.getElementById('tbodyBalance');
            if (tbodyBalance) {
                new Sortable(tbodyBalance, {
                    handle: '.sortable-handle',
                    animation: 150,
                    onEnd: function() {
                        actualizarIndices(tbodyBalance, 'balance');
                    }
                });
            }

            const tbodyResultados = document.getElementById('tbodyResultados');
            if (tbodyResultados) {
                new Sortable(tbodyResultados, {
                    handle: '.sortable-handle',
                    animation: 150,
                    onEnd: function() {
                        actualizarIndices(tbodyResultados, 'resultados');
                    }
                });
            }
        });
        
        // 2. Función corregida para actualizar los índices
        function actualizarIndices(tbody, tipo) {
            const rows = tbody.querySelectorAll('tr');
            rows.forEach((row, index) => {
                // Actualizar los índices en los nombres de los inputs de valor
                const valueInputs = row.querySelectorAll('input[type="number"]');
                valueInputs.forEach(input => {
                    const name = input.name;
                    // Expresión regular más específica para solo reemplazar el último número (índice)
                    input.name = name.replace(/_\d+$/, `_${index}`);
                });
            });
        }

        function agregarFila(tbodyId, tipo) {
          const tbody = document.getElementById(tbodyId);
          const nuevoIndice = tbody.rows.length;
          const nuevaFila = document.createElement('tr');

          let celdas = '';

          if (tipo === 'balance') {
              celdas = `
                <td><input type="text" class="input" name="cuenta_balance[]" required></td>
                <td>
                  <div class="select is-fullwidth">
                    <select name="tipo_balance[]" required>
                      <option value="activo">Activo</option>
                      <option value="pasivo">Pasivo</option>
                      <option value="capital">Capital</option>
                    </select>
                  </div>
                </td>
              `;
              periodos.forEach(periodo => {
                  celdas += `<td><input class="input" type="number" step="0.01" name="valor_balance_${periodo.id}_${nuevoIndice}" style="min-width: 80px;"></td>`;
              });
          } else { // tipo 'resultados'
              celdas = `
                <td><input type="text" class="input" name="cuenta_resultados[]" required></td>
                <td>
                  <div class="select is-fullwidth">
                    <select name="tipo_resultados[]" required>
                      <option value="ingreso">Ingreso</option>
                      <option value="costo">Costo</option>
                      <option value="gasto">Gasto</option>
                    </select>
                  </div>
                </td>
              `;
              periodos.forEach(periodo => {
                  celdas += `<td><input class="input" type="number" step="0.01" name="valor_resultados_${periodo.id}_${nuevoIndice}" style="min-width: 80px;"></td>`;
              });
          }

          celdas += '<td><button type="button" class="button is-small is-danger" onclick="eliminarFila(this)">✖</button><span class="sortable-handle">☰</span></td>';
          
          nuevaFila.innerHTML = celdas;
          tbody.appendChild(nuevaFila);
        }

        function eliminarFila(boton) {
          const fila = boton.closest('tr');
          const tbody = fila.parentElement;
          const tipo = tbody.id.includes('Balance') ? 'balance' : 'resultados';
          fila.remove();
          actualizarIndices(tbody, tipo); // Actualizar índices después de eliminar
        }
      </script>
    <?php endif; ?>
  <?php endif; ?>
</div>