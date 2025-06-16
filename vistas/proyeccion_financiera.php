<div class="container mt-5">
    <div class="Titulo">
    <h1 class="title is-3">Proyecciones Financieras</h1></div>

    <?php
        define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/FinanzasProyecto/');
        include 'inc/head.php';
        include 'inc/db.php';

        $empresas = $conn->query("SELECT id, nombre FROM empresas")->fetchAll();
        $empresa_id = $_POST['empresa_id'] ?? null;
        $num_periodos_proyectar = $_POST['num_periodos_proyectar'] ?? 3;
        $guardar_proyecciones = isset($_POST['guardar_proyecciones']);

        $periodos_historicos = [];
        $valores_balance_historico = [];
        $valores_resultados_historico = [];

        if ($empresa_id) {
            // Obtener todos los periodos históricos para la empresa
            $periodos_db = $conn->query("SELECT id, YEAR(fecha_inicio) as anio FROM periodos
                                    WHERE empresa_id = $empresa_id ORDER BY fecha_inicio ASC")->fetchAll();
            
            if (!empty($periodos_db)) {
                foreach($periodos_db as $p) {
                    $periodos_historicos[$p['id']] = $p['anio'];

                    // Obtener datos de balance
                    $balance_data = $conn->query("
                        SELECT cc.id as cuenta_id, cc.nombre as cuenta, cb.valor
                        FROM cuentas_balance cb
                        JOIN catalogo_cuentas cc ON cb.cuenta_id = cc.id
                        WHERE cb.periodo_id = {$p['id']}
                    ")->fetchAll();
                    foreach ($balance_data as $row) {
                        $valores_balance_historico[$row['cuenta']][$p['id']] = [
                            'valor' => $row['valor'],
                            'cuenta_id' => $row['cuenta_id']
                        ];
                    }

                    // Obtener datos de estado de resultados
                    $resultados_data = $conn->query("
                        SELECT cc.id as cuenta_id, cc.nombre as cuenta, cr.valor
                        FROM cuentas_resultados cr
                        JOIN catalogo_cuentas cc ON cr.cuenta_id = cc.id
                        WHERE cr.periodo_id = {$p['id']}
                    ")->fetchAll();
                    foreach ($resultados_data as $row) {
                        $valores_resultados_historico[$row['cuenta']][$p['id']] = [
                            'valor' => $row['valor'],
                            'cuenta_id' => $row['cuenta_id']
                        ];
                    }
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

        <?php if ($empresa_id): ?>
                <h2 class="subtitle is-5">Configuración de Proyecciones</h2>
                <div class="field">
                    <label class="label">Número de Años a Proyectar</label>
                    <div class="control" style="width: 160px;">
                        <input class="input" type="number" name="num_periodos_proyectar" value="<?= htmlspecialchars($num_periodos_proyectar) ?>" min="1" max="5">
                    </div>
                </div>
                <div class="control mt-3">
                    <button class="button is-primary" type="submit" name="generar_proyecciones">Generar Proyecciones</button>
                </div>

            <?php
            if ($empresa_id && !empty($periodos_historicos)) {
                // Obtener el último período histórico
                $ultimo_periodo_id = array_key_last($periodos_historicos);
                $ultimo_anio_historico = $periodos_historicos[$ultimo_periodo_id];

                // Obtener valores históricos
                $ventas_netas_ultimo = $valores_resultados_historico['Ventas Netas'][$ultimo_periodo_id]['valor'] ?? 0;
                $ventas_netas_cuenta_id = $valores_resultados_historico['Ventas Netas'][$ultimo_periodo_id]['cuenta_id'] ?? 0;
                $costo_ventas_ultimo = $valores_resultados_historico['Costo de Ventas'][$ultimo_periodo_id]['valor'] ?? 0;
                $costo_ventas_cuenta_id = $valores_resultados_historico['Costo de Ventas'][$ultimo_periodo_id]['cuenta_id'] ?? 0;
                $gastos_operacion_ultimo = $valores_resultados_historico['Gastos de Operación'][$ultimo_periodo_id]['valor'] ?? 0;
                $gastos_operacion_cuenta_id = $valores_resultados_historico['Gastos de Operación'][$ultimo_periodo_id]['cuenta_id'] ?? 0;
                $utilidad_neta_ultimo = $valores_resultados_historico['Utilidad Neta'][$ultimo_periodo_id]['valor'] ?? 0;
                $utilidad_neta_cuenta_id = $valores_resultados_historico['Utilidad Neta'][$ultimo_periodo_id]['cuenta_id'] ?? 0;
                
                $cuentas_cobrar_ultimo = $valores_balance_historico['Cuentas por Cobrar'][$ultimo_periodo_id]['valor'] ?? 0;
                $cuentas_cobrar_cuenta_id = $valores_balance_historico['Cuentas por Cobrar'][$ultimo_periodo_id]['cuenta_id'] ?? 0;
                $inventarios_ultimo = $valores_balance_historico['Inventarios'][$ultimo_periodo_id]['valor'] ?? 0;
                $inventarios_cuenta_id = $valores_balance_historico['Inventarios'][$ultimo_periodo_id]['cuenta_id'] ?? 0;
                $cuentas_pagar_ultimo = $valores_balance_historico['Cuentas por Pagar'][$ultimo_periodo_id]['valor'] ?? 0;
                $cuentas_pagar_cuenta_id = $valores_balance_historico['Cuentas por Pagar'][$ultimo_periodo_id]['cuenta_id'] ?? 0;
                $efectivo_ultimo = $valores_balance_historico['Efectivo y Equivalentes'][$ultimo_periodo_id]['valor'] ?? 0;
                $efectivo_cuenta_id = $valores_balance_historico['Efectivo y Equivalentes'][$ultimo_periodo_id]['cuenta_id'] ?? 0;

                // Calcular tasa de crecimiento de ventas basada en el historial
                $crecimiento_ventas_calc = 0;
                if (count($periodos_historicos) >= 2) {
                    $periodos_ids = array_keys($periodos_historicos);
                    $penultimo_periodo_id = $periodos_ids[count($periodos_ids) - 2];
                    $ventas_netas_penultimo = $valores_resultados_historico['Ventas Netas'][$penultimo_periodo_id]['valor'] ?? 0;
                    
                    if ($ventas_netas_penultimo > 0) {
                        $crecimiento_ventas_calc = ($ventas_netas_ultimo - $ventas_netas_penultimo) / $ventas_netas_penultimo;
                    }
                }

                // Calcular porcentajes históricos para la proyección
                $pct_costo_ventas = ($ventas_netas_ultimo == 0) ? 0 : ($costo_ventas_ultimo / $ventas_netas_ultimo);
                $pct_gastos_operacion = ($ventas_netas_ultimo == 0) ? 0 : ($gastos_operacion_ultimo / $ventas_netas_ultimo);
                $pct_utilidad_neta = ($ventas_netas_ultimo == 0) ? 0 : ($utilidad_neta_ultimo / $ventas_netas_ultimo);
                $pct_ctas_cobrar = ($ventas_netas_ultimo == 0) ? 0 : ($cuentas_cobrar_ultimo / $ventas_netas_ultimo);
                $pct_inventarios = ($costo_ventas_ultimo == 0) ? 0 : ($inventarios_ultimo / $costo_ventas_ultimo);
                $compras_ultimo = $valores_resultados_historico['Compras'][$ultimo_periodo_id]['valor'] ?? $costo_ventas_ultimo;
                $pct_ctas_pagar = ($compras_ultimo == 0) ? 0 : ($cuentas_pagar_ultimo / $compras_ultimo);
                $pct_efectivo = ($ventas_netas_ultimo == 0) ? 0 : ($efectivo_ultimo / $ventas_netas_ultimo);

                // Inicializar arrays para proyecciones
                $proyecciones_resultados = [];
                $proyecciones_balance = [];

                // Llenar con el último período histórico como base
                $proyecciones_resultados['Ventas Netas'][$ultimo_anio_historico] = [
                    'valor' => $ventas_netas_ultimo,
                    'cuenta_id' => $ventas_netas_cuenta_id,
                    'tipo' => 'historico'
                ];
                $proyecciones_resultados['Costo de Ventas'][$ultimo_anio_historico] = [
                    'valor' => $costo_ventas_ultimo,
                    'cuenta_id' => $costo_ventas_cuenta_id,
                    'tipo' => 'historico'
                ];
                $proyecciones_resultados['Gastos de Operación'][$ultimo_anio_historico] = [
                    'valor' => $gastos_operacion_ultimo,
                    'cuenta_id' => $gastos_operacion_cuenta_id,
                    'tipo' => 'historico'
                ];
                $proyecciones_resultados['Utilidad Neta'][$ultimo_anio_historico] = [
                    'valor' => $utilidad_neta_ultimo,
                    'cuenta_id' => $utilidad_neta_cuenta_id,
                    'tipo' => 'historico'
                ];

                $proyecciones_balance['Cuentas por Cobrar'][$ultimo_anio_historico] = [
                    'valor' => $cuentas_cobrar_ultimo,
                    'cuenta_id' => $cuentas_cobrar_cuenta_id,
                    'tipo' => 'historico'
                ];
                $proyecciones_balance['Inventarios'][$ultimo_anio_historico] = [
                    'valor' => $inventarios_ultimo,
                    'cuenta_id' => $inventarios_cuenta_id,
                    'tipo' => 'historico'
                ];
                $proyecciones_balance['Cuentas por Pagar'][$ultimo_anio_historico] = [
                    'valor' => $cuentas_pagar_ultimo,
                    'cuenta_id' => $cuentas_pagar_cuenta_id,
                    'tipo' => 'historico'
                ];
                $proyecciones_balance['Efectivo y Equivalentes'][$ultimo_anio_historico] = [
                    'valor' => $efectivo_ultimo,
                    'cuenta_id' => $efectivo_cuenta_id,
                    'tipo' => 'historico'
                ];

                // Generar proyecciones
                $anio_actual = $ultimo_anio_historico;
                for ($i = 1; $i <= $num_periodos_proyectar; $i++) {
                    $anio_proyectado = $anio_actual + 1;

                    // Proyectar Estado de Resultados
                    $proyecciones_resultados['Ventas Netas'][$anio_proyectado] = [
                        'valor' => $proyecciones_resultados['Ventas Netas'][$anio_actual]['valor'] * (1 + $crecimiento_ventas_calc),
                        'cuenta_id' => $ventas_netas_cuenta_id,
                        'tipo' => 'proyectado'
                    ];
                    $proyecciones_resultados['Costo de Ventas'][$anio_proyectado] = [
                        'valor' => $proyecciones_resultados['Ventas Netas'][$anio_proyectado]['valor'] * $pct_costo_ventas,
                        'cuenta_id' => $costo_ventas_cuenta_id,
                        'tipo' => 'proyectado'
                    ];
                    $proyecciones_resultados['Gastos de Operación'][$anio_proyectado] = [
                        'valor' => $proyecciones_resultados['Ventas Netas'][$anio_proyectado]['valor'] * $pct_gastos_operacion,
                        'cuenta_id' => $gastos_operacion_cuenta_id,
                        'tipo' => 'proyectado'
                    ];
                    // Calcular Utilidad Neta proyectada (Ventas Netas - Costo Ventas - Gastos Operación)
                    $utilidad_neta_proyectada = $proyecciones_resultados['Ventas Netas'][$anio_proyectado]['valor'] 
                                             - $proyecciones_resultados['Costo de Ventas'][$anio_proyectado]['valor']
                                             - $proyecciones_resultados['Gastos de Operación'][$anio_proyectado]['valor'];
                    
                    $proyecciones_resultados['Utilidad Neta'][$anio_proyectado] = [
                        'valor' => $utilidad_neta_proyectada,
                        'cuenta_id' => $utilidad_neta_cuenta_id,
                        'tipo' => 'proyectado'
                    ];

                    // Proyectar Balance General
                    $proyecciones_balance['Cuentas por Cobrar'][$anio_proyectado] = [
                        'valor' => $proyecciones_resultados['Ventas Netas'][$anio_proyectado]['valor'] * $pct_ctas_cobrar,
                        'cuenta_id' => $cuentas_cobrar_cuenta_id,
                        'tipo' => 'proyectado'
                    ];
                    $proyecciones_balance['Inventarios'][$anio_proyectado] = [
                        'valor' => $proyecciones_resultados['Costo de Ventas'][$anio_proyectado]['valor'] * $pct_inventarios,
                        'cuenta_id' => $inventarios_cuenta_id,
                        'tipo' => 'proyectado'
                    ];
                    $proyecciones_balance['Cuentas por Pagar'][$anio_proyectado] = [
                        'valor' => $proyecciones_resultados['Costo de Ventas'][$anio_proyectado]['valor'] * $pct_ctas_pagar,
                        'cuenta_id' => $cuentas_pagar_cuenta_id,
                        'tipo' => 'proyectado'
                    ];
                    $proyecciones_balance['Efectivo y Equivalentes'][$anio_proyectado] = [
                        'valor' => $proyecciones_resultados['Ventas Netas'][$anio_proyectado]['valor'] * $pct_efectivo,
                        'cuenta_id' => $efectivo_cuenta_id,
                        'tipo' => 'proyectado'
                    ];

                    $anio_actual = $anio_proyectado;
                }

                // Mostrar Proyecciones del Estado de Resultados
                echo '<div class="box mt-5">';
                echo '<h2 class="subtitle is-5">Estado de Resultados Proyectado</h2>';
                echo '<table class="table is-bordered is-fullwidth">';
                echo '<thead><tr><th>Cuenta</th><th>'.$ultimo_anio_historico.' (Histórico)</th>';
                for ($i = 1; $i <= $num_periodos_proyectar; $i++) {
                    echo '<th>'.($ultimo_anio_historico + $i).' (Proyectado)</th>';
                }
                echo '</tr></thead><tbody>';
                
                $cuentas_er_proyectar = ['Ventas Netas', 'Costo de Ventas', 'Gastos de Operación', 'Utilidad Neta'];
                foreach($cuentas_er_proyectar as $cuenta_nombre) {
                    echo '<tr><td>'.htmlspecialchars($cuenta_nombre).'</td>';
                    echo '<td>'.number_format($proyecciones_resultados[$cuenta_nombre][$ultimo_anio_historico]['valor'] ?? 0, 2).'</td>';
                    for ($i = 1; $i <= $num_periodos_proyectar; $i++) {
                        echo '<td>'.number_format($proyecciones_resultados[$cuenta_nombre][($ultimo_anio_historico + $i)]['valor'] ?? 0, 2).'</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';

                // Mostrar Proyecciones del Balance General
                echo '<div class="box mt-5">';
                echo '<h2 class="subtitle is-5">Balance General Proyectado</h2>';
                echo '<table class="table is-bordered is-fullwidth">';
                echo '<thead><tr><th>Cuenta</th><th>'.$ultimo_anio_historico.' (Histórico)</th>';
                for ($i = 1; $i <= $num_periodos_proyectar; $i++) {
                    echo '<th>'.($ultimo_anio_historico + $i).' (Proyectado)</th>';
                }
                echo '</tr></thead><tbody>';
                
                $cuentas_balance_proyectar = ['Efectivo y Equivalentes', 'Cuentas por Cobrar', 'Inventarios', 'Cuentas por Pagar'];
                foreach($cuentas_balance_proyectar as $cuenta_nombre) {
                    echo '<tr><td>'.htmlspecialchars($cuenta_nombre).'</td>';
                    echo '<td>'.number_format($proyecciones_balance[$cuenta_nombre][$ultimo_anio_historico]['valor'] ?? 0, 2).'</td>';
                    for ($i = 1; $i <= $num_periodos_proyectar; $i++) {
                        echo '<td>'.number_format($proyecciones_balance[$cuenta_nombre][($ultimo_anio_historico + $i)]['valor'] ?? 0, 2).'</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';

                // Botón para guardar proyecciones
                echo '<form method="post">';
                echo '<input type="hidden" name="empresa_id" value="'.$empresa_id.'">';
                echo '<input type="hidden" name="num_periodos_proyectar" value="'.$num_periodos_proyectar.'">';
                echo '<div class="control mt-3">';
                echo '<button class="button is-success" type="submit" name="guardar_proyecciones">Guardar Proyecciones</button>';
                echo '</div>';
                echo '</form>';

                // Guardar proyecciones si se hizo clic en el botón
                if ($guardar_proyecciones) {
                    try {
                        $conn->beginTransaction();
                        
                        // Borrar proyecciones anteriores para esta empresa
                        $conn->query("DELETE FROM proyecciones_resultados WHERE empresa_id = $empresa_id");
                        $conn->query("DELETE FROM proyecciones_balance WHERE empresa_id = $empresa_id");
                        
                        // Guardar proyecciones de resultados
                        foreach ($proyecciones_resultados as $cuenta_nombre => $anios) {
                            foreach ($anios as $anio => $datos) {
                                $stmt = $conn->prepare("INSERT INTO proyecciones_resultados 
                                    (empresa_id, cuenta_id, anio, valor, tipo) 
                                    VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    $empresa_id,
                                    $datos['cuenta_id'],
                                    $anio,
                                    $datos['valor'],
                                    $datos['tipo']
                                ]);
                            }
                        }
                        
                        // Guardar proyecciones de balance
                        foreach ($proyecciones_balance as $cuenta_nombre => $anios) {
                            foreach ($anios as $anio => $datos) {
                                $stmt = $conn->prepare("INSERT INTO proyecciones_balance 
                                    (empresa_id, cuenta_id, anio, valor, tipo) 
                                    VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    $empresa_id,
                                    $datos['cuenta_id'],
                                    $anio,
                                    $datos['valor'],
                                    $datos['tipo']
                                ]);
                            }
                        }
                        
                        $conn->commit();
                        echo '<div class="notification is-success mt-4">Proyecciones guardadas correctamente.</div>';
                    } catch (PDOException $e) {
                        $conn->rollBack();
                        echo '<div class="notification is-danger mt-4">Error al guardar proyecciones: '.$e->getMessage().'</div>';
                    }
                }

            } elseif ($empresa_id) {
                echo '<div class="notification is-warning mt-4">No hay suficientes datos históricos para generar proyecciones. Necesita al menos un período con datos de balance y estado de resultados.</div>';
            }
            ?>
        <?php endif; ?>
    </form>
</div>