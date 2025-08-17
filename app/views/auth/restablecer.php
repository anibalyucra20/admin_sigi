<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - SIGI</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <?php
  if ($datosSistema['favicon'] != '') {
    $ruta_favicon = BASE_URL . '/images/' . $datosSistema['favicon'];
  } else {
    $ruta_favicon = BASE_URL . '/img/favicon.ico';
  }
  ?>
  <link rel="icon" type="image/x-icon" href="<?= $ruta_favicon ?>">
  <style>
    body {

      min-height: 100vh;
    }

    .login-container {
      min-height: 100vh;
    }

    .card {
      border-radius: 12px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
    }

    .logo {
      width: 200px;
      margin-bottom: 10px;
    }
  </style>
</head>
<?php
//echo password_hash('',PASSWORD_DEFAULT);

?>

<body style="background-color: <?= $datosSistema['color_correo'] ?>;">
  <div class="container d-flex align-items-center justify-content-center login-container">
    <div class="col-md-6 col-lg-6">
      <div class="card p-4">
        <div class="text-center">
          <?php
          if ($datosSistema['logo'] != '') {
            $ruta_logo = BASE_URL . '/images/' . $datosSistema['logo'];
          } else {
            $ruta_logo = BASE_URL . '/img/logo_completo.png';
          }
          ?>
          <img src="<?= $ruta_logo ?>" alt="Logo SIGI" class="logo">
          <h4 class="mb-2">SIGI</h4>
          <p class="text-muted mb-4">Sistema Integrado de Gestión Institucional</p>
        </div>
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
        <form action="<?= BASE_URL ?>/guardarNuevaPassword" method="post">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <input type="hidden" name="data" value="<?= htmlspecialchars($data) ?>">
          <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="Ingrese su nueva Contraseña" required>
          </div>
          <div class="form-group">
            <label for="password_confirm">Confirmar contraseña</label>
            <input type="password" name="password_confirm" id="password_confirm" class="form-control" placeholder="Repita su contraseña" required>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Guardar</button>
        </form>
      </div>
    </div>
  </div>
  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>