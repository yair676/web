<div class="container mt-5">
    <h1 class="title is-3">Gestión de Inventarios</h1>

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

        // --- Variables para EOQ y Punto de Reorden (Estos valores tendrían que venir de tu base de datos o de entrada de usuario) ---
        // Para fines de demostración, se asumen valores cero ya que no están disponibles en las consultas actuales.
        // Si tienes estos datos en tu base de datos (ej: en una tabla de configuración de inventario o productos),
        // deberías modificar las consultas para obtenerlos.
        $demanda_anual_d = 0; // D: Demanda anual (unidades) - para EOQ
        $costo_por_pedido_k = 0; // K: Costo por pedido o reabastecimiento - para EOQ
        $costo_mantenimiento_g = 0; // G: Costo de mantenimiento por unidad por año - para EOQ
        $demanda_diaria = 0; // Demanda diaria (unidades) - para Punto de Reorden
        $tiempo_entrega_dias = 0; // Tiempo de entrega (días) - para Punto de Reorden
        // --- Fin Variables para EOQ y Punto de Reorden ---


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
                <h2 class="subtitle is-5">Inventario Promedio</h2>
                <?php if (isset($valores_balance['Inventarios'])): ?>
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
                            <tr>
                                <td>(Inventario Inicial + Inventario Final) / 2</td>
                                <?php foreach ($periodos as $index => $p): ?>
                                    <td>
                                        <?php if ($index == 0 && ($valores_balance['Inventarios'][$p['id']] ?? 0) == 0 && count($periodos) > 1):?>
                                            N/A
                                        <?php else: ?>
                                            <?= number_format($inventario_promedio_valores[$p['id']], 2) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No hay datos de Inventarios en el balance general para calcular el Inventario Promedio.</p>
                <?php endif; ?>
            </div>

            <div class="box mt-5">
                <h2 class="subtitle is-5">Rotación de Inventarios</h2>
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
                                $rotacion_inv = [];
                                // $inventario_promedio_valores ya se calculó en la sección anterior
                                foreach ($periodos as $index => $p) {
                                    $pid = $p['id'];
                                    $costo_ventas = $valores_resultados['Costo de Ventas'][$pid] ?? 0;
                                    $inventario_promedio = $inventario_promedio_valores[$pid] ?? 0;

                                    // 3. Rotación de Inventarios = Costo de Ventas / Inventario Promedio
                                    $rotacion_inv[$pid] = ($inventario_promedio == 0 ? 0 : ($costo_ventas / $inventario_promedio));
                                }
                            ?>
                            <tr>
                                <td>Costo de Ventas / Inventario Promedio</td>
                                <?php foreach ($periodos as $p): ?>
                                    <td>
                                        <?= number_format($rotacion_inv[$p['id']], 2) ?> veces
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No hay datos suficientes para calcular la Rotación de Inventarios.</p>
                <?php endif; ?>
            </div>

            <div class="box mt-5">
                <h2 class="subtitle is-5">Período Promedio de Inventarios</h2>
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
                                    // Usamos la Rotación de Inventarios ya calculada
                                    $rotacion = $rotacion_inv[$pid] ?? 0;

                                    // Se elimina la condición "$index == 0" para permitir el cálculo para el primer año
                                    if ($rotacion == 0) {
                                        $ppi[$pid] = 0; // Indica no calculable si la rotación es cero
                                    } else {
                                        $ppi[$pid] = 365 / $rotacion;
                                    }
                                }
                            ?>
                            <tr>
                                <td>365 / Rotación de Inventarios</td>
                                <?php foreach ($periodos as $index => $p): ?>
                                    <td>
                                        <?php
                                            // Se ajusta la condición de N/A para que solo se muestre si la rotación es 0
                                            if ($rotacion_inv[$p['id']] == 0):
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
                    <p>No hay datos suficientes para calcular el Período Promedio de Inventario.</p>
                <?php endif; ?>
            </div>

            <div class="box mt-5">
                <h2 class="subtitle is-5">Nivel Óptimo de Inventario</h2>
                <p><strong>Nota:</strong> Para calcular el Q, se requiere la <strong>Demanda Anual (D)</strong>, el <strong>Costo por Pedido (K)</strong> y el <strong>Costo de Mantenimiento por unidad por año (G)</strong>. Estos datos no están disponibles en las cuentas de balance o resultados.</p>
                <?php
                    $eoq_value = 0;
                    if ($demanda_anual_d > 0 && $costo_por_pedido_k > 0 && $costo_mantenimiento_g > 0) {
                        // Q = √2*K*D/G
                        $eoq_value = sqrt((2 * $costo_por_pedido_k * $demanda_anual_d) / $costo_mantenimiento_g);
                    }
                ?>
                <table class="table is-bordered is-fullwidth table-fixed-width">
                    <thead>
                        <tr>
                            <th style="width: 25%">Fórmula</th>
                            <th style="width: 75%">Valor Calculado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Q = &radic;(2KD / G)</td>
                            <td>
                                <?php if ($eoq_value > 0): ?>
                                    <?= number_format($eoq_value, 2) ?> unidades
                                <?php else: ?>
                                    Datos incompletos para el cálculo. (D=<?= $demanda_anual_d ?>, K=<?= $costo_por_pedido_k ?>, G=<?= $costo_mantenimiento_g ?>)
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="box mt-5">
                <h2 class="subtitle is-5">Nivel de Reorden</h2>
                <p><strong>Nota:</strong> Para calcular el Punto de Reorden, se requiere la <strong>Demanda diaria</strong> y el <strong>Tiempo de entrega (días)</strong>. Estos datos no están disponibles en las cuentas de balance o resultados.</p>
                <?php
                    $punto_reorden_value = 0;
                    if ($demanda_diaria > 0 && $tiempo_entrega_dias > 0) {
                        // Punto de Reorden = Demanda diaria × Tiempo de entrega (días)
                        $punto_reorden_value = $demanda_diaria * $tiempo_entrega_dias;
                    }
                ?>
                <table class="table is-bordered is-fullwidth table-fixed-width">
                    <thead>
                        <tr>
                            <th style="width: 25%">Fórmula</th>
                            <th style="width: 75%">Valor Calculado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Punto de Reorden = Demanda diaria &times; Tiempo de entrega (días)</td>
                            <td>
                                <?php if ($punto_reorden_value > 0): ?>
                                    <?= number_format($punto_reorden_value, 2) ?> unidades
                                <?php else: ?>
                                    Datos incompletos para el cálculo. (Demanda diaria=<?= $demanda_diaria ?>, Tiempo de entrega=<?= $tiempo_entrega_dias ?>)
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php elseif ($empresa_id): ?>
            <div class="notification is-warning mt-4">
                No hay datos suficientes para calcular las razones financieras. Se necesitan al menos 1 período con información de balance y estado de resultados.
            </div>
        <?php endif; ?>
    </form>
</div>