    <div class="container mt-5">
    <h1 class="title is-3">Simulación "What-If" del Capital de Trabajo</h1>

    <?php
        define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/FinanzasProyecto/');
        include 'inc/head.php';
        include 'inc/db.php';

        // Determinar si es un envío para guardar o para calcular
        $guardar_escenario = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_escenario']));
        $calcular_escenario = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calcular_escenario']));

        // Procesar guardado de escenario
        if ($guardar_escenario) {
            try {
                $empresa_id = $_POST['empresa_id'];
                $periodo_base_id = $_POST['periodo_base_id'];
                $mod_ppc = !empty($_POST['mod_ppc']) ? $_POST['mod_ppc'] : null;
                $mod_ppi = !empty($_POST['mod_ppi']) ? $_POST['mod_ppi'] : null;
                $mod_ppp = !empty($_POST['mod_ppp']) ? $_POST['mod_ppp'] : null;
                $mod_ventas_crecimiento = !empty($_POST['mod_ventas_crecimiento']) ? $_POST['mod_ventas_crecimiento'] : null;
                
                // Validar que todos los campos requeridos están presentes
                $required_fields = [
                    'cce_base', 'cce_whatif', 'razon_corriente_base', 'razon_corriente_whatif',
                    'capital_neto_base', 'capital_neto_whatif', 'prueba_acida_base', 'prueba_acida_whatif',
                    'efectivo_base', 'efectivo_whatif', 'ctas_cobrar_base', 'ctas_cobrar_whatif',
                    'inventarios_base', 'inventarios_whatif', 'ctas_pagar_base', 'ctas_pagar_whatif',
                    'co_base', 'co_whatif', 'ppi_base', 'ppi_whatif', 'ppc_base', 'ppc_whatif',
                    'ppp_base', 'ppp_whatif'
                ];
                
                foreach ($required_fields as $field) {
                    if (!isset($_POST[$field])) {
                        throw new Exception("Falta el campo requerido: $field");
                    }
                }

                // Insertar en la base de datos
                $stmt = $conn->prepare("INSERT INTO escenarios_whatif (
                    empresa_id, periodo_base_id, mod_ppc, mod_ppi, mod_ppp, mod_ventas_crecimiento,
                    cce_base, cce_whatif, razon_corriente_base, razon_corriente_whatif,
                    capital_neto_base, capital_neto_whatif, prueba_acida_base, prueba_acida_whatif,
                    efectivo_base, efectivo_whatif, ctas_cobrar_base, ctas_cobrar_whatif,
                    inventarios_base, inventarios_whatif, ctas_pagar_base, ctas_pagar_whatif,
                    co_base, co_whatif, ppi_base, ppi_whatif, ppc_base, ppc_whatif, ppp_base, ppp_whatif
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $empresa_id,
                    $periodo_base_id,
                    $mod_ppc,
                    $mod_ppi,
                    $mod_ppp,
                    $mod_ventas_crecimiento,
                    $_POST['cce_base'],
                    $_POST['cce_whatif'],
                    $_POST['razon_corriente_base'],
                    $_POST['razon_corriente_whatif'],
                    $_POST['capital_neto_base'],
                    $_POST['capital_neto_whatif'],
                    $_POST['prueba_acida_base'],
                    $_POST['prueba_acida_whatif'],
                    $_POST['efectivo_base'],
                    $_POST['efectivo_whatif'],
                    $_POST['ctas_cobrar_base'],
                    $_POST['ctas_cobrar_whatif'],
                    $_POST['inventarios_base'],
                    $_POST['inventarios_whatif'],
                    $_POST['ctas_pagar_base'],
                    $_POST['ctas_pagar_whatif'],
                    $_POST['co_base'],
                    $_POST['co_whatif'],
                    $_POST['ppi_base'],
                    $_POST['ppi_whatif'],
                    $_POST['ppc_base'],
                    $_POST['ppc_whatif'],
                    $_POST['ppp_base'],
                    $_POST['ppp_whatif']
                ]);
                
                echo '<div class="notification is-success">Escenario guardado correctamente.</div>';
            } catch (Exception $e) {
                echo '<div class="notification is-danger">Error al guardar el escenario: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }

        // Obtener valores del formulario (para mantenerlos después del envío)
        $empresas = $conn->query("SELECT id, nombre FROM empresas")->fetchAll();
        $empresa_id = $_POST['empresa_id'] ?? null;
        $periodo_base_id = $_POST['periodo_base_id'] ?? null;
        
        // Mantener los valores de los campos de entrada
        $mod_ppc = $_POST['mod_ppc'] ?? null;
        $mod_ppi = $_POST['mod_ppi'] ?? null;
        $mod_ppp = $_POST['mod_ppp'] ?? null;
        $mod_ventas_crecimiento = $_POST['mod_ventas_crecimiento'] ?? null;

        $periodos = [];
        $valores_balance_base = [];
        $valores_resultados_base = [];
        $periodo_base_anio = '';

        if ($empresa_id && $periodo_base_id) {
            $periodos_db = $conn->query("SELECT id, YEAR(fecha_inicio) as anio FROM periodos WHERE empresa_id = $empresa_id ORDER BY fecha_inicio")->fetchAll();
            foreach ($periodos_db as $p) {
                if ($p['id'] == $periodo_base_id) {
                    $periodo_base_anio = $p['anio'];
                    break;
                }
            }

            $balance_data_base = $conn->query("
                SELECT cc.nombre as cuenta, cb.valor
                FROM cuentas_balance cb
                JOIN catalogo_cuentas cc ON cb.cuenta_id = cc.id
                WHERE cb.periodo_id = $periodo_base_id
            ")->fetchAll();
            foreach ($balance_data_base as $row) {
                $valores_balance_base[$row['cuenta']] = $row['valor'];
            }

            $resultados_data_base = $conn->query("
                SELECT cc.nombre as cuenta, cr.valor
                FROM cuentas_resultados cr
                JOIN catalogo_cuentas cc ON cr.cuenta_id = cc.id
                WHERE cr.periodo_id = $periodo_base_id
            ")->fetchAll();
            foreach ($resultados_data_base as $row) {
                $valores_resultados_base[$row['cuenta']] = $row['valor'];
            }
        }
    ?>

    <form method="post" class="form-wide" id="formPrincipal">
        <div class="field">
            <label class="label">Seleccionar Empresa</label>
            <div class="select">
                <select name="empresa_id" onchange="document.getElementById('formPrincipal').submit()" required>
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
            <div class="field">
                <label class="label">Seleccionar Período Base</label>
                <div class="select">
                    <select name="periodo_base_id" onchange="document.getElementById('formPrincipal').submit()" required>
                        <option value="">Seleccione un año...</option>
                        <?php 
                            $periodos_select = $conn->query("SELECT id, YEAR(fecha_inicio) as anio FROM periodos WHERE empresa_id = $empresa_id ORDER BY fecha_inicio DESC")->fetchAll();
                            foreach ($periodos_select as $p): 
                        ?>
                            <option value="<?= $p['id'] ?>" <?= (isset($periodo_base_id) && $periodo_base_id == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['anio']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($empresa_id && $periodo_base_id): ?>
            <div>
                <h2 class="subtitle is-5">Variables para Simulación "What-If" (Dejar en blanco para usar valores base)</h2>
                <div class="field-group">
                    <div class="field">
                        <label class="label">Nuevo Período Promedio de Cobro (días)</label>
                        <div class="control">
                            <input class="input" style="width: 300px;" type="number" inputmode="decimal" name="mod_ppc" value="<?= htmlspecialchars($mod_ppc ?? '') ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Nuevo Período Promedio de Inventario (días)</label>
                        <div class="control">
                            <input class="input" style="width: 300px;" type="text" inputmode="decimal" name="mod_ppi" value="<?= htmlspecialchars($mod_ppi ?? '') ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Nuevo Período Promedio de Pago (días)</label>
                        <div class="control">
                            <input class="input" style="width: 300px;" type="text" inputmode="decimal" name="mod_ppp" value="<?= htmlspecialchars($mod_ppp ?? '') ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Crecimiento de Ventas (%)</label>
                        <div class="control">
                            <input class="input" style="width: 300px;" type="number" inputmode="decimal" name="mod_ventas_crecimiento" value="<?= htmlspecialchars($mod_ventas_crecimiento ?? '') ?>">
                        </div>
                    </div>
                </div>
                <div class="control mt-3">
                    <button class="button is-primary" type="submit" name="calcular_escenario">Calcular Escenario</button>
                </div>
            </div>

            <?php
            if (!empty($valores_balance_base) && !empty($valores_resultados_base) && $calcular_escenario) {
                
                function calcular_indicadores_cce($balance, $resultados) {
                    $ppi = 0; $ppc = 0; $ppp = 0; $co = 0; $cce = 0;
                    $costo_ventas = $resultados['Costo de Ventas'] ?? 0;
                    $inventario_actual = $balance['Inventarios'] ?? 0;
                    $ventas_netas = $resultados['Ventas Netas'] ?? 0;
                    $ctas_cobrar_actual = $balance['Cuentas por Cobrar'] ?? 0;
                    $compras = $resultados['Compras'] ?? ($resultados['Costo de Ventas'] ?? 0);
                    $ctas_pagar_actual = $balance['Cuentas por Pagar'] ?? 0;
                    $ppi = ($costo_ventas == 0) ? 0 : ($inventario_actual / $costo_ventas) * 365;
                    $ppc = ($ventas_netas == 0) ? 0 : ($ctas_cobrar_actual / $ventas_netas) * 365;
                    $ppp = ($compras == 0) ? 0 : ($ctas_pagar_actual / $compras) * 365;
                    $co = $ppi + $ppc;
                    $cce = $co - $ppp;
                    return ['ppi' => $ppi, 'ppc' => $ppc, 'ppp' => $ppp, 'co' => $co, 'cce' => $cce];
                }

                function calcular_razones_liquidez($balance) {
                    // Activo Circulante (ya correcto)
                    $activo_circulante = 
                        ($balance['Efectivo y Equivalentes'] ?? 0) +
                        ($balance['Cuentas por Cobrar'] ?? 0) +
                        ($balance['Inventarios'] ?? 0) +
                        ($balance['Otros Activos Circulantes'] ?? 0);
                    
                    // Pasivo Circulante: Añadir TODAS las partidas relevantes
                    $pasivo_circulante = 
                        ($balance['Cuentas por Pagar'] ?? 0) +
                        ($balance['Pasivos Acumulados'] ?? 0) +
                        ($balance['Deuda a Corto Plazo'] ?? 0) +
                        ($balance['Otras Cuentas por Pagar'] ?? 0); // Si existe en tu BD
                    
                    $inventarios = $balance['Inventarios'] ?? 0;

                    if ($pasivo_circulante != 0) {
                        $razon_corriente = $activo_circulante / $pasivo_circulante;
                        $prueba_acida = ($activo_circulante - $inventarios) / $pasivo_circulante;
                    } else {
                        $razon_corriente = 0;
                        $prueba_acida = 0;
                    }
                    
                    $capital_neto_trabajo = $activo_circulante - $pasivo_circulante;

                    return [
                        'razon_corriente' => $razon_corriente,
                        'prueba_acida' => $prueba_acida,
                        'capital_neto_trabajo' => $capital_neto_trabajo
                    ];
                }

                $indicadores_base = calcular_indicadores_cce($valores_balance_base, $valores_resultados_base);
                $razones_liquidez_base = calcular_razones_liquidez($valores_balance_base);

                $valores_balance_whatif = $valores_balance_base;
                $valores_resultados_whatif = $valores_resultados_base;
                $cambio_efectivo_neto = 0; 

                if ($mod_ventas_crecimiento !== null && is_numeric($mod_ventas_crecimiento)) {
                    $factor_crecimiento = (100 + floatval($mod_ventas_crecimiento)) / 100;
                    $valores_resultados_whatif['Ventas Netas'] = ($valores_resultados_base['Ventas Netas'] ?? 0) * $factor_crecimiento;
                    $valores_resultados_whatif['Costo de Ventas'] = ($valores_resultados_base['Costo de Ventas'] ?? 0) * $factor_crecimiento;
                    if (isset($valores_resultados_base['Compras'])) {
                        $valores_resultados_whatif['Compras'] = ($valores_resultados_base['Compras'] ?? 0) * $factor_crecimiento;
                    }
                }

                $ventas_netas_whatif = $valores_resultados_whatif['Ventas Netas'] ?? 1;
                $ppc_final = ($mod_ppc !== null && is_numeric($mod_ppc)) ? floatval($mod_ppc) : $indicadores_base['ppc'];
                $ctas_cobrar_base = $valores_balance_base['Cuentas por Cobrar'] ?? 0;
                $nuevas_ctas_cobrar_whatif = ($ventas_netas_whatif / 365) * $ppc_final;
                $cambio_efectivo_neto += ($ctas_cobrar_base - $nuevas_ctas_cobrar_whatif);
                $valores_balance_whatif['Cuentas por Cobrar'] = $nuevas_ctas_cobrar_whatif;
                
                $costo_ventas_whatif = $valores_resultados_whatif['Costo de Ventas'] ?? 1;
                $ppi_final = ($mod_ppi !== null && is_numeric($mod_ppi)) ? floatval($mod_ppi) : $indicadores_base['ppi'];
                $inventario_base = $valores_balance_base['Inventarios'] ?? 0;
                $nuevo_inventario_whatif = ($costo_ventas_whatif / 365) * $ppi_final;
                $cambio_efectivo_neto += ($inventario_base - $nuevo_inventario_whatif);
                $valores_balance_whatif['Inventarios'] = $nuevo_inventario_whatif;

                $compras_whatif = $valores_resultados_whatif['Compras'] ?? ($valores_resultados_whatif['Costo de Ventas'] ?? 1);
                $ppp_final = ($mod_ppp !== null && is_numeric($mod_ppp)) ? floatval($mod_ppp) : $indicadores_base['ppp'];
                $ctas_pagar_base = $valores_balance_base['Cuentas por Pagar'] ?? 0;
                $nuevas_ctas_pagar_whatif = ($compras_whatif / 365) * $ppp_final;
                $cambio_efectivo_neto += ($nuevas_ctas_pagar_whatif - $ctas_pagar_base);
                $valores_balance_whatif['Cuentas por Pagar'] = $nuevas_ctas_pagar_whatif;

                $efectivo_base = $valores_balance_base['Efectivo y Equivalentes'] ?? 0;
                $valores_balance_whatif['Efectivo y Equivalentes'] = $efectivo_base + $cambio_efectivo_neto;
                
                $valores_balance_whatif['Total Activo Circulante'] =
                    ($valores_balance_whatif['Efectivo y Equivalentes'] ?? 0) +
                    ($valores_balance_whatif['Cuentas por Cobrar'] ?? 0) +
                    ($valores_balance_whatif['Inventarios'] ?? 0) +
                    ($valores_balance_base['Otros Activos Circulantes'] ?? 0);

                $valores_balance_whatif['Total Pasivo Circulante'] =
                    ($valores_balance_whatif['Cuentas por Pagar'] ?? 0) +
                    ($valores_balance_base['Pasivos Acumulados'] ?? 0) +
                    ($valores_balance_base['Deuda a Corto Plazo'] ?? 0);

                $indicadores_whatif = calcular_indicadores_cce($valores_balance_whatif, $valores_resultados_whatif);
                $razones_liquidez_whatif = calcular_razones_liquidez($valores_balance_whatif);

                echo '<div class="box mt-5">';
                echo '<h2 class="subtitle is-5">Comparación de Escenarios</h2>';
                echo '<table class="table is-bordered is-fullwidth">';
                echo '<thead><tr><th>Indicador</th><th>Escenario Base ('.$periodo_base_anio.')</th><th>Escenario "What-If"</th></tr></thead>';
                echo '<tbody>';
                
                echo '<tr><td colspan="3" class="has-text-weight-bold">Ciclo de Conversión de Efectivo</td></tr>';
                echo '<tr><td>Período Promedio de Inventario (PPI)</td><td>'.number_format($indicadores_base['ppi'], 2).' días</td><td>'.number_format($indicadores_whatif['ppi'], 2).' días</td></tr>';
                echo '<tr><td>Período Promedio de Cobro (PPC)</td><td>'.number_format($indicadores_base['ppc'], 2).' días</td><td>'.number_format($indicadores_whatif['ppc'], 2).' días</td></tr>';
                echo '<tr><td>Período Promedio de Pago (PPP)</td><td>'.number_format($indicadores_base['ppp'], 2).' días</td><td>'.number_format($indicadores_whatif['ppp'], 2).' días</td></tr>';
                echo '<tr><td>Ciclo Operativo (CO)</td><td>'.number_format($indicadores_base['co'], 2).' días</td><td>'.number_format($indicadores_whatif['co'], 2).' días</td></tr>';
                echo '<tr><td>Ciclo de Conversión de Efectivo (CCE)</td><td>'.number_format($indicadores_base['cce'], 2).' días</td><td>'.number_format($indicadores_whatif['cce'], 2).' días</td></tr>';
                
                echo '<tr><td colspan="3" class="has-text-weight-bold">Razones de Liquidez</td></tr>';
                echo '<tr><td>Razón Corriente</td><td>'.number_format($razones_liquidez_base['razon_corriente'], 2).'</td><td>'.number_format($razones_liquidez_whatif['razon_corriente'], 2).'</td></tr>';
                echo '<tr><td>Prueba Ácida</td><td>'.number_format($razones_liquidez_base['prueba_acida'], 2).'</td><td>'.number_format($razones_liquidez_whatif['prueba_acida'], 2).'</td></tr>';
                echo '<tr><td>Capital Neto de Trabajo</td><td>$'.number_format($razones_liquidez_base['capital_neto_trabajo'], 2).'</td><td>$'.number_format($razones_liquidez_whatif['capital_neto_trabajo'], 2).'</td></tr>';

                echo '<tr><td colspan="3" class="has-text-weight-bold">Valores de Balance Impactados (Simulados)</td></tr>';
                echo '<tr><td>Efectivo y Equivalentes</td><td>$'.number_format($valores_balance_base['Efectivo y Equivalentes'] ?? 0, 2).'</td><td>$'.number_format($valores_balance_whatif['Efectivo y Equivalentes'] ?? 0, 2).'</td></tr>';
                echo '<tr><td>Cuentas por Cobrar</td><td>$'.number_format($valores_balance_base['Cuentas por Cobrar'] ?? 0, 2).'</td><td>$'.number_format($valores_balance_whatif['Cuentas por Cobrar'] ?? 0, 2).'</td></tr>';
                echo '<tr><td>Inventarios</td><td>$'.number_format($valores_balance_base['Inventarios'] ?? 0, 2).'</td><td>$'.number_format($valores_balance_whatif['Inventarios'] ?? 0, 2).'</td></tr>';
                echo '<tr><td>Cuentas por Pagar</td><td>$'.number_format($valores_balance_base['Cuentas por Pagar'] ?? 0, 2).'</td><td>$'.number_format($valores_balance_whatif['Cuentas por Pagar'] ?? 0, 2).'</td></tr>';

                echo '</tbody>';
                echo '</table>';
                
                // Formulario para guardar el escenario (ahora es parte del formulario principal)
                echo '<input type="hidden" name="cce_base" value="'.$indicadores_base['cce'].'">';
                echo '<input type="hidden" name="cce_whatif" value="'.$indicadores_whatif['cce'].'">';
                echo '<input type="hidden" name="razon_corriente_base" value="'.$razones_liquidez_base['razon_corriente'].'">';
                echo '<input type="hidden" name="razon_corriente_whatif" value="'.$razones_liquidez_whatif['razon_corriente'].'">';
                echo '<input type="hidden" name="capital_neto_base" value="'.$razones_liquidez_base['capital_neto_trabajo'].'">';
                echo '<input type="hidden" name="capital_neto_whatif" value="'.$razones_liquidez_whatif['capital_neto_trabajo'].'">';
                // Agrega estos campos ocultos junto con los que ya tienes
                echo '<input type="hidden" name="prueba_acida_base" value="'.$razones_liquidez_base['prueba_acida'].'">';
                echo '<input type="hidden" name="prueba_acida_whatif" value="'.$razones_liquidez_whatif['prueba_acida'].'">';
                echo '<input type="hidden" name="efectivo_base" value="'.($valores_balance_base['Efectivo y Equivalentes'] ?? 0).'">';
                echo '<input type="hidden" name="efectivo_whatif" value="'.($valores_balance_whatif['Efectivo y Equivalentes'] ?? 0).'">';
                echo '<input type="hidden" name="ctas_cobrar_base" value="'.($valores_balance_base['Cuentas por Cobrar'] ?? 0).'">';
                echo '<input type="hidden" name="ctas_cobrar_whatif" value="'.($valores_balance_whatif['Cuentas por Cobrar'] ?? 0).'">';
                echo '<input type="hidden" name="inventarios_base" value="'.($valores_balance_base['Inventarios'] ?? 0).'">';
                echo '<input type="hidden" name="inventarios_whatif" value="'.($valores_balance_whatif['Inventarios'] ?? 0).'">';
                echo '<input type="hidden" name="ctas_pagar_base" value="'.($valores_balance_base['Cuentas por Pagar'] ?? 0).'">';
                echo '<input type="hidden" name="ctas_pagar_whatif" value="'.($valores_balance_whatif['Cuentas por Pagar'] ?? 0).'">';
                echo '<input type="hidden" name="co_base" value="'.$indicadores_base['co'].'">';
                echo '<input type="hidden" name="co_whatif" value="'.$indicadores_whatif['co'].'">';
                echo '<input type="hidden" name="ppi_base" value="'.$indicadores_base['ppi'].'">';
                echo '<input type="hidden" name="ppi_whatif" value="'.$indicadores_whatif['ppi'].'">';
                echo '<input type="hidden" name="ppc_base" value="'.$indicadores_base['ppc'].'">';
                echo '<input type="hidden" name="ppc_whatif" value="'.$indicadores_whatif['ppc'].'">';
                echo '<input type="hidden" name="ppp_base" value="'.$indicadores_base['ppp'].'">';
                echo '<input type="hidden" name="ppp_whatif" value="'.$indicadores_whatif['ppp'].'">';
                
                echo '<div class="control mt-3">';
                echo '<button class="button is-info" type="submit" name="guardar_escenario">Guardar Escenario</button>';
                echo '</div>';
                
                echo '</div>';
            } elseif ($empresa_id && $periodo_base_id && $calcular_escenario) {
                echo '<div class="notification is-warning mt-4">No hay datos suficientes para el período base seleccionado. Asegúrese de que el Balance General y el Estado de Resultados contengan las cuentas necesarias (Efectivo, Cuentas por Cobrar, Inventarios, Cuentas por Pagar, Ventas Netas, Costo de Ventas).</div>';
            }
            ?>
        <?php elseif ($empresa_id): ?>
            <div class="notification is-info mt-4" style="width: 600px;">Por favor, seleccione un período base para realizar la simulación "What-If".</div>
        <?php endif; ?>
    </form>
</div>