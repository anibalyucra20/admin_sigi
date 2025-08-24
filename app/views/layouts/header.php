<?php

use Core\Auth;

Auth::start();
$logueado = Auth::user() !== null;

if ($logueado):
  $db = (new \Core\Model())->getDB();
  $userLogin = $_SESSION['admin_sigi_user_id'] ?? null;
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
  $ruta_favicon = BASE_URL . '/img/favicon.ico';
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
              $ruta_logo = BASE_URL . '/img/logo_completo.png';
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
          </div>
          <div class="d-flex align-items-center">
            <div class="dropdown d-inline-block ml-2">
              <button type="button" class="btn header-item waves-effect waves-light"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img class="rounded-circle header-profile-user"
                  src="<?= BASE_URL ?>/img/user.png"
                  alt="Header Avatar">
                <span class="d-none d-sm-inline-block ml-1"><?= $_SESSION['admin_sigi_user_name'] ?? 'Usuario' ?></span>
                <i class="mdi mdi-chevron-down d-none d-sm-inline-block"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-right">
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                  href="<?= BASE_URL ?>/perfil">
                  <span>Mi perfil</span>
                </a>
                <a class="dropdown-item d-flex align-items-center justify-content-between"
                  href="<?= BASE_URL ?>/resetPassword?data=<?= base64_encode($_SESSION['admin_sigi_user_id']) ?>&back=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
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
          <nav class="navbar navbar-light navbar-expand-lg topnav-menu">
            <div class="collapse navbar-collapse" id="topnav-menu-content">
              <ul class="navbar-nav">
                <li class="nav-item">
                  <a class="nav-link <?= ($_SERVER['REQUEST_URI'] === '/admin' ? 'active' : '') ?>"
                    href="<?= BASE_URL ?>/admin">
                    <i class="mdi mdi-home-analytics"></i> Inicio
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/planes') !== false ? 'active' : '' ?>"
                    href="<?= BASE_URL ?>/admin/plan">
                    <i class="mdi mdi-school"></i> Planes
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/biblioteca') !== false ? 'active' : '' ?>"
                    href="<?= BASE_URL ?>/admin/biblioteca">
                    <i class="mdi mdi-school"></i> Biblioteca
                  </a>
                </li>
                <li class="nav-item dropdown">
                  <a class="nav-link dropdown-toggle arrow-none" href="#" id="nav-matriculas" role="button"
                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="mdi mdi-bank-transfer"></i> IES <div class="arrow-down"></div>
                  </a>
                  <div class="dropdown-menu" aria-labelledby="nav-matriculas">
                    <a class="dropdown-item" href="<?= BASE_URL ?>/admin/ies">IES</a>
                    <a class="dropdown-item" href="<?= BASE_URL ?>/admin/apiKeys">Api Keys</a>
                    <a class="dropdown-item" href="<?= BASE_URL ?>/admin/recibos">Recibos</a>
                    <a class="dropdown-item" href="<?= BASE_URL ?>/admin/pagos">Pagos</a>
                  </div>
                </li>
                <li class="nav-item">
                  <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/suscripcion') !== false ? 'active' : '' ?>"
                    href="<?= BASE_URL ?>/admin/suscripcion">
                    <i class="mdi mdi-school"></i> Suscripciones
                  </a>
                </li>
                <!--<li class="nav-item">
                  <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/apiKeys') !== false ? 'active' : '' ?>"
                    href="<?= BASE_URL ?>/admin/apiKeys">
                    <i class="mdi mdi-school"></i> APIS
                  </a>
                </li>-->

              </ul>
            </div>
          </nav>
        </div>
      </div>

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
          <?php endif; ?>