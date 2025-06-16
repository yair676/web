<div class="container mt-5">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/regression@2.0.1/dist/regression.min.js"></script>
  <h1 class="title is-3">Análisis de Tendencias</h1>

  <?php
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/FinanzasProyecto/');
    include 'inc/head.php';
    include 'inc/db.php';

    // Obtener todas las empresas
    $empresas = $conn->query("SELECT id, nombre FROM empresas")->fetchAll();

    // Si se seleccionó una empresa
    $empresa_id = $_POST['empresa_id'] ?? null;
    $valores_balance = [];
    $valores_resultados = [];
    $labels_balance = [];
    $labels_resultados = [];

    if ($empresa_id) {
        // Obtener todos los periodos y agruparlos por año.
        $stmt_periodos = $conn->prepare("
            SELECT DISTINCT YEAR(fecha_inicio) as anio 
            FROM periodos 
            WHERE empresa_id = ? 
            ORDER BY anio
        ");
        $stmt_periodos->execute([$empresa_id]);
        $todos_los_anios = $stmt_periodos->fetchAll(PDO::FETCH_COLUMN);

        if (count($todos_los_anios)) {
            // Cuentas seleccionadas del balance general
            $cuentas_balance_nombres = ['Efectivo y Equivalentes', 'Inventarios', 'TOTAL ACTIVO', 'TOTAL PASIVO', 'CAPITAL CONTABLE'];
            
            // Obtener valores para las cuentas de balance
            $stmt_balance = $conn->prepare("
                SELECT YEAR(p.fecha_inicio) as anio, SUM(cb.valor) as valor 
                FROM cuentas_balance cb
                JOIN catalogo_cuentas cc ON cb.cuenta_id = cc.id
                JOIN periodos p ON cb.periodo_id = p.id
                WHERE cc.nombre = ? AND p.empresa_id = ?
                GROUP BY anio ORDER BY anio
            ");
            foreach ($cuentas_balance_nombres as $cuenta) {
                $stmt_balance->execute([$cuenta, $empresa_id]);
                $valores_balance[$cuenta] = $stmt_balance->fetchAll(PDO::FETCH_KEY_PAIR);
            }
            $labels_balance = $todos_los_anios;

            // Cuentas seleccionadas del estado de resultados
            $cuentas_resultados_nombres = ['Ventas Netas', 'Costo de Ventas', 'Gastos de Operación', 'Utilidad Neta'];
            
            // Obtener valores para las cuentas de resultados
            $stmt_resultados = $conn->prepare("
                SELECT YEAR(p.fecha_inicio) as anio, SUM(cr.valor) as valor 
                FROM cuentas_resultados cr
                JOIN catalogo_cuentas cc ON cr.cuenta_id = cc.id
                JOIN periodos p ON cr.periodo_id = p.id
                WHERE cc.nombre = ? AND p.empresa_id = ?
                GROUP BY anio ORDER BY anio
            ");
            foreach ($cuentas_resultados_nombres as $cuenta) {
                $stmt_resultados->execute([$cuenta, $empresa_id]);
                $valores_resultados[$cuenta] = $stmt_resultados->fetchAll(PDO::FETCH_KEY_PAIR);
            }
            $labels_resultados = $todos_los_anios;
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
  
  <?php if ($empresa_id && (count($labels_balance) || count($labels_resultados))): ?>
    <script>
      function calcularProyeccion(labels, data, grado = 2) {
        // Se necesitan al menos 2 puntos para la regresión.
        if (labels.length < 2) {
          return { r2: 0, ecuacion: 'N/A', predicciones: [0, 0] };
        }
        
        // Preparar datos para la regresión: [año, valor].
        const puntos = labels.map((x, i) => [x, data[i]]);
        
        // Calcular regresión polinómica con los años reales.
        const resultado = regression.polynomial(puntos, { order: grado, precision: 4 });
        
        // Predecir los próximos 2 años basándose en el último año real.
        const predicciones = [];
        const ultimoLabel = labels[labels.length - 1];
        
        for (let i = 1; i <= 2; i++) {
          const x_futuro = ultimoLabel + i;
          const y_predicho = resultado.predict(x_futuro)[1];
          predicciones.push(y_predicho); // Se permiten proyecciones negativas si el modelo lo indica.
        }
        
        return {
          r2: resultado.r2,
          ecuacion: resultado.string,
          predicciones: predicciones
        };
      }
    </script>

    <div class="box mt-5">
      <h2 class="subtitle is-5">Balance General - Tendencias Individuales</h2>
      
      <?php 
      $colores = ['rgba(54, 162, 235, 1)', 'rgba(255, 99, 132, 1)', 'rgba(75, 192, 192, 1)', 'rgba(153, 102, 255, 1)', 'rgba(255, 159, 64, 1)'];
      $i = 0;
      foreach ($valores_balance as $cuenta => $valores): 
        if(empty($valores)) continue;
        $color = $colores[$i % count($colores)];
        $i++;
        
        // Preparar datos para la gráfica (con nulos para los huecos).
        $valores_grafica = [];
        foreach($labels_balance as $anio){
          $valores_grafica[] = $valores[$anio] ?? null;
        }

        // Preparar datos para la proyección (solo años con valor).
        $labels_proyeccion = array_keys($valores);
        $datos_proyeccion = array_values($valores);
      ?>
        <div class="mb-6">
          <div class="chart-container" style="position: relative; height:40vh; width:80vw">
            <canvas id="graficaBalance<?= $i ?>"></canvas>
          </div>
          
            <script>
              document.addEventListener('DOMContentLoaded', function() {
                  const ctx = document.getElementById('graficaBalance<?= $i ?>');
                  if (!ctx) return;

                  const labels_proy = <?= json_encode($labels_proyeccion) ?>;
                  const data_proy = <?= json_encode($datos_proyeccion) ?>;
                  const proyeccion = calcularProyeccion(labels_proy, data_proy);
                  const labels_chart = <?= json_encode($labels_balance) ?>;
                  const data_chart = <?= json_encode($valores_grafica) ?>;
                  
                  const anosProyeccion = labels_chart.length > 0 ? [
                      parseInt(labels_chart[labels_chart.length - 1]) + 1,
                      parseInt(labels_chart[labels_chart.length - 1]) + 2
                  ] : [];
                  
                  // Encontrar el índice del último dato real (no nulo).
                  const lastDataIndex = data_chart.map((v, i) => v === null ? -1 : i).reduce((a, b) => Math.max(a, b), -1);

                  // Combinar datos históricos y de proyección en un solo array.
                  const combinedData = [...data_chart, ...proyeccion.predicciones];
                  
                  // --- INICIO DE LA CONFIGURACIÓN DEL GRÁFICO ---
                  new Chart(ctx, {
                      type: 'line',
                      data: {
                          labels: [...labels_chart, ...anosProyeccion.map(String)],
                          // Solo tenemos UN dataset, para tener una sola línea
                          datasets: [{
                              label: '<?= addslashes($cuenta) ?>',
                              data: combinedData,
                              borderColor: '<?= $color ?>',
                              borderWidth: 3,
                              tension: 0.1,
                              fill: false,
                              spanGaps: true,
                              // 'segment' se encarga de cambiar el estilo de la línea a punteado
                              segment: {
                                  borderDash: (context) => {
                                      if (lastDataIndex !== -1 && context.p0DataIndex >= lastDataIndex) {
                                          return [5, 5]; // Estilo punteado para la proyección
                                      }
                                      return undefined; // Estilo sólido para el historial
                                  }
                              },
                              pointRadius: (context) => {
                                  return context.dataIndex >= labels_chart.length ? 5 : 3;
                              },
                              pointBackgroundColor: '<?= $color ?>'
                          }]
                      },
                      options: {
                          responsive: true,
                          maintainAspectRatio: false,
                          plugins: {
                              title: { display: true, text: '<?= addslashes($cuenta) ?>', font: { size: 16 } },
                              // Aquí personalizamos la leyenda
                              legend: {
                                  labels: {
                                      // Esta función genera las etiquetas de la leyenda manualmente.
                                      // Nos permite crear dos items de leyenda a partir de un solo dataset.
                                      generateLabels: (chart) => {
                                          const dataset = chart.data.datasets[0];
                                          return [
                                          {
                                              // 1. Item para la parte histórica (sólida)
                                              text: dataset.label, // El nombre de la cuenta
                                              fillStyle: 'rgba(0,0,0,0)',
                                              strokeStyle: dataset.borderColor,
                                              lineWidth: dataset.borderWidth,
                                              lineDash: [], // Importante: array vacío para línea sólida
                                              fontColor: '#0a0000' // Puedes ajustar el color de la fuente
                                          },
                                          {
                                              // 2. Item para la parte de proyección (punteada)
                                              text: 'Proyección',
                                              fillStyle: 'rgba(0,0,0,0)',
                                              strokeStyle: dataset.borderColor,
                                              lineWidth: 2, // Puedes usar un grosor distinto si quieres
                                              lineDash: [5, 5], // Importante: el estilo punteado
                                              fontColor: '#0a0000'
                                          }];
                                      }
                                  }
                              },
                              tooltip: {
                                  callbacks: {
                                      label: (context) => {
                                          let label = '<?= addslashes($cuenta) ?>: ';
                                          if (context.parsed.y !== null) {
                                              label += new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(context.parsed.y);
                                          }
                                          if (context.dataIndex >= labels_chart.length) {
                                              label += ' (Proyección)';
                                          }
                                          return label;
                                      }
                                  }
                              }
                          },
                          scales: {
                              y: { beginAtZero: false, title: { display: true, text: 'Valor ($)' } },
                              x: { title: { display: true, text: 'Año' } }
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
              <?php foreach ($labels_balance as $anio): ?>
                <th><?= $anio ?></th>
              <?php endforeach; ?>
              <?php if(count($labels_balance) >= 2): ?>
                <th><?= (int)end($labels_balance) + 1 ?></th>
                <th><?= (int)end($labels_balance) + 2 ?></th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($valores_balance as $cuenta => $valores): if(!empty($valores)): ?>
              <tr>
                <td><?= htmlspecialchars($cuenta) ?></td>
                <?php foreach ($labels_balance as $anio): ?>
                  <td><?= isset($valores[$anio]) ? '$'.number_format($valores[$anio], 2) : 'N/A' ?></td>
                <?php endforeach; ?>
                <?php if(count($labels_balance) >= 2): ?>
                  <td id="proy-b-1-<?= md5($cuenta) ?>"></td>
                  <td id="proy-b-2-<?= md5($cuenta) ?>"></td>
                  <script>
                    (() => {
                      const labels = <?= json_encode(array_keys($valores)) ?>;
                      const data = <?= json_encode(array_values($valores)) ?>;
                      const proyeccion = calcularProyeccion(labels, data);
                      document.getElementById('proy-b-1-<?= md5($cuenta) ?>').innerText = '$' + new Intl.NumberFormat('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(proyeccion.predicciones[0]);
                      document.getElementById('proy-b-2-<?= md5($cuenta) ?>').innerText = '$' + new Intl.NumberFormat('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(proyeccion.predicciones[1]);
                    })();
                  </script>
                <?php endif; ?>
              </tr>
            <?php endif; endforeach; ?>
          </tbody>
      </table>
    </div>

    <div class="box mt-5">
      <h2 class="subtitle is-5">Estado de Resultados - Tendencias Individuales</h2>
      <?php 
      $j = 0;
      foreach ($valores_resultados as $cuenta => $valores):
        if(empty($valores)) continue;
        $color = $colores[$j % count($colores)];
        $j++;

        $valores_grafica = [];
        foreach($labels_resultados as $anio){
          $valores_grafica[] = $valores[$anio] ?? null;
        }
        $labels_proyeccion = array_keys($valores);
        $datos_proyeccion = array_values($valores);
      ?>
        <div class="mb-6">
          <div class="chart-container" style="position: relative; height:40vh; width:80vw">
            <canvas id="graficaResultados<?= $j ?>"></canvas>
          </div>
          <script>
             document.addEventListener('DOMContentLoaded', function() {
              const ctx = document.getElementById('graficaResultados<?= $j ?>');
              if (!ctx) return;

              const labels_proy = <?= json_encode($labels_proyeccion) ?>;
              const data_proy = <?= json_encode($datos_proyeccion) ?>;
              const proyeccion = calcularProyeccion(labels_proy, data_proy);
              
              const labels_chart = <?= json_encode($labels_resultados) ?>;
              const data_chart = <?= json_encode($valores_grafica) ?>;

              const anosProyeccion = labels_chart.length > 0 ? [
                parseInt(labels_chart[labels_chart.length - 1]) + 1,
                parseInt(labels_chart[labels_chart.length - 1]) + 2
              ] : [];

              // Encontrar el índice del último dato real (no nulo).
              const lastDataIndex = data_chart.map((v, i) => v === null ? -1 : i).reduce((a, b) => Math.max(a, b), -1);

              // Combinar datos históricos y de proyección.
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
                    fill: false,
                    spanGaps: true,
                    segment: {
                        borderDash: (context) => {
                            // Los segmentos desde el último dato real se dibujan punteados.
                            if (lastDataIndex !== -1 && context.p0DataIndex >= lastDataIndex) {
                                return [5, 5]; // Estilo de línea punteada.
                            }
                            return undefined; // Línea sólida.
                        }
                    },
                    pointRadius: (context) => {
                        // Puntos más grandes para los datos proyectados.
                        return context.dataIndex >= labels_chart.length ? 5 : 3;
                    },
                    pointBackgroundColor: '<?= $color ?>'
                  }]
                },
                options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: {
                    title: { display: true, text: '<?= addslashes($cuenta) ?>', font: { size: 16 } },
                    // Aquí personalizamos la leyenda
                              legend: {
                                  labels: {
                                      // Esta función genera las etiquetas de la leyenda manualmente.
                                      // Nos permite crear dos items de leyenda a partir de un solo dataset.
                                      generateLabels: (chart) => {
                                          const dataset = chart.data.datasets[0];
                                          return [
                                          {
                                              // 1. Item para la parte histórica (sólida)
                                              text: dataset.label, // El nombre de la cuenta
                                              fillStyle: 'rgba(0,0,0,0)',
                                              strokeStyle: dataset.borderColor,
                                              lineWidth: dataset.borderWidth,
                                              lineDash: [], // Importante: array vacío para línea sólida
                                              fontColor: '#0a0000' // Puedes ajustar el color de la fuente
                                          },
                                          {
                                              // 2. Item para la parte de proyección (punteada)
                                              text: 'Proyección',
                                              fillStyle: 'rgba(0,0,0,0)',
                                              strokeStyle: dataset.borderColor,
                                              lineWidth: 2, // Puedes usar un grosor distinto si quieres
                                              lineDash: [5, 5], // Importante: el estilo punteado
                                              fontColor: '#0a0000'
                                          }];
                                      }
                                  }
                              },
                     tooltip: {
                      callbacks: {
                        label: (context) => {
                           let label = context.dataset.label || '';
                           if(label) {
                               label += ': ';
                           }
                           if (context.parsed.y !== null) {
                               label += new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(context.parsed.y);
                           }
                           if (context.dataIndex >= labels_chart.length) {
                               label += ' (Proyección)';
                           }
                           return label;
                        }
                      }
                    }
                  },
                   scales: {
                    y: { beginAtZero: false, title: { display: true, text: 'Valor ($)' } },
                    x: { title: { display: true, text: 'Año' } }
                  }
                }
              });
            });
          </script>
        </div>
      <?php endforeach; ?>
      
      <h3 class="subtitle is-6 mt-5">Tabla de Valores y Proyecciones (Resultados de Resultado)</h3>
      <table class="table is-bordered is-fullwidth">
        <thead>
          <tr>
            <th>Cuenta</th>
            <?php foreach ($labels_resultados as $anio): ?>
              <th><?= $anio ?></th>
            <?php endforeach; ?>
            <?php if(count($labels_resultados) >= 2): ?>
              <th><?= (int)end($labels_resultados) + 1 ?></th>
              <th><?= (int)end($labels_resultados) + 2 ?></th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
           <?php foreach ($valores_resultados as $cuenta => $valores): if(!empty($valores)): ?>
              <tr>
                <td><?= htmlspecialchars($cuenta) ?></td>
                <?php foreach ($labels_resultados as $anio): ?>
                  <td><?= isset($valores[$anio]) ? '$'.number_format($valores[$anio], 2) : 'N/A' ?></td>
                <?php endforeach; ?>
                <?php if(count($labels_resultados) >= 2): ?>
                  <td id="proy-r-1-<?= md5($cuenta) ?>">1</td>
                  <td id="proy-r-2-<?= md5($cuenta) ?>">2</td>
                  <script>
                    (() => {
                      const labels = <?= json_encode(array_keys($valores)) ?>;
                      const data = <?= json_encode(array_values($valores)) ?>;
                      const proyeccion = calcularProyeccion(labels, data);
                      document.getElementById('proy-r-1-<?= md5($cuenta) ?>').innerText = '$' + new Intl.NumberFormat('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(proyeccion.predicciones[0]);
                      document.getElementById('proy-r-2-<?= md5($cuenta) ?>').innerText = '$' + new Intl.NumberFormat('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(proyeccion.predicciones[1]);
                    })();
                  </script>
                <?php endif; ?>
              </tr>
            <?php endif; endforeach; ?>
        </tbody>
      </table>
    </div>
    
  <?php elseif ($empresa_id): ?>
    <div class="notification is-warning">
      No hay datos suficientes para mostrar las tendencias. Asegúrese de haber capturado la información en el Balance General y/o Estado de Resultados para al menos dos periodos.
    </div>
  <?php endif; ?>
</div>