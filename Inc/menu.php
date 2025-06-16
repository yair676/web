<nav class="navbar" role="navigation" aria-label="main navigation">
  <div class="navbar-brand">
    <img src="./Img/Logo.png" width="84" height="12" alt="Logo de la UV">

    <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarBasicExample">
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
    </a>
  </div>

  <div id="navbarBasicExample" class="navbar-menu">
    <div class="navbar-start">

    <!-- Datos Empresa -->
    <div class="navbar-item has-dropdown is-hoverable">
      <a class="navbar-link" href="Inicio.php?vista=RNE" >Empresa</a>
      <div class="navbar-dropdown">
        <a class="navbar-item" href="Inicio.php?page=registrar_empresa">Registrar Empresa</a>
        <a class="navbar-item" href="Inicio.php?page=periodo">Crear Periodo Contable</a>
      </div>
    </div>
    
    <!-- Estados Financieros -->
      <div class="navbar-item has-dropdown is-hoverable">
        <a class="navbar-link">Estados Financieros</a>
        <div class="navbar-dropdown">
          <a class="navbar-item" href="Inicio.php?page=balance_general">Balance General</a>
          <a class="navbar-item" href="Inicio.php?page=estado_resultados">Estado Resultados</a>
          <a class="navbar-item" href="Inicio.php?page=historial">Historial</a>
        </div>
      </div>

      <!-- Análisis -->
      <div class="navbar-item has-dropdown is-hoverable">
        <a class="navbar-link">Análisis</a>
        <div class="navbar-dropdown">
          <a class="navbar-item" href="Inicio.php?page=analisis_horizontal">Analisis Horizontal</a>
          <a class="navbar-item" href="Inicio.php?page=analisis_vertical">Analisis Vertical</a>
          <a class="navbar-item" href="Inicio.php?page=razones_financieras">Razones Financieras</a>
          <a class="navbar-item" href="Inicio.php?page=tendencias">Tendencias</a>
        </div>
      </div>

      <!-- Capital de Trabajo -->
      <div class="navbar-item has-dropdown is-hoverable">
        <a class="navbar-link">Capital Trabajo</a>
        <div class="navbar-dropdown">
          <a class="navbar-item" href="Inicio.php?page=gestion_efectivo">Gestion Efectivo</a>
          <a class="navbar-item" href="Inicio.php?page=gestion_cuenta_cob">Cuentas por Cobrar</a>
          <a class="navbar-item" href="Inicio.php?page=gestion_inventario">Gestion Inventarios</a>
          <a class="navbar-item" href="Inicio.php?page=gestion_cuentas_pag">Cuentas por Pagar</a>
        </div>
      </div>

      <!-- Simulación -->
      <div class="navbar-item has-dropdown is-hoverable">
        <a class="navbar-link">Simulación</a>
        <div class="navbar-dropdown">
          <a class="navbar-item" href="Inicio.php?page=what_if">Escenarios What-If</a>
          <a class="navbar-item" href="Inicio.php?page=proyeccion_financiera">Proyecciones Financieras</a>
        </div>
      </div>

      <!-- Reportes -->
      <div class="navbar-item has-dropdown is-hoverable">
        <a class="navbar-link">Reportes</a>
        <div class="navbar-dropdown">
          <a class="navbar-item" href="Inicio.php?page=dashboard_visual">Dashboard</a>
        </div>
      </div>

    </div>

    <div class="navbar-end">
      <div class="navbar-item">
        <div class="buttons">
          <a class="button is-link is-rounded" href="logout.php">Salir</a>
        </div>
      </div>
    </div>
  </div>
</nav>
