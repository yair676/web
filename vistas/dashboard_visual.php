<div class="container mt-5">
  <h1 class="title is-3">Dashboard Financiero</h1>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/regression@2.0.1/dist/regression.min.js"></script>

    <?php
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/FinanzasProyecto/');
    include 'inc/head.php';
    include 'inc/db.php';

    // 1. Obtener TODAS las empresas con sus detalles completos
    $empresas = $conn->query("SELECT id, nombre, rfc, direccion, telefono FROM empresas")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener el ID de la empresa que se seleccionó en el formulario
    $empresa_id = $_POST['empresa_id'] ?? null;
    
    // 3. Preparar la variable que guardará la información de la empresa ELEGIDA
    $empresa_seleccionada = null; 

    // 4. Si se seleccionó un ID, buscar la empresa correspondiente en el array $empresas
    if ($empresa_id) {
        foreach ($empresas as $empresa_item) {
            if ($empresa_item['id'] == $empresa_id) {
                $empresa_seleccionada = $empresa_item; // ¡Aquí guardamos la empresa correcta!
                break; // Detenemos la búsqueda
            }
        }
    }

    // El resto de la lógica para obtener datos financieros ahora depende de si se encontró una empresa
    $valores_balance = [];
    $valores_resultados = [];
    $periodos = [];

    if ($empresa_seleccionada) { // Usamos la variable que ahora sí tiene datos
        $periodos = $conn->query("
            SELECT id, YEAR(fecha_inicio) as anio 
            FROM periodos 
            WHERE empresa_id = {$empresa_seleccionada['id']} 
            ORDER BY fecha_inicio
        ")->fetchAll();

        if (count($periodos) >= 2) {
            $periodo_ids = array_column($periodos, 'id');
            
            // Cuentas del balance general
            $cuentas_balance = $conn->query("
                SELECT DISTINCT cc.nombre as cuenta, cc.tipo as tipo
                FROM cuentas_balance cb
                JOIN catalogo_cuentas cc ON cb.cuenta_id = cc.id
                WHERE cb.periodo_id IN (" . implode(',', $periodo_ids) . ")
            ")->fetchAll();

            // Cuentas del estado de resultado
            $cuentas_resultados = $conn->query("
                SELECT DISTINCT cc.nombre as cuenta, cc.tipo as tipo
                FROM cuentas_resultados cr
                JOIN catalogo_cuentas cc ON cr.cuenta_id = cc.id
                WHERE cr.periodo_id IN (" . implode(',', $periodo_ids) . ")
            ")->fetchAll();

            // Procesar cuentas de balance
            foreach ($cuentas_balance as $cuenta) {
                $valores_balance[$cuenta['cuenta']] = ['tipo' => $cuenta['tipo'], 'valores' => []];
                $stmt = $conn->prepare("
                    SELECT cb.periodo_id, cb.valor 
                    FROM cuentas_balance cb
                    JOIN catalogo_cuentas cc ON cb.cuenta_id = cc.id
                    WHERE cc.nombre = ? AND cb.periodo_id IN (" . implode(',', $periodo_ids) . ")");
                $stmt->execute([$cuenta['cuenta']]);
                $balance_vals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                foreach ($periodo_ids as $pid) {
                    $valores_balance[$cuenta['cuenta']]['valores'][$pid] = $balance_vals[$pid] ?? 0;
                }
            }

            // Procesar cuentas de resultados
            foreach ($cuentas_resultados as $cuenta) {
                $valores_resultados[$cuenta['cuenta']] = ['tipo' => $cuenta['tipo'], 'valores' => []];
                $stmt = $conn->prepare("
                    SELECT cr.periodo_id, cr.valor 
                    FROM cuentas_resultados cr
                    JOIN catalogo_cuentas cc ON cr.cuenta_id = cc.id
                    WHERE cc.nombre = ? AND cr.periodo_id IN (" . implode(',', $periodo_ids) . ")");
                $stmt->execute([$cuenta['cuenta']]);
                $resultados_vals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                foreach ($periodo_ids as $pid) {
                    $valores_resultados[$cuenta['cuenta']]['valores'][$pid] = $resultados_vals[$pid] ?? 0;
                }
            }
        }
    }
?>

    <form method="post" class="form-wide">
        <div class="field">
            <label class="label">Seleccionar Empresa</label>
            <div class="control">
                <div class="select">
                    <select name="empresa_id" required onchange="this.form.submit()">
                        <option value="">Seleccione una empresa...</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?= $empresa['id'] ?>" <?= $empresa_id == $empresa['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($empresa['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </form>

  <div id="dashboard-content">
    <?php if ($empresa_seleccionada && count($periodos) >= 2): ?>
        <div class="box mt-4">
          <h2 class="title is-4"><?= htmlspecialchars($empresa_seleccionada['nombre']) ?></h2>
          <div class="content">
              <ul>
                  <li><strong>RFC:</strong> <?= htmlspecialchars($empresa_seleccionada['rfc']) ?></li>
                  <li><strong>Dirección:</strong> <?= htmlspecialchars($empresa_seleccionada['direccion']) ?></li>
                  <li><strong>Teléfono:</strong> <?= htmlspecialchars($empresa_seleccionada['telefono']) ?></li>
              </ul>
          </div>
        </div>


        <div class="box mt-5">
          <h2 class="title is-4 has-text-centered"> Balance General y Estado De Resultado</h2>
            <h2 class="subtitle is-5">Balance General</h2>
            <table class="table is-bordered is-fullwidth">
                <thead>
                    <tr>
                        <th>Cuenta</th>
                        <?php foreach ($periodos as $p): ?>
                            <th><?= htmlspecialchars($p['anio']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($valores_balance as $cuenta => $data): ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php foreach ($periodos as $p): ?>
                                <td>$<?= number_format($data['valores'][$p['id']] ?? 0, 2) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h2 class="subtitle is-5 mt-5">Estado de Resultados</h2>
            <table class="table is-bordered is-fullwidth">
                <thead>
                    <tr>
                        <th>Cuenta</th>
                        <?php foreach ($periodos as $p): ?>
                            <th><?= htmlspecialchars($p['anio']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($valores_resultados as $cuenta => $data): ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php foreach ($periodos as $p): ?>
                                <td>$<?= number_format($data['valores'][$p['id']] ?? 0, 2) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="box mt-5">
          <h2 class="title is-4 has-text-centered">Analisis Horizontal</h2>
            <h2 class="subtitle is-5 mt-5">Variación Absoluta (Balance General)</h2>
              <table class="table is-bordered is-fullwidth">
                  <thead>
                      <tr>
                          <th>Cuenta</th>
                          <?php for ($i = 1; $i < count($periodos); $i++): ?>
                              <th>
                                  <?= $periodos[$i-1]['anio'] ?> → <?= $periodos[$i]['anio'] ?>
                              </th>
                          <?php endfor; ?>
                      </tr>
                  </thead>
                  <tbody>
                      <?php foreach ($valores_balance as $cuenta => $data): ?>
                          <tr>
                              <td><?= htmlspecialchars($cuenta) ?></td>
                              <?php for ($i = 1; $i < count($periodos); $i++): ?>
                                  <?php
                                  // Se accede a $data['valores'] para obtener los montos
                                  $anterior = $data['valores'][$periodos[$i-1]['id']] ?? 0;
                                  $actual = $data['valores'][$periodos[$i]['id']] ?? 0;
                                  $cambio = $actual - $anterior;
                                  ?>
                                  <td class="<?= $cambio < 0 ? 'has-text-danger' : 'has-text-success' ?>">
                                      $<?= number_format($cambio, 2) ?>
                                  </td>
                              <?php endfor; ?>
                          </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>

              <h2 class="subtitle is-5 mt-5">Variación Porcentual (Balance General) (%)</h2>
              <table class="table is-bordered is-fullwidth">
                  <thead>
                      <tr>
                          <th>Cuenta</th>
                          <?php for ($i = 1; $i < count($periodos); $i++): ?>
                              <th>
                                  <?= $periodos[$i-1]['anio'] ?> → <?= $periodos[$i]['anio'] ?>
                              </th>
                          <?php endfor; ?>
                      </tr>
                  </thead>
                    <tbody>
                      <?php foreach ($valores_balance as $cuenta => $data): ?>
                          <tr>
                              <td><?= htmlspecialchars($cuenta) ?></td>
                              <?php for ($i = 1; $i < count($periodos); $i++): ?>
                                  <?php
                                  // Se accede a $data['valores'] para obtener los montos
                                  $anterior = $data['valores'][$periodos[$i-1]['id']] ?? 0;
                                  $actual = $data['valores'][$periodos[$i]['id']] ?? 0;
                                  $cambio = $anterior != 0 ? (($actual - $anterior) / $anterior * 100) : 0;
                                  ?>
                                  <td class="<?= $cambio < 0 ? 'has-text-danger' : 'has-text-success' ?>">
                                      <?= number_format($cambio, 2) ?>%
                                  </td>
                              <?php endfor; ?>
                          </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
              
              <h2 class="subtitle is-5 mt-5">Variación Absoluta (Estado de Resultados)</h2>
              <table class="table is-bordered is-fullwidth">
                  <thead>
                      <tr>
                          <th>Cuenta</th>
                          <?php for ($i = 1; $i < count($periodos); $i++): ?>
                              <th>
                                  <?= $periodos[$i-1]['anio'] ?> → <?= $periodos[$i]['anio'] ?>
                              </th>
                          <?php endfor; ?>
                      </tr>
                  </thead>
                  <tbody>
                      <?php foreach ($valores_resultados as $cuenta => $data): ?>
                          <tr>
                              <td><?= htmlspecialchars($cuenta) ?></td>
                              <?php for ($i = 1; $i < count($periodos); $i++): ?>
                                  <?php
                                  // Se accede a $data['valores'] para obtener los montos
                                  $anterior = $data['valores'][$periodos[$i-1]['id']] ?? 0;
                                  $actual = $data['valores'][$periodos[$i]['id']] ?? 0;
                                  $cambio = $actual - $anterior;
                                  ?>
                                  <td class="<?= $cambio < 0 ? 'has-text-danger' : 'has-text-success' ?>">
                                      $<?= number_format($cambio, 2) ?>
                                  </td>
                              <?php endfor; ?>
                          </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>

              <h2 class="subtitle is-5 mt-5">Variación Porcentual (Estado de Resultados) (%)</h2>
              <table class="table is-bordered is-fullwidth">
                  <thead>
                      <tr>
                          <th>Cuenta</th>
                          <?php for ($i = 1; $i < count($periodos); $i++): ?>
                              <th>
                                  <?= $periodos[$i-1]['anio'] ?> → <?= $periodos[$i]['anio'] ?>
                              </th>
                          <?php endfor; ?>
                      </tr>
                  </thead>
                  <tbody>
                      <?php foreach ($valores_resultados as $cuenta => $data): ?>
                          <tr>
                              <td><?= htmlspecialchars($cuenta) ?></td>
                              <?php for ($i = 1; $i < count($periodos); $i++): ?>
                                  <?php
                                  // Se accede a $data['valores'] para obtener los montos
                                  $anterior = $data['valores'][$periodos[$i-1]['id']] ?? 0;
                                  $actual = $data['valores'][$periodos[$i]['id']] ?? 0;
                                  $cambio = $anterior != 0 ? (($actual - $anterior) / $anterior * 100) : 0;
                                  ?>
                                  <td class="<?= $cambio < 0 ? 'has-text-danger' : 'has-text-success' ?>">
                                      <?= number_format($cambio, 2) ?>%
                                  </td>
                              <?php endfor; ?>
                          </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
        </div>

        <div class="box mt-5">
          <h2 class="title is-4 has-text-centered">Analisis Veritical</h2>
            <h2 class="subtitle is-5 mt-5">Análisis Vertical - Balance General (%)</h2>
            <table class="table is-bordered is-fullwidth">
                <thead>
                    <tr>
                        <th>Cuenta</th>
                        <?php foreach ($periodos as $p): ?>
                            <th><?= htmlspecialchars($p['anio']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Obtener valores totales por periodo
                    $totales_activo = [];
                    $totales_pasivo_capital = [];
                    
                    foreach ($periodos as $p) {
                        $pid = $p['id'];
                        
                        // Buscar el valor de TOTAL ACTIVO para cada periodo
                        $total_activo = 0;
                        foreach ($valores_balance as $cuenta => $data) {
                            if ($cuenta == 'TOTAL ACTIVO') {
                                $total_activo = $data['valores'][$pid] ?? 0;
                                break;
                            }
                        }
                        $totales_activo[$pid] = $total_activo;
                        
                        // Buscar el valor de TOTAL PASIVO Y CAPITAL CONTABLE para cada periodo
                        $total_pasivo_capital = 0;
                        foreach ($valores_balance as $cuenta => $data) {
                            if ($cuenta == 'TOTAL PASIVO Y CAPITAL CONTABLE') {
                                $total_pasivo_capital = $data['valores'][$pid] ?? 0;
                                break;
                            }
                        }
                        $totales_pasivo_capital[$pid] = $total_pasivo_capital;
                    }
                    
                    // Mostrar cuentas de ACTIVO
                    foreach ($valores_balance as $cuenta => $data): 
                        if ($data['tipo'] == 'activo' && $cuenta != 'TOTAL ACTIVO'):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php foreach ($periodos as $p): 
                                $pid = $p['id'];
                                $valor = $data['valores'][$pid] ?? 0;
                                $porcentaje = $totales_activo[$pid] != 0 ? ($valor / $totales_activo[$pid] * 100) : 0;
                            ?>
                                <td class="<?= $porcentaje < 0 ? 'has-text-danger' : 'has-text-success' ?>">
                                    <?= number_format($porcentaje, 2) ?>%
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    
                    <!-- Total Activo -->
                    <tr>
                        <td><strong>TOTAL ACTIVO</strong></td>
                        <?php foreach ($periodos as $p): 
                            $pid = $p['id'];
                        ?>
                            <td><strong>100.00%</strong></td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <!-- Mostrar cuentas de PASIVO -->
                    <?php foreach ($valores_balance as $cuenta => $data): 
                        if ($data['tipo'] == 'pasivo' && $cuenta != 'TOTAL PASIVO'):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php foreach ($periodos as $p): 
                                $pid = $p['id'];
                                $valor = $data['valores'][$pid] ?? 0;
                                $porcentaje = $totales_pasivo_capital[$pid] != 0 ? ($valor / $totales_pasivo_capital[$pid] * 100) : 0;
                            ?>
                                <td class="<?= $porcentaje < 0 ? 'has-text-danger' : 'has-text-success' ?>">
                                    <?= number_format($porcentaje, 2) ?>%
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    
                    <!-- Mostrar cuentas de CAPITAL -->
                    <?php foreach ($valores_balance as $cuenta => $data): 
                        if ($data['tipo'] == 'capital' && $cuenta != 'CAPITAL CONTABLE' && $cuenta != 'TOTAL PASIVO Y CAPITAL CONTABLE'):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php foreach ($periodos as $p): 
                                $pid = $p['id'];
                                $valor = $data['valores'][$pid] ?? 0;
                                $porcentaje = $totales_pasivo_capital[$pid] != 0 ? ($valor / $totales_pasivo_capital[$pid] * 100) : 0;
                            ?>
                                <td class="<?= $porcentaje < 0 ? 'has-text-danger' : 'has-text-success' ?>">
                                    <?= number_format($porcentaje, 2) ?>%
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    
                    <!-- Total Pasivo + Capital -->
                    <tr>
                        <td><strong>TOTAL PASIVO + CAPITAL</strong></td>
                        <?php foreach ($periodos as $p): 
                            $pid = $p['id'];
                        ?>
                            <td><strong>100.00%</strong></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>

            <h2 class="subtitle is-5 mt-5">Análisis Vertical - Estado de Resultados (%)</h2>
            <table class="table is-bordered is-fullwidth">
                <thead>
                    <tr>
                        <th>Cuenta</th>
                        <?php foreach ($periodos as $p): ?>
                            <th><?= htmlspecialchars($p['anio']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Calcular total ventas por periodo (base para el análisis vertical)
                    $ventas_totales = [];
                    foreach ($periodos as $p) {
                        $pid = $p['id'];
                        $ventas_totales[$pid] = 0;
                        
                        // Buscar específicamente la cuenta "Ventas Netas"
                        foreach ($valores_resultados as $cuenta => $data) {
                            if (strtolower($cuenta) == 'ventas netas') {
                                $ventas_totales[$pid] = $data['valores'][$pid] ?? 0;
                                break;
                            }
                        }
                        
                        // Si no encuentra "Ventas Netas", usar el primer ingreso como alternativa
                        if ($ventas_totales[$pid] == 0) {
                            foreach ($valores_resultados as $cuenta => $data) {
                                if ($data['tipo'] == 'ingreso') {
                                    $ventas_totales[$pid] = $data['valores'][$pid] ?? 0;
                                    break;
                                }
                            }
                        }
                    }

                    
                    foreach ($valores_resultados as $cuenta => $data): ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php foreach ($periodos as $p): 
                                $pid = $p['id'];
                                $valor = $data['valores'][$pid] ?? 0;
                                $ventas = $ventas_totales[$pid];
                                $porcentaje = $ventas != 0 ? ($valor / $ventas * 100) : 0;
                            ?>
                                <td class="<?= $porcentaje < 0 ? 'has-text-danger' : 'has-text-success' ?>">
                                    <?= number_format($porcentaje, 2) ?>%
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="box mt-5">
          <h2 class="title is-4 has-text-centered">Razones Financieras</h2>

          <?php
          // --- Cálculo de Inventario Promedio ---
          // Este cálculo es necesario para las razones de actividad.
          $inventario_promedio_valores = [];
          foreach ($periodos as $index => $p) {
              $pid = $p['id'];
              // Ajuste para leer la estructura de datos del dashboard
              $inventario_actual = $valores_balance['Inventarios']['valores'][$pid] ?? 0;

              if ($index == 0) {
                  $inventario_promedio = $inventario_actual;
              } else {
                  $pid_anterior = $periodos[$index-1]['id'];
                  // Ajuste para leer la estructura de datos del dashboard
                  $inventario_anterior = $valores_balance['Inventarios']['valores'][$pid_anterior] ?? 0;
                  $inventario_promedio = ($inventario_anterior + $inventario_actual) / 2;
              }
              $inventario_promedio_valores[$pid] = $inventario_promedio;
          }
          ?>

          <h2 class="subtitle is-5 mt-4">Razones de Liquidez</h2>
          <table class="table is-bordered is-fullwidth">
              <thead>
                  <tr>
                      <th>Razón</th>
                      <th>Fórmula</th>
                      <?php foreach ($periodos as $p): ?>
                          <th><?= $p['anio'] ?></th>
                      <?php endforeach; ?>
                  </tr>
              </thead>
              <tbody>
                  <tr>
                      <td>Razón Corriente</td>
                      <td>Activo Circulante / Pasivo Circulante</td>
                      <?php foreach ($periodos as $p): 
                          $pid = $p['id'];
                          // Ajuste para leer la estructura de datos del dashboard
                          $activo_circ = $valores_balance['Total Activo Circulante']['valores'][$pid] ?? 0;
                          $pasivo_circ = $valores_balance['Total Pasivo Circulante']['valores'][$pid] ?? 1;
                          $resultado = $pasivo_circ != 0 ? $activo_circ / $pasivo_circ : 0;
                      ?>
                          <td><?= number_format($resultado, 2) ?></td>
                      <?php endforeach; ?>
                  </tr>
                  <tr>
                      <td>Prueba Ácida</td>
                      <td>(Activo Circulante - Inventarios) / Pasivo Circulante</td>
                      <?php foreach ($periodos as $p): 
                          $pid = $p['id'];
                          // Ajuste para leer la estructura de datos del dashboard
                          $activo_circ = $valores_balance['Total Activo Circulante']['valores'][$pid] ?? 0;
                          $inventarios = $valores_balance['Inventarios']['valores'][$pid] ?? 0;
                          $pasivo_circ = $valores_balance['Total Pasivo Circulante']['valores'][$pid] ?? 1;
                          $resultado = $pasivo_circ != 0 ? ($activo_circ - $inventarios) / $pasivo_circ : 0;
                      ?>
                          <td><?= number_format($resultado, 2) ?></td>
                      <?php endforeach; ?>
                  </tr>
                  <tr>
                      <td>Capital Neto Trabajo</td>
                      <td>Activo Circulante - Pasivo Circulante</td>
                      <?php foreach ($periodos as $p): 
                          $pid = $p['id'];
                          // Ajuste para leer la estructura de datos del dashboard
                          $activo_circ = $valores_balance['Total Activo Circulante']['valores'][$pid] ?? 0;
                          $pasivo_circ = $valores_balance['Total Pasivo Circulante']['valores'][$pid] ?? 0;
                          $resultado = $activo_circ - $pasivo_circ;
                      ?>
                          <td>$<?= number_format($resultado, 2) ?></td>
                      <?php endforeach; ?>
                  </tr>
              </tbody>
          </table>

          <h2 class="subtitle is-5 mt-4">Razones de Actividad</h2>
          <table class="table is-bordered is-fullwidth">
              <thead>
                  <tr>
                      <th>Razón</th>
                      <th>Fórmula</th>
                      <?php foreach ($periodos as $p): ?>
                          <th><?= $p['anio'] ?></th>
                      <?php endforeach; ?>
                  </tr>
              </thead>
              <tbody>
                  <tr>
                      <td>Rotación Inventarios</td>
                      <td>Costo Ventas / Inventario Promedio</td>
                      <?php foreach ($periodos as $p):
                          $pid = $p['id'];
                          // Ajuste para leer la estructura de datos del dashboard
                          $costo_ventas = $valores_resultados['Costo de Ventas']['valores'][$pid] ?? 0;
                          $inventario_promedio = $inventario_promedio_valores[$pid] ?? 0;
                          $resultado = $inventario_promedio != 0 ? $costo_ventas / $inventario_promedio : 0;
                      ?>
                          <td><?= $resultado != 0 ? number_format($resultado, 2) . ' veces' : 'N/A' ?></td>
                      <?php endforeach; ?>
                  </tr>
                  <tr>
                      <td>Periodo Promedio Cobro</td>
                      <td>(Cuentas por Cobrar / Ventas Netas) × 365</td>
                      <?php foreach ($periodos as $p):
                          $pid = $p['id'];
                          // Ajuste para leer la estructura de datos del dashboard
                          $ctas_cobrar = $valores_balance['Cuentas por Cobrar']['valores'][$pid] ?? 0;
                          $ventas = $valores_resultados['Ventas Netas']['valores'][$pid] ?? 1;
                          $resultado = $ventas != 0 ? ($ctas_cobrar / $ventas) * 365 : 0;
                      ?>
                          <td><?= number_format($resultado, 2) ?> días</td>
                      <?php endforeach; ?>
                  </tr>
                  <tr>
                      <td>Periodo Promedio Pago</td>
                      <td>(Cuentas por Pagar / Compras) × 365</td>
                      <?php foreach ($periodos as $p):
                          $pid = $p['id'];
                          // Ajuste para leer la estructura de datos del dashboard
                          $compras = $valores_resultados['Compras']['valores'][$pid] ?? ($valores_resultados['Costo de Ventas']['valores'][$pid] ?? 1);
                          $ctas_pagar = $valores_balance['Cuentas por Pagar']['valores'][$pid] ?? 0;
                          $resultado = $compras != 0 ? ($ctas_pagar / $compras) * 365 : 0;
                      ?>
                          <td><?= number_format($resultado, 2) ?> días</td>
                      <?php endforeach; ?>
                  </tr>
              </tbody>
          </table>

          <h2 class="subtitle is-5 mt-4">Razones de Endeudamiento</h2>
          <table class="table is-bordered is-fullwidth">
              <thead>
                  <tr>
                      <th>Razón</th>
                      <th>Fórmula</th>
                      <?php foreach ($periodos as $p): ?>
                          <th><?= $p['anio'] ?></th>
                      <?php endforeach; ?>
                  </tr>
              </thead>
              <tbody>
                  <tr>
                      <td>Endeudamiento Total</td>
                      <td>(Pasivo Total / Activo Total) × 100</td>
                      <?php foreach ($periodos as $p):
                          $pid = $p['id'];
                          // Ajuste para leer la estructura de datos del dashboard
                          $pasivo_total = $valores_balance['TOTAL PASIVO']['valores'][$pid] ?? 0;
                          $activo_total = $valores_balance['TOTAL ACTIVO']['valores'][$pid] ?? 1;
                          $resultado = $activo_total != 0 ? ($pasivo_total / $activo_total) * 100 : 0;
                      ?>
                          <td><?= number_format($resultado, 2) ?>%</td>
                      <?php endforeach; ?>
                  </tr>
                  <tr>
                      <td>Cobertura Intereses</td>
                      <td>Utilidad de Operación / Intereses</td>
                      <?php foreach ($periodos as $p):
                          $pid = $p['id'];
                          // Ajuste para leer la estructura de datos del dashboard
                          $utilidad_op = $valores_resultados['Utilidad de Operación']['valores'][$pid] ?? 0;
                          $intereses = abs($valores_resultados['Gastos por Intereses']['valores'][$pid] ?? $valores_resultados['Resultado Financiero']['valores'][$pid] ?? 1);
                          $resultado = $intereses != 0 ? $utilidad_op / $intereses : 0;
                      ?>
                          <td><?= $intereses != 0 ? number_format($resultado, 2) : 'N/A' ?></td>
                      <?php endforeach; ?>
                  </tr>
              </tbody>
          </table>

          <h2 class="subtitle is-5 mt-4">Razones de Rentabilidad</h2>
          <table class="table is-bordered is-fullwidth">
              <thead>
                  <tr>
                      <th>Razón</th>
                      <th>Fórmula</th>
                      <?php foreach ($periodos as $p): ?>
                          <th><?= $p['anio'] ?></th>
                      <?php endforeach; ?>
                  </tr>
              </thead>
              <tbody>
                  <tr>
                      <td>Margen Bruto</td>
                      <td>(Utilidad Bruta / Ventas) × 100</td>
                      <?php foreach ($periodos as $p):
                          $pid = $p['id'];
                          // Ajuste para leer la estructura de datos del dashboard
                          $util_bruta = $valores_resultados['Utilidad Bruta']['valores'][$pid] ?? 0;
                          $ventas = $valores_resultados['Ventas Netas']['valores'][$pid] ?? 1;
                          $resultado = $ventas != 0 ? ($util_bruta / $ventas) * 100 : 0;
                      ?>
                          <td><?= number_format($resultado, 2) ?>%</td>
                      <?php endforeach; ?>
                  </tr>
                  <tr>
                      <td>Margen Operativo</td>
                      <td>(Utilidad de Operación / Ventas) × 100</td>
                      <?php foreach ($periodos as $p):
                          $pid = $p['id'];
                          // Ajuste para leer la estructura de datos del dashboard
                          $util_op = $valores_resultados['Utilidad de Operación']['valores'][$pid] ?? 0;
                          $ventas = $valores_resultados['Ventas Netas']['valores'][$pid] ?? 1;
                          $resultado = $ventas != 0 ? ($util_op / $ventas) * 100 : 0;
                      ?>
                          <td><?= number_format($resultado, 2) ?>%</td>
                      <?php endforeach; ?>
                  </tr>
              </tbody>
          </table>
      </div>

      <div class="box mt-5">
        <h2 class="title is-4 has-text-centered">Análisis de Tendencias con Proyección</h2>

        <?php
            // --- LÓGICA DE DATOS PARA TENDENCIAS ---
            $tendencias_valores_balance = [];
            $tendencias_valores_resultados = [];
            $tendencias_labels = [];

            $stmt_periodos = $conn->prepare("
                SELECT DISTINCT YEAR(fecha_inicio) as anio 
                FROM periodos 
                WHERE empresa_id = ? ORDER BY anio
            ");
            $stmt_periodos->execute([$empresa_id]);
            $tendencias_labels = $stmt_periodos->fetchAll(PDO::FETCH_COLUMN);

            if (count($tendencias_labels)) {
                $cuentas_balance_nombres = ['Efectivo y Equivalentes', 'Inventarios', 'TOTAL ACTIVO', 'TOTAL PASIVO', 'CAPITAL CONTABLE'];
                $stmt_balance_tendencias = $conn->prepare("
                    SELECT YEAR(p.fecha_inicio) as anio, SUM(cb.valor) as valor 
                    FROM cuentas_balance cb
                    JOIN catalogo_cuentas cc ON cb.cuenta_id = cc.id
                    JOIN periodos p ON cb.periodo_id = p.id
                    WHERE cc.nombre = ? AND p.empresa_id = ?
                    GROUP BY anio ORDER BY anio
                ");
                foreach ($cuentas_balance_nombres as $cuenta) {
                    $stmt_balance_tendencias->execute([$cuenta, $empresa_id]);
                    $tendencias_valores_balance[$cuenta] = $stmt_balance_tendencias->fetchAll(PDO::FETCH_KEY_PAIR);
                }

                $cuentas_resultados_nombres = ['Ventas Netas', 'Costo de Ventas', 'Gastos de Operación', 'Utilidad Neta'];
                $stmt_resultados_tendencias = $conn->prepare("
                    SELECT YEAR(p.fecha_inicio) as anio, SUM(cr.valor) as valor 
                    FROM cuentas_resultados cr
                    JOIN catalogo_cuentas cc ON cr.cuenta_id = cc.id
                    JOIN periodos p ON cr.periodo_id = p.id
                    WHERE cc.nombre = ? AND p.empresa_id = ?
                    GROUP BY anio ORDER BY anio
                ");
                foreach ($cuentas_resultados_nombres as $cuenta) {
                    $stmt_resultados_tendencias->execute([$cuenta, $empresa_id]);
                    $tendencias_valores_resultados[$cuenta] = $stmt_resultados_tendencias->fetchAll(PDO::FETCH_KEY_PAIR);
                }
            }
        ?>

        <script>
          function calcularProyeccion(labels, data, grado = 2) {
            if (labels.length < 2) return { predicciones: ['N/A', 'N/A'] };
            const puntos = labels.map((x, i) => [x, data[i]]);
            const resultado = regression.polynomial(puntos, { order: grado, precision: 4 });
            const ultimoLabel = labels[labels.length - 1];
            return {
              predicciones: [
                resultado.predict(ultimoLabel + 1)[1],
                resultado.predict(ultimoLabel + 2)[1]
              ]
            };
          }
        </script>
        
        <h2 class="subtitle is-5 mt-4">Balance General - Tendencias Individuales</h2>
        <?php 
        $colores = ['rgba(54, 162, 235, 1)', 'rgba(255, 99, 132, 1)', 'rgba(75, 192, 192, 1)', 'rgba(153, 102, 255, 1)', 'rgba(255, 159, 64, 1)'];
        $i = 0;
        foreach ($tendencias_valores_balance as $cuenta => $valores): 
          if(empty($valores)) continue;
          $color = $colores[$i % count($colores)];
          $i++;
          $valores_grafica = array_map(fn($anio) => $valores[$anio] ?? null, $tendencias_labels);
        ?>
          <div class="mb-6">
            <div class="chart-container" style="position: relative; height:40vh; width:80vw; margin: auto;">
              <canvas id="graficaBalance<?= $i ?>"></canvas>
            </div>
            <script>
              document.addEventListener('DOMContentLoaded', function() {
                  const ctx = document.getElementById('graficaBalance<?= $i ?>');
                  const labels_proy = <?= json_encode(array_keys($valores)) ?>;
                  const data_proy = <?= json_encode(array_values($valores)) ?>;
                  const proyeccion = calcularProyeccion(labels_proy, data_proy);
                  const labels_chart = <?= json_encode($tendencias_labels) ?>;
                  const data_chart = <?= json_encode($valores_grafica) ?>;
                  const anosProyeccion = labels_chart.length > 0 ? [parseInt(labels_chart[labels_chart.length - 1]) + 1, parseInt(labels_chart[labels_chart.length - 1]) + 2] : [];
                  const lastDataIndex = data_chart.map((v, i) => v === null ? -1 : i).reduce((a, b) => Math.max(a, b), -1);
                  const combinedData = [...data_chart, ...proyeccion.predicciones];

                  new Chart(ctx, {
                      type: 'line',
                      data: {
                          labels: [...labels_chart, ...anosProyeccion.map(String)],
                          datasets: [{
                              label: '<?= addslashes($cuenta) ?>',
                              data: combinedData,
                              borderColor: '<?= $color ?>',
                              borderWidth: 3,
                              tension: 0.1,
                              spanGaps: true,
                              segment: { borderDash: (c) => (lastDataIndex !== -1 && c.p0DataIndex >= lastDataIndex) ? [5, 5] : undefined },
                          }]
                      },
                      options: { 
                          responsive: true, 
                          maintainAspectRatio: false, 
                          plugins: { 
                              title: { display: true, text: '<?= addslashes($cuenta) ?>', font: { size: 16 } }, 
                              legend: { 
                                  labels: { 
                                      usePointStyle: false, // Forzar a no usar un estilo de punto
                                      generateLabels: (chart) => { 
                                          const ds = chart.data.datasets[0]; 
                                          return [
                                              { text: ds.label, strokeStyle: ds.borderColor, fillStyle: 'transparent', lineWidth: ds.borderWidth, lineDash: [] }, 
                                              { text: 'Proyección', strokeStyle: ds.borderColor, fillStyle: 'transparent', lineWidth: 2, lineDash: [5, 5] }
                                          ]; 
                                      } 
                                  } 
                              } 
                          } 
                      }
                  });
              });
            </script>
          </div>
        <?php endforeach; ?>

        <h3 class="subtitle is-6 mt-5">Tabla de Valores y Proyecciones (Balance General)</h3>
        <table class="table is-bordered is-fullwidth">
          <thead>
              <tr>
                <th>Cuenta</th>
                <?php foreach ($tendencias_labels as $anio): ?><th><?= $anio ?></th><?php endforeach; ?>
                <?php if(count($tendencias_labels) >= 2): ?>
                  <th>Proy. <?= (int)end($tendencias_labels) + 1 ?></th>
                  <th>Proy. <?= (int)end($tendencias_labels) + 2 ?></th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tendencias_valores_balance as $cuenta => $valores): if(!empty($valores)): ?>
                <tr>
                  <td><?= htmlspecialchars($cuenta) ?></td>
                  <?php foreach ($tendencias_labels as $anio): ?>
                    <td><?= isset($valores[$anio]) ? '$'.number_format($valores[$anio], 2) : 'N/A' ?></td>
                  <?php endforeach; ?>
                  <?php if(count($tendencias_labels) >= 2): ?>
                    <td id="proy-b-1-<?= md5($cuenta) ?>">Calculando...</td>
                    <td id="proy-b-2-<?= md5($cuenta) ?>">Calculando...</td>
                    <script>
                      (() => {
                        const proy = calcularProyeccion(<?= json_encode(array_keys($valores)) ?>, <?= json_encode(array_values($valores)) ?>);
                        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
                        document.getElementById('proy-b-1-<?= md5($cuenta) ?>').innerText = formatter.format(proy.predicciones[0]);
                        document.getElementById('proy-b-2-<?= md5($cuenta) ?>').innerText = formatter.format(proy.predicciones[1]);
                      })();
                    </script>
                  <?php endif; ?>
                </tr>
              <?php endif; endforeach; ?>
            </tbody>
        </table>

        <h2 class="subtitle is-5 mt-6">Estado de Resultados - Tendencias Individuales</h2>
        <?php 
        $j = 0;
        foreach ($tendencias_valores_resultados as $cuenta => $valores):
          if(empty($valores)) continue;
          $color = $colores[($j + 1) % count($colores)];
          $j++;
          $valores_grafica = array_map(fn($anio) => $valores[$anio] ?? null, $tendencias_labels);
        ?>
          <div class="mb-6">
            <div class="chart-container" style="position: relative; height:40vh; width:80vw; margin: auto;">
              <canvas id="graficaResultados<?= $j ?>"></canvas>
            </div>
            <script>
              document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('graficaResultados<?= $j ?>');
                const labels_proy = <?= json_encode(array_keys($valores)) ?>;
                const data_proy = <?= json_encode(array_values($valores)) ?>;
                const proyeccion = calcularProyeccion(labels_proy, data_proy);
                const labels_chart = <?= json_encode($tendencias_labels) ?>;
                const data_chart = <?= json_encode($valores_grafica) ?>;
                const anosProyeccion = labels_chart.length > 0 ? [parseInt(labels_chart[labels_chart.length - 1]) + 1, parseInt(labels_chart[labels_chart.length - 1]) + 2] : [];
                const lastDataIndex = data_chart.map((v, i) => v === null ? -1 : i).reduce((a, b) => Math.max(a, b), -1);
                const combinedData = [...data_chart, ...proyeccion.predicciones];

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: [...labels_chart, ...anosProyeccion.map(String)],
                        datasets: [{
                            label: '<?= addslashes($cuenta) ?>',
                            data: combinedData,
                            borderColor: '<?= $color ?>',
                            borderWidth: 3,
                            tension: 0.1,
                            spanGaps: true,
                            segment: { borderDash: (c) => (lastDataIndex !== -1 && c.p0DataIndex >= lastDataIndex) ? [5, 5] : undefined },
                        }]
                    },
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        plugins: { 
                            title: { display: true, text: '<?= addslashes($cuenta) ?>', font: { size: 16 } }, 
                            legend: { 
                                labels: { 
                                    usePointStyle: false, // Forzar a no usar un estilo de punto
                                    generateLabels: (chart) => { 
                                        const ds = chart.data.datasets[0]; 
                                        return [
                                            { text: ds.label, strokeStyle: ds.borderColor, fillStyle: 'transparent', lineWidth: ds.borderWidth, lineDash: [] }, 
                                            { text: 'Proyección', strokeStyle: ds.borderColor, fillStyle: 'transparent', lineWidth: 2, lineDash: [5, 5] }
                                        ]; 
                                    } 
                                } 
                            } 
                        } 
                    }
                });
              });
            </script>
          </div>
        <?php endforeach; ?>
        
        <h3 class="subtitle is-6 mt-5">Tabla de Valores y Proyecciones (Estado de Resultados)</h3>
        <table class="table is-bordered is-fullwidth">
          <thead>
            <tr>
              <th>Cuenta</th>
              <?php foreach ($tendencias_labels as $anio): ?><th><?= $anio ?></th><?php endforeach; ?>
              <?php if(count($tendencias_labels) >= 2): ?>
                <th>Proy. <?= (int)end($tendencias_labels) + 1 ?></th>
                <th>Proy. <?= (int)end($tendencias_labels) + 2 ?></th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tendencias_valores_resultados as $cuenta => $valores): if(!empty($valores)): ?>
                <tr>
                  <td><?= htmlspecialchars($cuenta) ?></td>
                  <?php foreach ($tendencias_labels as $anio): ?>
                    <td><?= isset($valores[$anio]) ? '$'.number_format($valores[$anio], 2) : 'N/A' ?></td>
                  <?php endforeach; ?>
                  <?php if(count($tendencias_labels) >= 2): ?>
                    <td id="proy-r-1-<?= md5($cuenta) ?>">Calculando...</td>
                    <td id="proy-r-2-<?= md5($cuenta) ?>">Calculando...</td>
                    <script>
                      (() => {
                        const proy = calcularProyeccion(<?= json_encode(array_keys($valores)) ?>, <?= json_encode(array_values($valores)) ?>);
                        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
                        document.getElementById('proy-r-1-<?= md5($cuenta) ?>').innerText = formatter.format(proy.predicciones[0]);
                        document.getElementById('proy-r-2-<?= md5($cuenta) ?>').innerText = formatter.format(proy.predicciones[1]);
                      })();
                    </script>
                  <?php endif; ?>
                </tr>
              <?php endif; endforeach; ?>
          </tbody>
        </table>
    </div>

    <div class="box mt-5">
        <h2 class="title is-4 has-text-centered">Gestión de Efectivo (Ciclo de Conversión)</h2>

        <?php
        // --- INICIO: CÁLCULOS PARA EL CICLO DE EFECTIVO ---

        // 1. Periodo Promedio de Inventario (PPI)
        $ppi_valores = [];
        foreach ($periodos as $index => $p) {
            $pid = $p['id'];
            $costo_ventas = $valores_resultados['Costo de Ventas']['valores'][$pid] ?? 0;
            $inventario_actual = $valores_balance['Inventarios']['valores'][$pid] ?? 0;

            $inventario_promedio = 0;
            if ($index == 0) {
                $inventario_promedio = $inventario_actual;
            } else {
                $pid_anterior = $periodos[$index-1]['id'];
                $inventario_anterior = $valores_balance['Inventarios']['valores'][$pid_anterior] ?? 0;
                $inventario_promedio = ($inventario_anterior + $inventario_actual) / 2;
            }
            
            $ppi_valores[$pid] = ($costo_ventas == 0) ? 0 : ($inventario_promedio / $costo_ventas) * 365;
        }

        // 2. Periodo Promedio de Cobro (PPC)
        $ppc_valores = [];
        foreach ($periodos as $p) {
            $pid = $p['id'];
            $ventas = $valores_resultados['Ventas Netas']['valores'][$pid] ?? 0;
            $ctas_cobrar = $valores_balance['Cuentas por Cobrar']['valores'][$pid] ?? 0;
            $ppc_valores[$pid] = ($ventas == 0) ? 0 : ($ctas_cobrar / $ventas) * 365;
        }

        // 3. Periodo Promedio de Pago (PPP)
        $ppp_valores = [];
        foreach ($periodos as $p) {
            $pid = $p['id'];
            $compras_o_costo = $valores_resultados['Compras']['valores'][$pid] ?? ($valores_resultados['Costo de Ventas']['valores'][$pid] ?? 0);
            $ctas_pagar = $valores_balance['Cuentas por Pagar']['valores'][$pid] ?? 0;
            $ppp_valores[$pid] = ($compras_o_costo == 0) ? 0 : ($ctas_pagar / $compras_o_costo) * 365;
        }

        // 4. Ciclo Operativo (CO)
        $co_valores = [];
        foreach ($periodos as $p) {
            $pid = $p['id'];
            $co_valores[$pid] = ($ppi_valores[$pid] ?? 0) + ($ppc_valores[$pid] ?? 0);
        }

        // 5. Ciclo de Conversión de Efectivo (CCE)
        $cce_valores = [];
        foreach ($periodos as $p) {
            $pid = $p['id'];
            $cce_valores[$pid] = ($co_valores[$pid] ?? 0) - ($ppp_valores[$pid] ?? 0);
        }

        // --- FIN: CÁLCULOS ---
        ?>

        <h2 class="subtitle is-5 mt-4">Periodo Promedio de Inventario (PPI)</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= $p['anio'] ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>(Inventario Promedio / Costo de Ventas) × 365</td>
                    <?php foreach ($periodos as $p): ?>
                        <td><?= ($ppi_valores[$p['id']] ?? 0) == 0 ? 'N/A' : number_format($ppi_valores[$p['id']], 1) . ' días' ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
        
        <h2 class="subtitle is-5 mt-4">Periodo Promedio de Cobro (PPC)</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= $p['anio'] ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>(Cuentas por Cobrar / Ventas Netas) × 365</td>
                    <?php foreach ($periodos as $p): ?>
                        <td><?= ($ppc_valores[$p['id']] ?? 0) == 0 ? 'N/A' : number_format($ppc_valores[$p['id']], 1) . ' días' ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <h2 class="subtitle is-5 mt-4">Periodo Promedio de Pago (PPP)</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= $p['anio'] ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>(Cuentas por Pagar / Compras o Costo de Ventas) × 365</td>
                    <?php foreach ($periodos as $p): ?>
                        <td><?= ($ppp_valores[$p['id']] ?? 0) == 0 ? 'N/A' : number_format($ppp_valores[$p['id']], 1) . ' días' ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <h2 class="subtitle is-5 mt-4">Ciclo Operativo (CO)</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= $p['anio'] ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>PPI + PPC</td>
                    <?php foreach ($periodos as $p): ?>
                        <td><?= ($co_valores[$p['id']] ?? 0) == 0 ? 'N/A' : number_format($co_valores[$p['id']], 1) . ' días' ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <h2 class="subtitle is-5 mt-4">Ciclo de Conversión de Efectivo (CCE)</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= $p['anio'] ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Ciclo Operativo - PPP</td>
                    <?php foreach ($periodos as $p): ?>
                        <td><?= ($co_valores[$p['id']] ?? 0) == 0 && ($ppp_valores[$p['id']] ?? 0) == 0 ? 'N/A' : number_format($cce_valores[$p['id']], 1) . ' días' ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="box mt-5">
        <h2 class="title is-4 has-text-centered">Gestión de Cuentas por Cobrar</h2>

        <?php
        // --- INICIO: CÁLCULO PARA ROTACIÓN DE CUENTAS POR COBRAR ---
        // El Período Promedio de Cobro (PPC) ya se calculó en la sección de Gestión de Efectivo ($ppc_valores).
        
        // Calculamos la Rotación de Cuentas por Cobrar
        $rotacion_cc_valores = [];
        foreach ($periodos as $p) {
            $pid = $p['id'];
            
            // Adaptamos la lectura de datos a la estructura del dashboard
            $ventas_netas = $valores_resultados['Ventas Netas']['valores'][$pid] ?? 0;
            $cuentas_por_cobrar = $valores_balance['Cuentas por Cobrar']['valores'][$pid] ?? 0;
            
            // El cálculo de la rotación es Ventas / Cuentas por Cobrar.
            // Si las Cuentas por Cobrar son 0, la rotación no se puede definir o es infinita, aquí la marcaremos como 0.
            $rotacion_cc_valores[$pid] = ($cuentas_por_cobrar == 0) ? 0 : ($ventas_netas / $cuentas_por_cobrar);
        }
        // --- FIN: CÁLCULO ---
        ?>

        <h2 class="subtitle is-5 mt-4">Período Promedio de Cobro (Días)</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= htmlspecialchars($p['anio']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>(Cuentas por Cobrar / Ventas Netas) × 365</td>
                    <?php foreach ($periodos as $p): ?>
                        <td>
                            <?php 
                            // Usamos la variable $ppc_valores que ya existe de la sección anterior
                            $ppc = $ppc_valores[$p['id']] ?? 0;
                            echo ($ppc == 0) ? 'N/A' : number_format($ppc, 1) . ' días';
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <h2 class="subtitle is-5 mt-4">Rotación de Cuentas por Cobrar</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= htmlspecialchars($p['anio']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Ventas Netas / Cuentas por Cobrar</td>
                    <?php foreach ($periodos as $p): ?>
                        <td>
                            <?php 
                            $rotacion = $rotacion_cc_valores[$p['id']] ?? 0;
                            echo ($rotacion == 0) ? 'N/A' : number_format($rotacion, 2) . ' veces';
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="box mt-5">
        <h2 class="title is-4 has-text-centered">Gestión de Inventarios</h2>

        <?php
        // --- INICIO: CÁLCULOS Y VARIABLES PARA GESTIÓN DE INVENTARIO ---

        // 1. Inventario Promedio
        $inventario_promedio_valores = [];
        foreach ($periodos as $index => $p) {
            $pid = $p['id'];
            $inventario_actual = $valores_balance['Inventarios']['valores'][$pid] ?? 0;

            if ($index == 0) {
                $inventario_promedio_valores[$pid] = $inventario_actual;
            } else {
                $pid_anterior = $periodos[$index - 1]['id'];
                $inventario_anterior = $valores_balance['Inventarios']['valores'][$pid_anterior] ?? 0;
                $inventario_promedio_valores[$pid] = ($inventario_anterior + $inventario_actual) / 2;
            }
        }

        // 2. Rotación de Inventarios
        $rotacion_inv_valores = [];
        foreach ($periodos as $p) {
            $pid = $p['id'];
            $costo_ventas = $valores_resultados['Costo de Ventas']['valores'][$pid] ?? 0;
            $inventario_promedio = $inventario_promedio_valores[$pid] ?? 0;
            $rotacion_inv_valores[$pid] = ($inventario_promedio == 0) ? 0 : ($costo_ventas / $inventario_promedio);
        }
        
        // El Período Promedio de Inventario (PPI) ya se calculó en la sección de Gestión de Efectivo ($ppi_valores).

        // 3. Variables para EOQ y Punto de Reorden (estos son ejemplos, deben venir de fuera)
        $demanda_anual_d = 0; 
        $costo_por_pedido_k = 0; 
        $costo_mantenimiento_g = 0;
        $demanda_diaria = 0;
        $tiempo_entrega_dias = 0;
        
        // --- FIN: CÁLCULOS ---
        ?>

        <h2 class="subtitle is-5 mt-4">Inventario Promedio</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= htmlspecialchars($p['anio']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>(Inventario Inicial + Inventario Final) / 2</td>
                    <?php foreach ($periodos as $p): ?>
                        <td>
                            <?= '$' . number_format($inventario_promedio_valores[$p['id']], 2) ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <h2 class="subtitle is-5 mt-4">Rotación de Inventarios</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= htmlspecialchars($p['anio']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Costo de Ventas / Inventario Promedio</td>
                    <?php foreach ($periodos as $p): ?>
                        <td>
                            <?= ($rotacion_inv_valores[$p['id']] == 0) ? 'N/A' : number_format($rotacion_inv_valores[$p['id']], 2) . ' veces' ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <h2 class="subtitle is-5 mt-4">Período Promedio de Inventario (Días)</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= htmlspecialchars($p['anio']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>365 / Rotación de Inventarios</td>
                    <?php foreach ($periodos as $p): ?>
                        <td>
                            <?php 
                            // Usamos la variable $ppi_valores que ya existe de la sección de Gestión de Efectivo
                            $ppi = $ppi_valores[$p['id']] ?? 0;
                            echo ($ppi == 0) ? 'N/A' : number_format($ppi, 1) . ' días';
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <h2 class="subtitle is-5 mt-4">Nivel Óptimo de Inventario (EOQ)</h2>
        <div class="notification is-info is-light">
          <strong>Nota:</strong> Para este cálculo se requiere la <strong>Demanda Anual (D)</strong>, el <strong>Costo por Pedido (K)</strong> y el <strong>Costo de Mantenimiento (G)</strong>. Estos datos deben obtenerse de la operación del negocio, no de los estados financieros.
        </div>
        <?php
            $eoq_value = 0;
            if ($demanda_anual_d > 0 && $costo_por_pedido_k > 0 && $costo_mantenimiento_g > 0) {
                $eoq_value = sqrt((2 * $costo_por_pedido_k * $demanda_anual_d) / $costo_mantenimiento_g);
            }
        ?>
        <table class="table is-bordered is-fullwidth">
            <tbody>
                <tr>
                    <td><strong>Fórmula:</strong> Q = &radic;(2KD / G)</td>
                    <td>
                        <strong>Resultado:</strong>
                        <?php if ($eoq_value > 0): ?>
                            <?= number_format($eoq_value, 2) ?> unidades
                        <?php else: ?>
                            Datos incompletos para el cálculo.
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2 class="subtitle is-5 mt-4">Nivel de Reorden</h2>
        <div class="notification is-info is-light">
          <strong>Nota:</strong> Para este cálculo se requiere la <strong>Demanda diaria</strong> y el <strong>Tiempo de entrega</strong> del proveedor en días.
        </div>
        <?php
            $punto_reorden_value = 0;
            if ($demanda_diaria > 0 && $tiempo_entrega_dias > 0) {
                $punto_reorden_value = $demanda_diaria * $tiempo_entrega_dias;
            }
        ?>
        <table class="table is-bordered is-fullwidth">
            <tbody>
                <tr>
                    <td><strong>Fórmula:</strong> Punto de Reorden = Demanda diaria &times; Tiempo de entrega</td>
                    <td>
                        <strong>Resultado:</strong>
                        <?php if ($punto_reorden_value > 0): ?>
                            <?= number_format($punto_reorden_value, 2) ?> unidades
                        <?php else: ?>
                            Datos incompletos para el cálculo.
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="box mt-5">
        <h2 class="title is-4 has-text-centered">Gestión de Cuentas por Pagar</h2>

        <?php
        // --- INICIO: CÁLCULO PARA ROTACIÓN DE CUENTAS POR PAGAR ---
        // El Período Promedio de Pago (PPP) ya se calculó en la sección de Gestión de Efectivo ($ppp_valores).
        
        // Calculamos la Rotación de Cuentas por Pagar
        $rotacion_cp_valores = [];
        foreach ($periodos as $p) {
            $pid = $p['id'];
            
            // Adaptamos la lectura de datos a la estructura del dashboard
            $compras_o_costo = $valores_resultados['Compras']['valores'][$pid] ?? ($valores_resultados['Costo de Ventas']['valores'][$pid] ?? 0);
            $cuentas_por_pagar = $valores_balance['Cuentas por Pagar']['valores'][$pid] ?? 0;
            
            // El cálculo de la rotación es Compras (o Costo de Ventas) / Cuentas por Pagar.
            $rotacion_cp_valores[$pid] = ($cuentas_por_pagar == 0) ? 0 : ($compras_o_costo / $cuentas_por_pagar);
        }
        // --- FIN: CÁLCULO ---
        ?>

        <h2 class="subtitle is-5 mt-4">Período Promedio de Pago (Días)</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= htmlspecialchars($p['anio']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>(Cuentas por Pagar / Compras o Costo de Ventas) × 365</td>
                    <?php foreach ($periodos as $p): ?>
                        <td>
                            <?php 
                            // Usamos la variable $ppp_valores que ya existe de la sección de Gestión de Efectivo
                            $ppp = $ppp_valores[$p['id']] ?? 0;
                            echo ($ppp == 0) ? 'N/A' : number_format($ppp, 1) . ' días';
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>

        <h2 class="subtitle is-5 mt-4">Rotación de Cuentas por Pagar</h2>
        <table class="table is-bordered is-fullwidth">
            <thead>
                <tr>
                    <th>Fórmula</th>
                    <?php foreach ($periodos as $p): ?>
                        <th><?= htmlspecialchars($p['anio']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Costo de Ventas / Cuentas por Pagar</td>
                    <?php foreach ($periodos as $p): ?>
                        <td>
                            <?php 
                            $rotacion = $rotacion_cp_valores[$p['id']] ?? 0;
                            echo ($rotacion == 0) ? 'N/A' : number_format($rotacion, 2) . ' veces';
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>

    <?php
    // --- INICIO: SECCIÓN DE ESCENARIOS "WHAT-IF" GUARDADOS ---

    // 1. Obtener los escenarios guardados para la empresa seleccionada
    $stmt_escenarios = $conn->prepare("
        SELECT * FROM escenarios_whatif 
        WHERE empresa_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt_escenarios->execute([$empresa_id]);
    $escenarios_guardados = $stmt_escenarios->fetchAll();

    // 2. Crear un mapa de periodo_id => anio para búsqueda fácil
    $mapa_periodos_anio = [];
    foreach ($periodos as $p) {
        $mapa_periodos_anio[$p['id']] = $p['anio'];
    }
    ?>
    <div class="box mt-5">
        <h2 class="title is-4 has-text-centered">Escenarios "What-If" Guardados</h2>
        
        <?php if (count($escenarios_guardados) > 0): ?>
            <?php foreach ($escenarios_guardados as $escenario): ?>
                <?php
                    // Obtener el año base del escenario
                    $anio_base = $mapa_periodos_anio[$escenario['periodo_base_id']] ?? 'Desconocido';
                    // Formatear fecha de creación
                    $fecha_creacion = date('d/m/Y H:i', strtotime($escenario['created_at']));
                ?>
                <div class="box mb-5 content">
                    <h3 class="subtitle is-5">
                        Escenario del (Año Base: <?= htmlspecialchars($anio_base) ?>)
                    </h3>
                    
                    <h4 class="subtitle is-6">Parámetros de Simulación:</h4>
                    <ul>
                        <li><strong>Nuevo PPC:</strong> <?= $escenario['mod_ppc'] !== null ? htmlspecialchars(number_format($escenario['mod_ppc'], 2)) . ' días' : '<em>Sin cambio</em>' ?></li>
                        <li><strong>Nuevo PPI:</strong> <?= $escenario['mod_ppi'] !== null ? htmlspecialchars(number_format($escenario['mod_ppi'], 2)) . ' días' : '<em>Sin cambio</em>' ?></li>
                        <li><strong>Nuevo PPP:</strong> <?= $escenario['mod_ppp'] !== null ? htmlspecialchars(number_format($escenario['mod_ppp'], 2)) . ' días' : '<em>Sin cambio</em>' ?></li>
                        <li><strong>Crecimiento Ventas:</strong> <?= $escenario['mod_ventas_crecimiento'] !== null ? htmlspecialchars(number_format($escenario['mod_ventas_crecimiento'], 2)) . '%' : '<em>Sin cambio</em>' ?></li>
                    </ul>

                    <h4 class="subtitle is-6 mt-4">Resultados Comparativos:</h4>
                    <div class="table-container">
                        <table class="table is-bordered is-fullwidth is-hoverable">
                            <thead>
                                <tr>
                                    <th>Indicador</th>
                                    <th>Valor Base (<?= htmlspecialchars($anio_base) ?>)</th>
                                    <th>Valor Simulado ("What-If")</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="3" class="has-background-light has-text-weight-bold">Ciclo de Conversión de Efectivo</td></tr>
                                <tr><td>Período Promedio de Inventario (PPI)</td><td><?= number_format($escenario['ppi_base'], 2) ?> días</td><td><?= number_format($escenario['ppi_whatif'], 2) ?> días</td></tr>
                                <tr><td>Período Promedio de Cobro (PPC)</td><td><?= number_format($escenario['ppc_base'], 2) ?> días</td><td><?= number_format($escenario['ppc_whatif'], 2) ?> días</td></tr>
                                <tr><td>Período Promedio de Pago (PPP)</td><td><?= number_format($escenario['ppp_base'], 2) ?> días</td><td><?= number_format($escenario['ppp_whatif'], 2) ?> días</td></tr>
                                <tr><td>Ciclo Operativo (CO)</td><td><?= number_format($escenario['co_base'], 2) ?> días</td><td><?= number_format($escenario['co_whatif'], 2) ?> días</td></tr>
                                <tr><td>Ciclo de Conversión de Efectivo (CCE)</td><td><?= number_format($escenario['cce_base'], 2) ?> días</td><td><?= number_format($escenario['cce_whatif'], 2) ?> días</td></tr>
                                
                                <tr><td colspan="3" class="has-background-light has-text-weight-bold">Razones de Liquidez</td></tr>
                                <tr><td>Razón Corriente</td><td><?= number_format($escenario['razon_corriente_base'], 2) ?></td><td><?= number_format($escenario['razon_corriente_whatif'], 2) ?></td></tr>
                                <tr><td>Prueba Ácida</td><td><?= number_format($escenario['prueba_acida_base'], 2) ?></td><td><?= number_format($escenario['prueba_acida_whatif'], 2) ?></td></tr>
                                <tr><td>Capital Neto de Trabajo</td><td>$<?= number_format($escenario['capital_neto_base'], 2) ?></td><td>$<?= number_format($escenario['capital_neto_whatif'], 2) ?></td></tr>

                                <tr><td colspan="3" class="has-background-light has-text-weight-bold">Valores de Balance Impactados</td></tr>
                                <tr><td>Efectivo y Equivalentes</td><td>$<?= number_format($escenario['efectivo_base'], 2) ?></td><td>$<?= number_format($escenario['efectivo_whatif'], 2) ?></td></tr>
                                <tr><td>Cuentas por Cobrar</td><td>$<?= number_format($escenario['ctas_cobrar_base'], 2) ?></td><td>$<?= number_format($escenario['ctas_cobrar_whatif'], 2) ?></td></tr>
                                <tr><td>Inventarios</td><td>$<?= number_format($escenario['inventarios_base'], 2) ?></td><td>$<?= number_format($escenario['inventarios_whatif'], 2) ?></td></tr>
                                <tr><td>Cuentas por Pagar</td><td>$<?= number_format($escenario['ctas_pagar_base'], 2) ?></td><td>$<?= number_format($escenario['ctas_pagar_whatif'], 2) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notification is-info is-light">
                No hay escenarios "What-If" guardados para esta empresa. Puede crear y guardar nuevos escenarios en la pestaña de Simulación "What-If".
            </div>
        <?php endif; ?>
    </div>
    <?php // --- FIN: SECCIÓN DE ESCENARIOS "WHAT-IF" GUARDADOS --- ?>

    <div class="box mt-5">
        <h2 class="title is-4 has-text-centered">Proyecciones Financieras Guardadas</h2>

        <?php
        // --- INICIO: SECCIÓN DE PROYECCIONES FINANCIERAS GUARDADAS ---

        // 1. Obtener las proyecciones de resultados guardadas
        $stmt_proy_resultados = $conn->prepare("
            SELECT cc.nombre as cuenta, pr.anio, pr.valor, pr.tipo
            FROM proyecciones_resultados pr
            JOIN catalogo_cuentas cc ON pr.cuenta_id = cc.id
            WHERE pr.empresa_id = ?
            ORDER BY cc.orden, pr.anio
        ");
        $stmt_proy_resultados->execute([$empresa_id]);
        $proyecciones_resultados_raw = $stmt_proy_resultados->fetchAll(PDO::FETCH_ASSOC);

        // 2. Obtener las proyecciones de balance guardadas
        $stmt_proy_balance = $conn->prepare("
            SELECT cc.nombre as cuenta, pb.anio, pb.valor, pb.tipo
            FROM proyecciones_balance pb
            JOIN catalogo_cuentas cc ON pb.cuenta_id = cc.id
            WHERE pb.empresa_id = ?
            ORDER BY cc.orden, pb.anio
        ");
        $stmt_proy_balance->execute([$empresa_id]);
        $proyecciones_balance_raw = $stmt_proy_balance->fetchAll(PDO::FETCH_ASSOC);

        // 3. Procesar los datos para facilitar su visualización
        $proyecciones_resultados = [];
        $proyecciones_balance = [];
        $anios_proyeccion = [];

        foreach ($proyecciones_resultados_raw as $row) {
            $proyecciones_resultados[$row['cuenta']][$row['anio']] = ['valor' => $row['valor'], 'tipo' => $row['tipo']];
            if (!in_array($row['anio'], $anios_proyeccion)) {
                $anios_proyeccion[] = $row['anio'];
            }
        }

        foreach ($proyecciones_balance_raw as $row) {
            $proyecciones_balance[$row['cuenta']][$row['anio']] = ['valor' => $row['valor'], 'tipo' => $row['tipo']];
            if (!in_array($row['anio'], $anios_proyeccion)) {
                $anios_proyeccion[] = $row['anio'];
            }
        }
        sort($anios_proyeccion);

        if (!empty($anios_proyeccion)):
        ?>
            <h3 class="subtitle is-5 mt-4">Estado de Resultados Proyectado</h3>
            <div class="table-container">
                <table class="table is-bordered is-fullwidth">
                    <thead>
                        <tr>
                            <th>Cuenta</th>
                            <?php foreach ($anios_proyeccion as $anio): ?>
                                <th><?= $anio ?> (<?= ucfirst($proyecciones_resultados[array_key_first($proyecciones_resultados)][$anio]['tipo'] ?? '') ?>)</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proyecciones_resultados as $cuenta => $valores_por_anio): ?>
                            <tr>
                                <td><?= htmlspecialchars($cuenta) ?></td>
                                <?php foreach ($anios_proyeccion as $anio): ?>
                                    <td>$<?= isset($valores_por_anio[$anio]) ? number_format($valores_por_anio[$anio]['valor'], 2) : 'N/A' ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 class="subtitle is-5 mt-5">Balance General Proyectado</h3>
            <div class="table-container">
                <table class="table is-bordered is-fullwidth">
                    <thead>
                        <tr>
                            <th>Cuenta</th>
                            <?php foreach ($anios_proyeccion as $anio): ?>
                                <th><?= $anio ?> (<?= ucfirst($proyecciones_balance[array_key_first($proyecciones_balance)][$anio]['tipo'] ?? '') ?>)</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proyecciones_balance as $cuenta => $valores_por_anio): ?>
                            <tr>
                                <td><?= htmlspecialchars($cuenta) ?></td>
                                <?php foreach ($anios_proyeccion as $anio): ?>
                                    <td>$<?= isset($valores_por_anio[$anio]) ? number_format($valores_por_anio[$anio]['valor'], 2) : 'N/A' ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="notification is-info is-light mt-4">
                No hay proyecciones financieras guardadas para esta empresa. Puede generarlas y guardarlas en la pestaña "Proyecciones Financieras".
            </div>
        <?php endif; ?>
        <?php // --- FIN: SECCIÓN DE PROYECCIONES FINANCIERAS GUARDADAS --- ?>
    </div>
  </div>
  <div>
        <div class="control">
            <button type="button" id="export-pdf-btn" class="button is-primary is-large">
                <span class="icon">
                    <i class="fas fa-file-pdf"></i>
                </span>
                <span>Exportar a PDF</span>
            </button>
        </div>
    </div>
    <?php // --- START: PDF EXPORT SCRIPT --- ?>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
      <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script> <?php // For the PDF icon, if not already included ?>

      <script>
      document.addEventListener('DOMContentLoaded', function() {
          // Find the export button
          const exportBtn = document.getElementById('export-pdf-btn');

          // Check if the button exists
          if (exportBtn) {
              exportBtn.addEventListener('click', function() {
                  
                  // Let the user know something is happening
                  exportBtn.innerHTML = `
                      <span class="icon">
                          <i class="fas fa-spinner fa-spin"></i>
                      </span>
                      <span>Generando PDF...</span>`;
                  exportBtn.disabled = true;

                  // Select the element to be converted to PDF
                  const elementToExport = document.getElementById('dashboard-content');

                  // Use html2canvas to capture the element
                  html2canvas(elementToExport, {
                      scale: 2, // Increase scale for better resolution
                      useCORS: true // Important for images/charts from other origins
                  }).then(canvas => {
                      // Initialize jsPDF
                      const { jsPDF } = window.jspdf;
                      const pdf = new jsPDF({
                          orientation: 'p', // p for portrait, l for landscape
                          unit: 'mm',
                          format: 'a4'
                      });

                      // Calculate dimensions
                      const imgData = canvas.toDataURL('image/png');
                      const imgWidth = 210; // A4 width in mm
                      const pageHeight = 295; // A4 height in mm
                      const imgHeight = canvas.height * imgWidth / canvas.width;
                      let heightLeft = imgHeight;
                      let position = 0;

                      // Add the first page
                      pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                      heightLeft -= pageHeight;

                      // Add new pages if the content is long
                      while (heightLeft > 0) {
                          position = heightLeft - imgHeight;
                          pdf.addPage();
                          pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                          heightLeft -= pageHeight;
                      }
                      
                      // Save the PDF
                      pdf.save('dashboard-financiero.pdf');

                      // Restore the button to its original state
                      exportBtn.innerHTML = `
                          <span class="icon">
                              <i class="fas fa-file-pdf"></i>
                          </span>
                          <span>Exportar a PDF</span>`;
                      exportBtn.disabled = false;

                  }).catch(err => {
                      console.error('oops, something went wrong!', err);
                      // Restore the button in case of an error
                      exportBtn.innerHTML = `
                          <span class="icon">
                              <i class="fas fa-times-circle"></i>
                          </span>
                          <span>Error al Exportar</span>`;
                      exportBtn.disabled = false;
                  });
              });
          }
      });
      </script>
      <?php // --- END: PDF EXPORT SCRIPT --- ?>
    <?php elseif ($empresa_id): ?>
        <div class="notification is-warning">
            Se necesitan al menos 2 periodos para realizar el análisis horizontal.
        </div>
    <?php endif; ?>
</div>