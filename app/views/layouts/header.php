<?php

use Core\Auth;

Auth::start();
$logueado = Auth::user() !== null;

if ($logueado):
  $db = (new \Core\Model())->getDB();
  $userLogin = $_SESSION['sigi_user_id'] ?? null;
  //"SELECT s.id, s.nombre FROM sigi_sedes s INNER JOIN sigi_usuarios u ON u.id_sede = s.id WHERE u.id='$userLogin' ORDER BY s.nombre"
  //SELECT id, nombre FROM sigi_sedes ORDER BY nombre"
  $sedess = $db->query("SELECT s.id, s.nombre FROM sigi_sedes s INNER JOIN sigi_usuarios u ON u.id_sede = s.id WHERE u.id='$userLogin' ORDER BY s.nombre")->fetchAll(PDO::FETCH_ASSOC);
  $periodos = $db->query("SELECT id, nombre FROM sigi_periodo_academico ORDER BY fecha_inicio DESC")->fetchAll(PDO::FETCH_ASSOC);

  $_SESSION['sigi_sede_actual'] = $sedess[0]['id'] ?? 0;
  $sedeActual    = $_SESSION['sigi_sede_actual'] ?? 0;
  $periodoActual = $_SESSION['sigi_periodo_actual_id'] ?? ($periodos[0]['id'] ?? 0);

  // Definir el id de admin en una variable por claridad
  $rolAdmin = 1;
  $rolActual = $_SESSION['sigi_rol_actual'] ?? null;
endif;
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <title><?= $pageTitle ?? 'SIGI' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="<?= BASE_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/icons.min.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>/assets/css/theme.min.css" rel="stylesheet" type="text/css" />
  <?php
  if ($_SESSION['favicon'] != '') {
    $ruta_favicon = BASE_URL . '/images/' . $_SESSION['favicon'];
  } else {
    $ruta_favicon = BASE_URL . '/img/favicon.ico';
  }
  ?>
  <link rel="icon" type="image/x-icon" href="<?= $ruta_favicon; ?>">
</head>

<body data-sidebar="light">
  <div id="layout-wrapper">
    <!-- SIEMPRE muestra el header y logo -->
    <?php if ($logueado): ?>
      <header id="page-topbar">
        <div class="navbar-header">
          <div class="navbar-brand-box d-flex align-items-left">
            <a href="<?= BASE_URL . '/' . $_SESSION['modulo_vista']; ?>" class="logo">
              <?php
              if ($_SESSION['logo'] != '') {
                $ruta_logo = BASE_URL . '/images/' . $_SESSION['logo'];
              } else {
                $ruta_logo = BASE_URL . '/img/logo_completo.png';
              }
              ?>
              <i class="mdi"><img src="<?= $ruta_logo ?>" alt="" width="100px" height="30px"></i>
              <span> SIGI</span>
            </a>
            <button type="button" class="btn btn-sm mr-2 font-size-16 d-lg-none header-item waves-effect waves-light"
              data-toggle="collapse" data-target="#topnav-menu-content">
              <i class="fa fa-fw fa-bars"></i>
            </button>
          </div>
          <div class="d-flex align-items-center">
            <div class="dropdown d-inline-block">
              <form action="<?= BASE_URL ?>/sedes/cambiarSesion" method="get" class="d-flex align-items-center">
                <label for="sedeee" class="me-2 small">Sede:</label>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                <select id="sedeee" name="sede" class="form-control me-2" onchange="this.form.submit()">
                  <?php foreach ($sedess as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $sedeActual ? 'selected' : '' ?>>
                      <?= $s['nombre'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </form>
            </div>
            <?php if ($_SESSION['sigi_modulo_actual'] != 0): ?>
              <div class="dropdown d-inline-block">
                <form action="<?= BASE_URL ?>/sigi/periodoAcademico/cambiarSesion" method="get" class="d-flex align-items-center">
                  <label for="periodo" class="me-2 small">Periodo:</label>
                  <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                  <select name="periodo" class="form-control me-2" onchange="this.form.submit()">
                    <?php foreach ($periodos as $p): ?>
                      <option value="<?= $p['id'] ?>" <?= $p['id'] == $periodoActual ? 'selected' : '' ?>>
                        <?= $p['nombre'] ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['sigi_permisos_usuario']) && $_SESSION['sigi_modulo_actual'] != 0): ?>
              <div class="dropdown d-inline-block">
                <form method="get" action="<?= BASE_URL ?>/sigi/rol/cambiarSesion" class="d-flex align-items-center">
                  <label for="permiso" class="me-2 small">Rol:</label>
                  <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                  <select name="permiso" id="permiso" class="form-control me-2" style="width:auto;" onchange="this.form.submit()">
                    <?php
                    $moduloActual = $_SESSION['sigi_modulo_actual'] ?? null;
                    foreach ($_SESSION['sigi_permisos_usuario'] as $permiso):
                      if ($permiso['id_sistema'] != $moduloActual) continue;
                    ?>
                      <option value="<?= $permiso['id_sistema'] ?>-<?= $permiso['id_rol'] ?>"
                        <?= ($_SESSION['sigi_rol_actual'] == $permiso['id_rol']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($permiso['rol']) ?>
                      </option>
                    <?php endforeach; ?>

                  </select>
                </form>
              </div>
            <?php endif; ?>
          </div>
          <div class="d-flex align-items-center">
            <div class="dropdown d-inline-block ml-2">
              <button type="button" class="btn header-item waves-effect waves-light"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img class="rounded-circle header-profile-user"
                  src="<?= BASE_URL ?>/img/user.png"
                  alt="Header Avatar">
                <span class="d-none d-sm-inline-block ml-1"><?= $_SESSION['sigi_user_name'] ?? 'Usuario' ?></span>
                <i class="mdi mdi-chevron-down d-none d-sm-inline-block"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-right">
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                  href="<?= BASE_URL ?>/perfil">
                  <span>Mi perfil</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                  href="<?= BASE_URL ?>/resetPassword?data=<?= base64_encode($_SESSION['sigi_user_id']) ?>&back=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                  <span>Cambiar contraseña</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between text-danger"
                  href="<?= BASE_URL ?>/logout">
                  <span>Cerrar sesión</span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </header>
    <?php endif; ?>
    <?php if ($logueado): ?>
      <div class="topnav">
        <div class="container-fluid">
          <?php
          $module = strtolower($module ?? 'sigi');
          include __DIR__ . "/menus/{$module}.php";
          ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Contenido principal -->
    <div class="main-content">
      <div class="page-content">
        <div class="container-fluid">
          <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
              <ul>
                <?php foreach ($errores as $e): ?>
                  <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success alert-dismissible">
              <?= $_SESSION['flash_success'] ?>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
          <?php endif; ?>
          <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger alert-dismissible">
              <?= $_SESSION['flash_error'] ?>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
          <?php endif; ?>