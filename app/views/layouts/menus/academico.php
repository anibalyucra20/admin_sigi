<nav class="navbar navbar-light navbar-expand-lg topnav-menu">
    <div class="collapse navbar-collapse" id="topnav-menu-content">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" href="<?= BASE_URL ?>/intranet">
                    <i class="mdi mdi-view-dashboard"></i> Panel
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($_SERVER['REQUEST_URI'] === '/academico' ? 'active' : '') ?>"
                    href="<?= BASE_URL ?>/academico">
                    <i class="mdi mdi-home-analytics"></i> Inicio
                </a>
            </li>
            <!-- Menú normal solo si es administrador -->
            <?php if (\Core\Auth::tieneRolEnAcademico()): ?>
                <?php if (\Core\Auth::esAdminAcademico()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle arrow-none" href="#" id="nav-periodos" role="button"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="mdi mdi-format-page-break"></i> Planificación <div class="arrow-down"></div>
                        </a>
                        <div class="dropdown-menu" aria-labelledby="nav-periodos">
                            <a href="<?= BASE_URL ?>/academico/programacionUnidadDidactica" class="dropdown-item">Programación de Clases</a>
                        </div>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle arrow-none" href="#" id="nav-matriculas" role="button"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="mdi mdi-bank-transfer"></i> Matrículas <div class="arrow-down"></div>
                        </a>
                        <div class="dropdown-menu" aria-labelledby="nav-matriculas">
                            <a href="<?= BASE_URL ?>/academico/matricula" class="dropdown-item">Registro de Matrículas</a>
                            <a href="<?= BASE_URL ?>/academico/licencias" class="dropdown-item">Licencias de Estudio</a>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/docentes') !== false ? 'active' : '' ?>"
                            href="<?= BASE_URL ?>/academico/estudiantes">
                            <i class="mdi mdi-school"></i> Estudiantes
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle arrow-none" href="#" id="nav-matriculas" role="button"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="mdi mdi-bank-transfer"></i> Evaluación <div class="arrow-down"></div>
                        </a>
                        <div class="dropdown-menu" aria-labelledby="nav-matriculas">
                            <a href="<?= BASE_URL ?>/academico/unidadesDidacticas/evaluar" class="dropdown-item">Registros de evaluacion</a>
                        </div>
                    </li>
                <?php endif; ?>
                <?php if (\Core\Auth::esDocenteAcademico()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle arrow-none" href="#" id="nav-matriculas" role="button"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="mdi mdi-bank-transfer"></i> Unidades Didácticas <div class="arrow-down"></div>
                        </a>
                        <div class="dropdown-menu" aria-labelledby="nav-matriculas">
                            <a href="<?= BASE_URL ?>/academico/unidadesDidacticas" class="dropdown-item">Mis Unidades Didácticas</a>
                        </div>
                    </li>
                <?php endif; ?>
                <?php if ((\Core\Auth::esAdminAcademico()) || (\Core\Auth::esDirectorAcademico()) || (\Core\Auth::esSecretarioAcadAcademico()) || (\Core\Auth::esJUAAcademico()) || (\Core\Auth::esCoordinadorPEAcademico())): ?>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?= BASE_URL ?>/academico/reportes">
                            <i class="mdi mdi-chart-line"></i> Reportes
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ((\Core\Auth::esEstudianteAcademico())): ?>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?= BASE_URL ?>/academico/calificaciones/consulta">
                            <i class="mdi mdi-chart-line"></i> Calificaciones
                        </a>
                    </li>
                <?php endif; ?>
            <?php else: ?>
                <!-- Aquí va solo lo mínimo, o un mensaje, o nada -->
                <!-- O simplemente no muestres nada más -->
            <?php endif; ?>
        </ul>
    </div>
</nav>