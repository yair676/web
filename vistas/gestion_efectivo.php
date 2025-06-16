<div class="container mt-5">
    <h1 class="title is-3">Gestion de efectivo</h1>

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
                    <option value="<?= $empresa['id'] ?>" <?= (isset($empresa_id) && $empresa_id == $empresa['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($empresa['nombre']) ?>
                    </option>
                <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if ($empresa_id && !empty($periodos)): ?>
            <div class="box mt-5">
                <h2 class="subtitle is-5">Periodo promedio de inventario</h2>
                <?php if (!empty($resultados_data) && isset($valores_balance['Inventarios']) && isset($valores_resultados['Costo de Ventas'])): ?>

                    <table class="table is-bordered is-fullwidth table-fixed-width">
                        <thead>
                            <tr>
                                <th style="width: 25%">Fórmula</th>
                                <?php foreach ($periodos as $p): ?>
                                    <th style="width: 7%"><?= htmlspecialchars($p['anio']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $ppi = [];
                                foreach ($periodos as $index => $p) {
                                    $pid = $p['id'];
                                    $costo_ventas = $valores_resultados['Costo de Ventas'][$pid] ?? 0;
                                    $inventario_actual = $valores_balance['Inventarios'][$pid] ?? 0;

                                    // Calcular promedio de inventario
                                    $inventario_promedio = 0;
                                    if ($index == 0) {
                                        // Para el primer período, si solo tenemos el valor final, lo usamos como promedio.
                                        $inventario_promedio = $inventario_actual;
                                    } else {
                                        $pid_anterior = $periodos[$index-1]['id'];
                                        $inventario_anterior = $valores_balance['Inventarios'][$pid_anterior] ?? 0;
                                        $inventario_promedio = ($inventario_anterior + $inventario_actual) / 2;
                                    }
                                    
                                    // Calcular PPI
                                    // Si el costo de ventas es 0, no se puede calcular un PPI significativo
                                    if ($costo_ventas == 0) {
                                        $ppi[$pid] = 0; // Se establece a 0 para que la condición de N/A lo capture
                                    } else {
                                        $ppi[$pid] = ($inventario_promedio / $costo_ventas) * 365;
                                    }
                                }
                            ?>
                            <tr>
                                <td>(Inventario Promedio / Costo de Ventas) × 365</td>
                                <?php foreach ($periodos as $index => $p): ?>
                                    <td>
                                        <?php
                                            // Se simplifica la condición para N/A. Si el valor calculado de PPI es 0,
                                            // se mostrará N/A. Esto ocurre si Inventario Promedio o Costo de Ventas fue 0.
                                            if ($ppi[$p['id']] == 0):
                                        ?>
                                            N/A
                                        <?php else: ?>
                                            <?= number_format($ppi[$p['id']], 1) ?> días
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No hay datos suficientes (Inventarios o Costo de Ventas) para calcular el Periodo Promedio de Inventario.</p>
                <?php endif; ?>
            </div>

            <div class="box mt-5">
                <h2 class="subtitle is-5">Periodo promedio de cobro</h2>
                <?php if (!empty($resultados_data) && isset($valores_balance['Cuentas por Cobrar']) && isset($valores_resultados['Ventas Netas'])): ?>

                    <table class="table is-bordered is-fullwidth table-fixed-width">
                        <thead>
                            <tr>
                                <th style="width: 25%">Fórmula</th>
                                <?php foreach ($periodos as $p): ?>
                                    <th style="width: 7%"><?= htmlspecialchars($p['anio']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $ppc = [];
                                    foreach ($periodos as $index => $p) {
                                        $pid = $p['id'];
                                        $ventas = $valores_resultados['Ventas Netas'][$pid] ?? 0;
                                        $ctas_cobrar_actual = $valores_balance['Cuentas por Cobrar'][$pid] ?? 0;
                                        
                                        // Aplicar la fórmula directa: (Cuentas por Cobrar / Ventas Netas) * 365
                                        if ($ventas == 0) {
                                            $ppc[$pid] = 0;
                                        } else {
                                            $ppc[$pid] = ($ctas_cobrar_actual / $ventas) * 365;
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>(Cuentas por Cobrar / Ventas Netas) × 365</td>
                                    <?php foreach ($periodos as $p): ?>
                                        <td>
                                            <?php if ($ppc[$p['id']] == 0): // Simplificado ?>
                                                N/A
                                            <?php else: ?>
                                                <?= number_format($ppc[$p['id']], 2) ?> días
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No hay datos suficientes (Cuentas por Cobrar o Ventas Netas) para calcular el Periodo Promedio de Cobro.</p>
                <?php endif; ?>
            </div>

            <div class="box mt-5">
                <h2 class="subtitle is-5">Periodo promedio de pago</h2>
                <?php if (!empty($resultados_data) && isset($valores_balance['Cuentas por Pagar']) && (isset($valores_resultados['Compras']) || isset($valores_resultados['Costo de Ventas']))): ?>

                    <table class="table is-bordered is-fullwidth table-fixed-width">
                        <thead>
                            <tr>
                                <th style="width: 25%">Fórmula</th>
                                <?php foreach ($periodos as $p): ?>
                                    <th style="width: 7%"><?= htmlspecialchars($p['anio']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $ppp = [];
                                foreach ($periodos as $index => $p) {
                                    $pid = $p['id'];
                                    $compras_o_costo_ventas = $valores_resultados['Compras'][$pid] ?? ($valores_resultados['Costo de Ventas'][$pid] ?? 0);
                                    $ctas_pagar_actual = $valores_balance['Cuentas por Pagar'][$pid] ?? 0;

                                    if ($compras_o_costo_ventas == 0) {
                                        $ppp[$pid] = 0;
                                    } else {
                                        $ppp[$pid] = ($ctas_pagar_actual / $compras_o_costo_ventas) * 365;
                                    }
                                }
                            ?>
                            <tr>
                                <td>(Cuentas por Pagar / Compras o Costo de Ventas) × 365</td>
                                <?php foreach ($periodos as $p): ?>
                                    <td>
                                        <?php if ($ppp[$p['id']] == 0): // Simplificado ?>
                                            N/A
                                        <?php else: ?>
                                            <?= number_format($ppp[$p['id']], 2) ?> días
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No hay datos suficientes (Cuentas por Pagar o Compras/Costo de Ventas) para calcular el Periodo Promedio de Pago.</p>
                <?php endif; ?>
            </div>

            <div class="box mt-5">
                <h2 class="subtitle is-5">Ciclo Operativo</h2>
                <?php if (!empty($resultados_data) && isset($ppi) && isset($ppc)): ?>

                    <table class="table is-bordered is-fullwidth table-fixed-width">
                        <thead>
                            <tr>
                                <th style="width: 25%">Fórmula</th>
                                <?php foreach ($periodos as $p): ?>
                                    <th style="width: 7%"><?= htmlspecialchars($p['anio']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $co = [];
                                foreach ($periodos as $index => $p) {
                                    $pid = $p['id'];
                                    $co[$pid] = ($ppi[$pid] ?? 0) + ($ppc[$pid] ?? 0); 
                                }
                            ?>
                            <tr>
                                <td>Periodo promedio de inventario (PPI) + Periodo promedio de cobro (PPC)</td>
                                <?php foreach ($periodos as $p): ?>
                                    <td>
                                        <?php if (($ppi[$p['id']] ?? 0) == 0 && ($ppc[$p['id']] ?? 0) == 0): ?>
                                            N/A
                                        <?php else: ?>
                                            <?= number_format($co[$p['id']], 2) ?> días
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No hay datos suficientes (Periodo promedio de inventario o Periodo promedio de cobro) para calcular el Ciclo Operativo.</p>
                <?php endif; ?>
            </div>

            <div class="box mt-5">
                <h2 class="subtitle is-5">Ciclo de conversión de efectivo</h2>
                <?php if (!empty($resultados_data) && isset($co) && isset($ppp)): ?>

                    <table class="table is-bordered is-fullwidth table-fixed-width">
                        <thead>
                            <tr>
                                <th style="width: 25%">Fórmula</th>
                                <?php foreach ($periodos as $p): ?>
                                    <th style="width: 7%"><?= htmlspecialchars($p['anio']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $cce = [];
                                foreach ($periodos as $index => $p) {
                                    $pid = $p['id'];
                                    $cce[$pid] = ($co[$pid] ?? 0) - ($ppp[$pid] ?? 0); 
                                }
                            ?>
                            <tr>
                                <td>Ciclo operativo (CO) - Periodo promedio de pago (PPP)</td>
                                <?php foreach ($periodos as $p): ?>
                                    <td>
                                        <?php if (($co[$p['id']] ?? 0) == 0 && ($ppp[$p['id']] ?? 0) == 0): ?>
                                            N/A
                                        <?php else: ?>
                                            <?= number_format($cce[$p['id']], 2) ?> días
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No hay datos suficientes (Ciclo Operativo o Periodo Promedio de Pago) para calcular el Ciclo de Conversión de Efectivo.</p>
                <?php endif; ?>
            </div>

        <?php elseif ($empresa_id): ?>
        <div class="notification is-warning mt-4">
            No hay datos suficientes para calcular las razones financieras. Se necesitan al menos 1 período con información de balance y estado de resultados.
        </div>
    <?php endif; ?>
    </form>
</div>