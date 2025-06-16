<div class="container mt-5">
    <h1 class="title is-3">Gestionar Periodos Contables</h1>

    <?php
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/FinanzasProyecto/');
    include 'inc/head.php';
    include 'inc/db.php';

    $empresas = $conn->query("SELECT id, nombre FROM empresas")->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['eliminar_periodo_id'])) {
            try {
                $stmt = $conn->prepare("DELETE FROM periodos WHERE id = ?");
                $stmt->execute([$_POST['eliminar_periodo_id']]);
                echo "<div class='notification is-success'>Periodo eliminado exitosamente.</div>";
            } catch (PDOException $e) {
                echo "<div class='notification is-danger'>Error al eliminar el periodo: " . $e->getMessage() . "</div>";
            }
        }
        elseif (isset($_POST['empresa_id'], $_POST['periodos'])) {
            $empresa_id = $_POST['empresa_id'];
            $periodos = $_POST['periodos'];
            try {
                $sql = "INSERT INTO periodos (empresa_id, fecha_inicio, fecha_fin) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                foreach ($periodos as $p) {
                    if (!empty($p['inicio']) && !empty($p['fin'])) {
                        if (new DateTime($p['inicio']) >= new DateTime($p['fin'])) {
                            throw new Exception("La fecha de fin debe ser posterior a la fecha de inicio.");
                        }
                        $stmt->execute([$empresa_id, $p['inicio'], $p['fin']]);
                    }
                }
                echo "<div class='notification is-success'>Nuevos periodos guardados exitosamente.</div>";
            } catch (Exception $e) {
                echo "<div class='notification is-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
    }
    ?>

    <div class="field form-wide">
      <label class="label">Seleccionar Empresa</label>
      <div class="control">
          <div class="select">
              <select id="empresaSelect" required>
                  <option value="">Seleccione una empresa...</option>
                  <?php foreach ($empresas as $empresa) : ?>
                      <option value="<?= $empresa['id'] ?>"><?= htmlspecialchars($empresa['nombre']) ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
      </div>
    </div>

    <div id="gestionContainer" class="mt-4"></div>
    <script>
        const BASE_URL = "<?= BASE_URL ?>";

        document.addEventListener('DOMContentLoaded', function() {
            const empresaSelect = document.getElementById('empresaSelect');
            const gestionContainer = document.getElementById('gestionContainer');

            empresaSelect.addEventListener('change', async function() {
                const empresaId = this.value;
                gestionContainer.innerHTML = '';

                if (!empresaId) return;

                try {
                    const url = `${BASE_URL}php/fetch_periodos.php?empresa_id=${empresaId}`;
                    const response = await fetch(url);
                    if (!response.ok) throw new Error('Error al conectar con el servidor.');
                    const periodos = await response.json();

                    if (periodos.length > 0) {
                        let html = `<div class="box">`;
                        html += `<h2 class="title is-5">Periodos Existentes</h2>`;

                        html += '<div class="periodos-container">';
                        periodos.forEach(p => {
                            html += `
                                <form class="periodo-existente-form" method="POST" action="">
                                    <div class="level box is-shadowless p-2 mb-2">
                                        <div class="level-left">
                                            <p><strong>Inicio:</strong> ${p.fecha_inicio} &nbsp;&nbsp; <strong>Fin:</strong> ${p.fecha_fin}</p>
                                        </div>
                                        <div class="level-right">
                                            <input type="hidden" name="eliminar_periodo_id" value="${p.id}">
                                            <button id="BotonEliminar" class="button is-danger is-small" type="submit" onclick="return confirm('¿Estás seguro de que deseas eliminar este periodo?');">
                                                Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </form>`;
                        });
                        
                        html += '</div>';

                        // Formulario para agregar NUEVOS periodos
                        html += `<hr>
                                <form method="POST" action=""> <h2 class="title is-5 mt-5">Agregar Nuevos Periodos</h2>
                                    <input type="hidden" name="empresa_id" value="${empresaId}">
                                    ${generarFormularioNuevosPeriodos()}
                                    <div class="field mt-4">
                                        <div class="control">
                                            <button type="submit" class="button is-primary">Guardar Nuevos Periodos</button>
                                        </div>
                                    </div>
                                </form>`;
                        
                        html += `</div>`;
                        gestionContainer.innerHTML = html;

                    } else {
                        let html = `
                            <form method="POST" action="">
                                <div class="box">
                                    <h2 class="title is-5">No hay periodos registrados</h2>
                                    <p class="subtitle is-6">Agregue los periodos contables para esta empresa.</p>
                                    <input type="hidden" name="empresa_id" value="${empresaId}">
                                    ${generarFormularioNuevosPeriodos()}
                                    <div class="field mt-4">
                                        <div class="control">
                                            <button type="submit" class="button is-primary">Crear Periodos</button>
                                        </div>
                                    </div>
                                </div>
                            </form>`;
                        gestionContainer.innerHTML = html;
                    }

                    configurarEventosNuevosPeriodos(gestionContainer);

                } catch (error) {
                    gestionContainer.innerHTML = `<div class='notification is-danger'>${error.message}</div>`;
                }
            });

            function generarFormularioNuevosPeriodos() {
                return `<div class="field">
                            <label class="label">¿Cuántos periodos desea agregar?</label>
                                <div class="control">
                                    <input class="input" type="number" id="numPeriodos" min="1" max="20" style="width: 200px;">
                                </div>
                            </div>
                            <div id="periodosContainer" class="numPeriodos">
                        </div>`;
            }

            function configurarEventosNuevosPeriodos(contexto) {
                const numPeriodosInput = contexto.querySelector('#numPeriodos');
                if (numPeriodosInput) {
                    numPeriodosInput.addEventListener('change', function() {
                        const num = parseInt(this.value);
                        const container = contexto.querySelector('#periodosContainer');
                        container.innerHTML = '';
                        if (isNaN(num) || num <= 0) return;

                        for (let i = 0; i < num; i++) {
                            container.innerHTML += `
                                <div class="box mb-3 is-light nuevo-periodo-box">
                                    <p class="subtitle is-6">Nuevo Periodo ${i + 1}</p>
                                    <div class="campos-flex-container">
                                        <div class="field">
                                            <label class="label is-small">Fecha de Inicio</label>
                                            <input class="input is-small" type="date" name="periodos[${i}][inicio]" required>
                                        </div>
                                        <div class="field">
                                            <label class="label is-small">Fecha de Fin</label>
                                            <input class="input is-small" type="date" name="periodos[${i}][fin]" required>
                                        </div>
                                    </div>
                                </div>`;
                        }
                    });
                }
            }
        });
    </script>
</div>