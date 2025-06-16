<div class="container mt-5">
    <h1 class="title is-3">Análisis de Razones Financieras</h1>

    <?php
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/FinanzasProyecto/');
    include 'inc/head.php';
    include 'inc/db.php';
    
    // Obtener empresas
    $empresas = $conn->query("SELECT id, nombre FROM empresas")->fetchAll();
    $empresa_id = $_POST['empresa_id'] ?? null;
    $periodos = [];
    $valores_balance = [];
    $valores_resultados = [];

    if ($empresa_id) {
        // Obtener periodos para la empresa seleccionada
        $periodos = $conn->query("SELECT id, YEAR(fecha_inicio) as anio FROM periodos 
                                WHERE empresa_id = $empresa_id ORDER BY fecha_inicio")->fetchAll();

        if (!empty($periodos)) {
            // Obtener datos de balance
            $balance_data = $conn->query("
                SELECT cc.nombre as cuenta, cb.periodo_id, cb.valor, cc.tipo 
                FROM cuentas_balance cb
                JOIN catalogo_cuentas cc ON cb.cuenta_id = cc.id
                WHERE cb.periodo_id IN (SELECT id FROM periodos WHERE empresa_id = $empresa_id)
            ")->fetchAll();

            foreach ($balance_data as $row) {
                $valores_balance[$row['cuenta']][$row['periodo_id']] = $row['valor'];
            }

            // Obtener datos de estado de resultados
            $resultados_data = $conn->query("
                SELECT cc.nombre as cuenta, cr.periodo_id, cr.valor, cc.tipo 
                FROM cuentas_resultados cr
                JOIN catalogo_cuentas cc ON cr.cuenta_id = cc.id
                WHERE cr.periodo_id IN (SELECT id FROM periodos WHERE empresa_id = $empresa_id)
            ")->fetchAll();

            foreach ($resultados_data as $row) {
                $valores_resultados[$row['cuenta']][$row['periodo_id']] = $row['valor'];
            }
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
                        <option value="<?= $empresa['id'] ?>" <?= $empresa_id == $empresa['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($empresa['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

    <?php if ($empresa_id && !empty($periodos)): ?>
    
        <?php
        // --- Cálculo de Inventario Promedio para todos los períodos ---
        // Este bloque se coloca aquí para que $inventario_promedio_valores esté disponible para otras razones.
        $inventario_promedio_valores = [];
        foreach ($periodos as $index => $p) {
            $pid = $p['id'];
            $inventario_actual = $valores_balance['Inventarios'][$pid] ?? 0;

            if ($index == 0) {
                // Para el primer período, si solo tenemos el valor final, lo usamos como promedio.
                $inventario_promedio = $inventario_actual;
            } else {
                $pid_anterior = $periodos[$index-1]['id'];
                $inventario_anterior = $valores_balance['Inventarios'][$pid_anterior] ?? 0;
                // Inventario Promedio = (Inventario Inicial + Inventario Final) / 2
                $inventario_promedio = ($inventario_anterior + $inventario_actual) / 2;
            }
            $inventario_promedio_valores[$pid] = $inventario_promedio;
        }
        ?>

        <div class="box mt-5">
            <h2 class="subtitle is-5">Razones de Liquidez</h2>
            <?php if (!empty($valores_balance)): // Usar $valores_balance ya que estas razones dependen de él ?>
                <table class="table is-bordered is-fullwidth">
                    <thead>
                        <tr>
                            <th style="width: 12%">Razón</th>
                            <th style="width: 20%">Fórmula</th>
                            <?php foreach ($periodos as $p): ?>
                                <th style="width: 5%"><?= $p['anio'] ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $razon_corriente = [];
                        foreach ($periodos as $p) {
                            $pid = $p['id'];
                            $activo_circ = $valores_balance['Total Activo Circulante'][$pid] ?? 0;
                            $pasivo_circ = $valores_balance['Total Pasivo Circulante'][$pid] ?? 1; // Evitar división por cero
                            $razon_corriente[$pid] = $activo_circ / $pasivo_circ;
                        }
                        ?>
                        <tr>
                            <td style="width: 12%">Razón Corriente</td>
                            <td style="width: 20%">Activo Circulante / Pasivo Circulante</td>
                            <?php foreach ($periodos as $p): ?>
                                <td style="width: 5%"><?= number_format($razon_corriente[$p['id']], 2) ?></td>
                            <?php endforeach; ?>
                        </tr>

                        <?php
                        $prueba_acida = [];
                        foreach ($periodos as $p) {
                            $pid = $p['id'];
                            $activo_circ = $valores_balance['Total Activo Circulante'][$pid] ?? 0;
                            $inventarios = $valores_balance['Inventarios'][$pid] ?? 0;
                            $pasivo_circ = $valores_balance['Total Pasivo Circulante'][$pid] ?? 1; // Evitar división por cero
                            $prueba_acida[$pid] = ($activo_circ - $inventarios) / $pasivo_circ;
                        }
                        ?>
                        <tr>
                            <td style="width: 12%">Prueba Ácida</td>
                            <td style="width: 20%">(Activo Circulante - Inventarios) / Pasivo Circulante</td>
                            <?php foreach ($periodos as $p): ?>
                                <td style="width: 5%"><?= number_format($prueba_acida[$p['id']], 2) ?></td>
                            <?php endforeach; ?>
                        </tr>

                        <?php
                        $cnt = [];
                        foreach ($periodos as $p) {
                            $pid = $p['id'];
                            $activo_circ = $valores_balance['Total Activo Circulante'][$pid] ?? 0;
                            $pasivo_circ = $valores_balance['Total Pasivo Circulante'][$pid] ?? 0;
                            $cnt[$pid] = $activo_circ - $pasivo_circ;
                        }
                        ?>
                        <tr>
                            <td style="width: 12%">Capital Neto Trabajo</td>
                            <td style="width: 20%">Activo Circulante - Pasivo Circulante</td>
                            <?php foreach ($periodos as $p): ?>
                                <td style="width: 5%">$<?= number_format($cnt[$p['id']], 2) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay datos en el balance general lo cual no se pueden realizar las operaciones de liquidez.</p>
            <?php endif; ?>
        </div>

        <div class="box mt-5">
            <h2 class="subtitle is-5">Razones de Actividad</h2>
            <?php if (!empty($resultados_data) && isset($valores_balance['Inventarios']) && isset($valores_resultados['Costo de Ventas'])): // Condición ajustada para Rotación de Inventarios ?>
                <table class="table is-bordered is-fullwidth">
                    <thead>
                        <tr>
                            <th style="width: 12%">Razón</th>
                            <th style="width: 20%">Fórmula</th>
                            <?php foreach ($periodos as $p): ?>
                                <th style="width: 5%"><?= $p['anio'] ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rotacion_inv = [];
                        foreach ($periodos as $p) { // Ya no necesitamos $index aquí, Inventario Promedio ya está calculado
                            $pid = $p['id'];
                            $costo_ventas = $valores_resultados['Costo de Ventas'][$pid] ?? 0;
                            $inventario_promedio = $inventario_promedio_valores[$pid] ?? 0; // Usar el valor precalculado

                            // Rotación de Inventarios = Costo de Ventas / Inventario Promedio
                            $rotacion_inv[$pid] = ($inventario_promedio == 0 ? 0 : ($costo_ventas / $inventario_promedio));
                        }
                        ?>
                        <tr>
                            <td style="width: 12%">Rotación Inventarios</td>
                            <td style="width: 20%">Costo Ventas / Inventario Promedio</td>
                            <?php foreach ($periodos as $p): ?>
                                <td style="width: 5%">
                                    <?php if ($rotacion_inv[$p['id']] == 0): ?>
                                        N/A
                                    <?php else: ?>
                                        <?= number_format($rotacion_inv[$p['id']], 2) ?> veces
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <?php
                        $ppc = [];
                            foreach ($periodos as $index => $p) {
                                $pid = $p['id'];
                                $ventas = $valores_resultados['Ventas Netas'][$pid] ?? 1;
                                $ctas_cobrar_actual = $valores_balance['Cuentas por Cobrar'][$pid] ?? 0;
                                
                                // Evitar división por cero
                                $ventas = $ventas ?: 1;
                                
                                // Aplicar la fórmula directa: (Cuentas por Cobrar / Ventas Netas) * 365
                                $ppc[$pid] = ($ctas_cobrar_actual / $ventas) * 365;
                            }
                        ?>
                        <tr>
                            <td style="width: 12%">Periodo Promedio Cobro</td>
                            <td style="width: 20%">(Cuentas por Cobrar / Ventas Netas) × 365</td>
                            <?php foreach ($periodos as $p): ?>
                                <td style="width: 5%">
                                    <?= number_format($ppc[$p['id']], 2) ?> días
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php
                            $ppp = [];
                            foreach ($periodos as $index => $p) {
                                $pid = $p['id'];
                                // Se ajusta para obtener "Compras" directamente si existe, si no, usa Costo de Ventas
                                $compras = $valores_resultados['Compras'][$pid] ?? ($valores_resultados['Costo de Ventas'][$pid] ?? 1);
                                $ctas_pagar_actual = $valores_balance['Cuentas por Pagar'][$pid] ?? 0;

                                // Evitar división por cero
                                $compras = $compras ?: 1;

                                // Aplicar la fórmula directa: (Cuentas por Pagar / Compras) * 365
                                $ppp[$pid] = ($ctas_pagar_actual / $compras) * 365;
                            }
                        ?>
                        <tr>
                            <td style="width: 12%">Periodo Promedio Pago</td>
                            <td style="width: 20%">(Cuentas por Pagar / Compras) × 365</td>
                            <?php foreach ($periodos as $p): ?>
                                <td style="width: 5%">
                                    <?= number_format($ppp[$p['id']], 2) ?> días
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay datos suficientes para calcular las razones de actividad.</p>
            <?php endif; ?>
        </div>

        <div class="box mt-5">
            <h2 class="subtitle is-5">Razones de Endeudamiento</h2>
            <?php if (!empty($valores_balance)): // Usar $valores_balance ya que estas razones dependen de él ?>
                <table class="table is-bordered is-fullwidth">
                    <thead>
                        <tr>
                            <th style="width: 12%">Razón</th>
                            <th style="width: 20%">Fórmula</th>
                            <?php foreach ($periodos as $p): ?>
                                <th style="width: 5%"><?= $p['anio'] ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $end_total = [];
                        foreach ($periodos as $p) {
                            $pid = $p['id'];
                            $pasivo_total = $valores_balance['TOTAL PASIVO'][$pid] ?? 0;
                            $activo_total = $valores_balance['TOTAL ACTIVO'][$pid] ?? 1; // Evitar división por cero
                            $end_total[$pid] = ($pasivo_total / $activo_total) * 100;
                        }
                        ?>
                        <tr>
                            <td style="width: 12%">Endeudamiento Total</td>
                            <td style="width: 20%">(Pasivo Total / Activo Total) × 100</td>
                            <?php foreach ($periodos as $p): ?>
                                <td style="width: 5%"><?= number_format($end_total[$p['id']], 2) ?>%</td>
                            <?php endforeach; ?>
                        </tr>

                        <?php
                        $cobertura_int = [];
                        foreach ($periodos as $p) {
                            $pid = $p['id'];
                            $utilidad_op = $valores_resultados['Utilidad de Operación'][$pid] ?? 0; // Se ajusta a 0 para que si no hay utilidad operativa, el cálculo sea 0 o N/A
                            $intereses = abs($valores_resultados['Gastos por Intereses'][$pid] ?? $valores_resultados['Resultado Financiero'][$pid] ?? 1); // Evitar división por cero
                            
                            // Asegurarse de que si utilidad_op es 0 y intereses es 0, no divida por cero y de N/A.
                            if ($intereses == 0) {
                                $cobertura_int[$pid] = "N/A"; // O un valor que indique indefinido
                            } else {
                                $cobertura_int[$pid] = $utilidad_op / $intereses;
                            }
                        }
                        ?>
                        <tr>
                            <td style="width: 12%">Cobertura Intereses</td>
                            <td style="width: 20%">Utilidad de Operación / Intereses</td>
                            <?php foreach ($periodos as $p): ?>
                                <td style="width: 5%">
                                    <?php if ($cobertura_int[$p['id']] === "N/A"): ?>
                                        N/A
                                    <?php else: ?>
                                        <?= number_format($cobertura_int[$p['id']], 2) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay datos en el balance general o estado de resultado lo cual no se pueden realizar las operaciones de endeudamiento.</p>
            <?php endif; ?>
        </div>

        <div class="box mt-5">
            <h2 class="subtitle is-5">Razones de Rentabilidad</h2>
            <?php if (!empty($resultados_data)): ?>
                <table class="table is-bordered is-fullwidth">
                    <thead>
                        <tr>
                            <th style="width: 12%">Razón</th>
                            <th style="width: 20%">Fórmula</th>
                            <?php foreach ($periodos as $p): ?>
                                <th style="width: 5%"><?= $p['anio'] ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $margen_bruto = [];
                        foreach ($periodos as $p) {
                            $pid = $p['id'];
                            $util_bruta = $valores_resultados['Utilidad Bruta'][$pid] ?? 0;
                            $ventas = $valores_resultados['Ventas Netas'][$pid] ?? 1; // Evitar división por cero
                            $margen_bruto[$pid] = ($util_bruta / $ventas) * 100;
                        }
                        ?>
                        <tr>
                            <td style="width: 12%">Margen Bruto</td>
                            <td style="width: 20%">(Utilidad Bruta / Ventas) × 100</td>
                            <?php foreach ($periodos as $p): ?>
                                <td style="width: 5%"><?= number_format($margen_bruto[$p['id']], 2) ?>%</td>
                            <?php endforeach; ?>
                        </tr>

                        <?php
                        $margen_op = [];
                        foreach ($periodos as $p) {
                            $pid = $p['id'];
                            $util_op = $valores_resultados['Utilidad de Operación'][$pid] ?? 0;
                            $ventas = $valores_resultados['Ventas Netas'][$pid] ?? 1; // Evitar división por cero
                            $margen_op[$pid] = ($util_op / $ventas) * 100;
                        }
                        ?>
                        <tr>
                            <td style="width: 12%">Margen Operativo</td>
                            <td style="width: 20%">(Utilidad de Operación / Ventas) × 100</td>
                            <?php foreach ($periodos as $p): ?>
                                <td style="width: 5%"><?= number_format($margen_op[$p['id']], 2) ?>%</td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay datos en el estado de resultado lo cual no se pueden realizar las operaciones de rentabilidad.</p>
            <?php endif; ?>
        </div>

    <?php elseif ($empresa_id): ?>
        <div class="notification is-warning mt-4">
            No hay datos suficientes para calcular las razones financieras. Se necesitan al menos 1 período con información de balance y estado de resultados.
        </div>
    <?php endif; ?>
</div>