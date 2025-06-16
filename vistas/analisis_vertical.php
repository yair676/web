<div class="container mt-5">
    <h1 class="title is-3">Análisis Vertical</h1>

    <?php
        define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/FinanzasProyecto/');
        include 'inc/head.php';
        include 'inc/db.php';

        // Obtener todas las empresas
        $empresas = $conn->query("SELECT id, nombre FROM empresas")->fetchAll();

        // Si se seleccionó una empresa
        $empresa_id = $_POST['empresa_id'] ?? null;
        $cuentas_balance = [];
        $cuentas_resultados = [];   
        $periodos = [];
        $valores_balance = [];
        $valores_resultados = [];

        if ($empresa_id) {
            // Obtener periodos para la empresa seleccionada, ordenados por fecha
            $periodos = $conn->query("
                SELECT id, YEAR(fecha_inicio) as anio 
                FROM periodos 
                WHERE empresa_id = $empresa_id 
                ORDER BY fecha_inicio
            ")->fetchAll();

            if (count($periodos) >= 1) { // Para análisis vertical solo necesitamos 1 período
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
                    $valores_balance[$cuenta['cuenta']] = [
                        'tipo' => $cuenta['tipo'],
                        'valores' => []
                    ];

                    $stmt = $conn->prepare("
                        SELECT cb.periodo_id, cb.valor 
                        FROM cuentas_balance cb
                        JOIN catalogo_cuentas cc ON cb.cuenta_id = cc.id
                        WHERE cc.nombre = ? AND cb.periodo_id IN (" . implode(',', $periodo_ids) . ")
                    ");

                    $stmt->execute([$cuenta['cuenta']]);
                    $balance_vals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                    foreach ($periodo_ids as $pid) {
                        $valores_balance[$cuenta['cuenta']]['valores'][$pid] = $balance_vals[$pid] ?? 0;
                    }
                }

                // Procesar cuentas de resultados
                foreach ($cuentas_resultados as $cuenta) {
                    $valores_resultados[$cuenta['cuenta']] = [
                        'tipo' => $cuenta['tipo'],
                        'valores' => []
                    ];

                    $stmt = $conn->prepare("
                        SELECT cr.periodo_id, cr.valor 
                        FROM cuentas_resultados cr
                        JOIN catalogo_cuentas cc ON cr.cuenta_id = cc.id
                        WHERE cc.nombre = ? AND cr.periodo_id IN (" . implode(',', $periodo_ids) . ")
                    ");

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
    
    <?php if ($empresa_id && count($periodos) >= 1): ?>

        <div class="box mt-5">
            <!-- Sección Balance General -->
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

            <!-- Análisis Vertical del Balance General (%) -->
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
        </div>

        <div class="box mt-5">
            <!-- Sección Estado de Resultados -->
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

            <!-- Análisis Vertical del Estado de Resultados -->
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
    <?php elseif ($empresa_id): ?>
        <div class="notification is-warning">
            Se necesita al menos 1 período para realizar el análisis vertical.
        </div>
    <?php endif; ?>
</div>