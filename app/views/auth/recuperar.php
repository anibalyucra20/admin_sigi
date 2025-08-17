<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña - SIGI</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <?php
    // Favicon
    $ruta_favicon = !empty($datosSistema['favicon'])
        ? BASE_URL . '/images/' . $datosSistema['favicon']
        : BASE_URL . '/img/favicon.ico';

    // Logo
    $ruta_logo = !empty($datosSistema['logo'])
        ? BASE_URL . '/images/' . $datosSistema['logo']
        : BASE_URL . '/img/logo_completo.png';

    // Color de fondo seguro
    $rawColor = trim($datosSistema['color_correo'] ?? '');
    if ($rawColor === '' || !preg_match('/^#?[0-9A-Fa-f]{3,8}$/', $rawColor)) {
        $rawColor = '#062758'; // fallback
    }
    if ($rawColor[0] !== '#') {
        $rawColor = '#' . $rawColor;
    }
    ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($ruta_favicon) ?>">
    <style>
        body {
            min-height: 100vh;
        }

        .login-container {
            min-height: 100vh;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
        }

        .logo {
            width: 200px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body style="background-color: <?= htmlspecialchars($rawColor) ?>;">
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
                    <img src="<?= htmlspecialchars($ruta_logo) ?>" alt="Logo SIGI" class="logo">
                    <h4 class="mb-2"><?= htmlspecialchars($datosSistema['nombre_corto'] ?? 'SIGI') ?></h4>
                    <p class="text-muted mb-4">Sistema de Gestión Institucional</p>
                    <p>Recuperar contraseña</p>
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

                <form id="formRecuperar" action="<?= BASE_URL ?>/enviarRecuperacion" method="POST">
                    <!-- CSRF (en el controlador generar y pasar $csrfToken) -->
                    <?php if (!empty($csrfToken)): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="email">Correo</label>
                        <input type="email"
                            name="email"
                            id="email"
                            class="form-control"
                            placeholder="Ingrese su correo"
                            autocomplete="email"
                            required
                            autofocus>
                    </div>

                    <div class="form-group">
                        <label for="dni">DNI</label>
                        <input type="text"
                            name="dni"
                            id="dni"
                            class="form-control"
                            placeholder="Ingrese su DNI"
                            inputmode="numeric"
                            pattern="[0-9]{8,12}"
                            maxlength="12"
                            required>
                        <small class="text-muted">Ingrese solo números (no use puntos ni guiones).</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Recuperar</button>
                    <a href="<?= BASE_URL ?>/login" class="btn btn-link btn-block">Volver al inicio de sesión</a>
                </form>
            </div>
        </div>
    </div>
    <!-- jQuery slim = ok (no Ajax requerido aquí) -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Evitar doble envío
        (function() {
            const form = document.getElementById('formRecuperar');
            form.addEventListener('submit', function(e) {
                const btn = form.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = 'Enviando...';
            });
        })();
    </script>
</body>

</html>