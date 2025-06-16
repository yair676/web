<div class="container mt-5">
    <h1 class="title is-3">Gestión de Cuentas por Pagar</h1>

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
                <h2 class="subtitle is-5">Período Promedio de Pago</h2>
                <?php if (!empty($resultados_data) && isset($valores_balance['Cuentas por Pagar'])): ?>
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
                                foreach ($periodos as $p) {
                                    $pid = $p['id'];
                                    // Para las compras, si no está disponible directamente, usa Costo de Ventas
                                    $compras = $valores_resultados['Compras'][$pid] ?? ($valores_resultados['Costo de Ventas'][$pid] ?? 1); 
                                    $ctas_pagar_actual = $valores_balance['Cuentas por Pagar'][$pid] ?? 0;
                                    
                                    $ppp[$pid] = ($ctas_pagar_actual / ($compras == 0 ? 1 : $compras)) * 365; // Evitar división por cero
                                }
                            ?>
                            <tr>
                                <td>(Cuentas por Pagar / Costo de Ventas) × 365</td>
                                <?php foreach ($periodos as $p): ?>
                                    <td>
                                        <?= number_format($ppp[$p['id']], 2) ?> días
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No hay datos suficientes (Cuentas por Pagar o Compras/Costo de Ventas) para calcular el Período Promedio de Pago.</p>
                <?php endif; ?>
            </div>

            <div class="box mt-5">
                <h2 class="subtitle is-5">Rotación de Cuentas por Pagar</h2>
                <?php if (!empty($resultados_data) && isset($valores_balance['Cuentas por Pagar'])): ?>
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
                                $rotacion_cp = [];
                                foreach ($periodos as $p) {
                                    $pid = $p['id'];
                                    $compras = $valores_resultados['Compras'][$pid] ?? ($valores_resultados['Costo de Ventas'][$pid] ?? 0);
                                    $ctas_pagar_actual = $valores_balance['Cuentas por Pagar'][$pid] ?? 0;
                                    $ctas_pagar_promedio = $ctas_pagar_actual;

                                    $rotacion_cp[$pid] = ($ctas_pagar_promedio == 0 ? 0 : ($compras / $ctas_pagar_promedio)); // Evitar división por cero
                                }
                            ?>
                            <tr>
                                <td>Costo de Ventas / Cuentas por Pagar</td>
                                <?php foreach ($periodos as $p): ?>
                                    <td>
                                        <?= number_format($rotacion_cp[$p['id']], 2) ?> veces
                                    </td>
                                <?php endforeach; ?>
                            </tr> 
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No hay datos suficientes para calcular la Rotación de Cuentas por Pagar.</p>
                <?php endif; ?>
            </div>
        <?php elseif ($empresa_id): ?>
            <div class="notification is-warning mt-4">
                No hay datos suficientes para calcular las razones financieras. Se necesitan al menos 1 período con información de balance y estado de resultados.
            </div>
        <?php endif; ?>
    </form>
</div>