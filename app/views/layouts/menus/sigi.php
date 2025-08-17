<nav class="navbar navbar-light navbar-expand-lg topnav-menu">
    <div class="collapse navbar-collapse" id="topnav-menu-content">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" href="<?= BASE_URL ?>/intranet">
                    <i class="mdi mdi-view-dashboard"></i> Panel
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_SERVER['REQUEST_URI'] === '/sigi' ? 'active' : '') ?>"
                    href="<?= BASE_URL ?>/sigi">
                    <i class="mdi mdi-home-analytics"></i> Inicio
                </a>
            </li>
            <!-- Menú normal solo si es administrador -->
            <?php if (\Core\Auth::esAdminSigi()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle arrow-none" href="#" id="nav-periodos" role="button"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="mdi mdi-format-page-break"></i> Planificación <div class="arrow-down"></div>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="nav-periodos">
                        <a href="<?= BASE_URL ?>/sigi/periodoAcademico" class="dropdown-item">Periodos Académicos</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle arrow-none" href="#" id="nav-docentes" role="button"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="mdi mdi-school"></i> Docentes <div class="arrow-down"></div>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="nav-docentes">
                        <a href="<?= BASE_URL ?>/sigi/coordinadores" class="dropdown-item">Coordinadores</a>
                        <a href="<?= BASE_URL ?>/sigi/docentes" class="dropdown-item">Docentes</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle arrow-none" href="#" id="nav-mantenimiento" role="button"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="mdi mdi-settings"></i> Mantenimiento <div class="arrow-down"></div>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="nav-mantenimiento">
                        <a href="<?= BASE_URL ?>/sigi/datosInstitucionales" class="dropdown-item">Datos Institucionales</a>
                        <a href="<?= BASE_URL ?>/sigi/sedes" class="dropdown-item">Sedes</a>
                        <a href="<?= BASE_URL ?>/sigi/programas" class="dropdown-item">Programas de Estudio</a>
                        <a href="<?= BASE_URL ?>/sigi/planes" class="dropdown-item">Planes de Estudio</a>
                        <a href="<?= BASE_URL ?>/sigi/moduloFormativo" class="dropdown-item">Módulos Formativos</a>
                        <a href="<?= BASE_URL ?>/sigi/semestre" class="dropdown-item">Semestre</a>
                        <a href="<?= BASE_URL ?>/sigi/unidadDidactica" class="dropdown-item">Unidades Didácticas</a>
                        <a href="<?= BASE_URL ?>/sigi/competencias" class="dropdown-item">Competencias</a>
                        <a href="<?= BASE_URL ?>/sigi/capacidades" class="dropdown-item">Capacidades</a>
                        <a href="<?= BASE_URL ?>/sigi/rol" class="dropdown-item">Roles</a>
                        <a href="<?= BASE_URL ?>/sigi/datosSistema" class="dropdown-item">Datos de Sistema</a>
                        <a href="<?= BASE_URL ?>/sigi/sistemasIntegrados" class="dropdown-item">Sistemas Integrados</a>
                        <a href="<?= BASE_URL ?>/sigi/logs" class="dropdown-item">Logs</a>
                    </div>
                </li>
            <?php else: ?>
                <!-- Aquí va solo lo mínimo, o un mensaje, o nada -->
                <!-- O simplemente no muestres nada más -->
            <?php endif; ?>
        </ul>
    </div>
</nav>