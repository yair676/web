<div class="container mt-5">
    <h1 class="title is-3">Análisis Horizontal</h1>

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

            if (count($periodos) >= 2) {
                $periodo_ids = array_column($periodos, 'id');

                //Cuentas del balance general
                $cuentas_balance = $conn->query("
                    SELECT DISTINCT cc.nombre as cuenta 
                    FROM cuentas_balance cb
                    JOIN catalogo_cuentas cc ON cb.cuenta_id = cc.id
                    WHERE cb.periodo_id IN (" . implode(',', $periodo_ids) . ")
                ")->fetchAll(PDO::FETCH_COLUMN);

                //Cuentas del estado de resultado
                $cuentas_resultados = $conn->query("
                    SELECT DISTINCT cc.nombre as cuenta 
                    FROM cuentas_resultados cr
                    JOIN catalogo_cuentas cc ON cr.cuenta_id = cc.id
                    WHERE cr.periodo_id IN (" . implode(',', $periodo_ids) . ")
                ")->fetchAll(PDO::FETCH_COLUMN);

                // Procesar cuentas de balance
                foreach ($cuentas_balance as $cuenta) {
                    $valores_balance[$cuenta] = [];

                    $stmt = $conn->prepare("
                        SELECT cb.periodo_id, cb.valor 
                        FROM cuentas_balance cb
                        JOIN catalogo_cuentas cc ON cb.cuenta_id = cc.id
                        WHERE cc.nombre = ? AND cb.periodo_id IN (" . implode(',', $periodo_ids) . ")
                    ");

                    $stmt->execute([$cuenta]);
                    $balance_vals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                    foreach ($periodo_ids as $pid) {
                        $valores_balance[$cuenta][$pid] = $balance_vals[$pid] ?? 0;
                    }
                }

                // Procesar cuentas de resultados
                foreach ($cuentas_resultados as $cuenta) {
                    $valores_resultados[$cuenta] = [];

                    $stmt = $conn->prepare("
                        SELECT cr.periodo_id, cr.valor 
                        FROM cuentas_resultados cr
                        JOIN catalogo_cuentas cc ON cr.cuenta_id = cc.id
                        WHERE cc.nombre = ? AND cr.periodo_id IN (" . implode(',', $periodo_ids) . ")
                    ");

                    $stmt->execute([$cuenta]);
                    $resultados_vals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                    foreach ($periodo_ids as $pid) {
                        $valores_resultados[$cuenta][$pid] = $resultados_vals[$pid] ?? 0;
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

    <?php if ($empresa_id && count($periodos) >= 2): ?>
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
                    <?php foreach ($valores_balance as $cuenta => $vals): ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php foreach ($periodos as $p): ?>
                                <td>$<?= number_format($vals[$p['id']] ?? 0, 2) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

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
                    <?php foreach ($valores_balance as $cuenta => $vals): ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php for ($i = 1; $i < count($periodos); $i++): ?>
                                <?php
                                $anterior = $vals[$periodos[$i-1]['id']] ?? 0;
                                $actual = $vals[$periodos[$i]['id']] ?? 0;
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
                    <?php foreach ($valores_balance as $cuenta => $vals): ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php for ($i = 1; $i < count($periodos); $i++): ?>
                                <?php
                                $anterior = $vals[$periodos[$i-1]['id']] ?? 0;
                                $actual = $vals[$periodos[$i]['id']] ?? 0;
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
                    <?php foreach ($valores_resultados as $cuenta => $vals): ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php foreach ($periodos as $p): ?>
                                <td>$<?= number_format($vals[$p['id']] ?? 0, 2) ?></td>
                            <?php endforeach; ?>
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
                    <?php foreach ($valores_resultados as $cuenta => $vals): ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php for ($i = 1; $i < count($periodos); $i++): ?>
                                <?php
                                $anterior = $vals[$periodos[$i-1]['id']] ?? 0;
                                $actual = $vals[$periodos[$i]['id']] ?? 0;
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
                    <?php foreach ($valores_resultados as $cuenta => $vals): ?>
                        <tr>
                            <td><?= htmlspecialchars($cuenta) ?></td>
                            <?php for ($i = 1; $i < count($periodos); $i++): ?>
                                <?php
                                $anterior = $vals[$periodos[$i-1]['id']] ?? 0;
                                $actual = $vals[$periodos[$i]['id']] ?? 0;
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
    <?php elseif ($empresa_id): ?>
        <div class="notification is-warning">
            Se necesitan al menos 2 periodos para realizar el análisis horizontal.
        </div>
    <?php endif; ?>
</div>