<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php if (!empty($_SESSION['flash_error'])) : ?>
  <div class="alert alert-danger alert-dismissible">
    <?= $_SESSION['flash_error'] ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>
<h4 class="mb-4">Bienvenido, <?= htmlspecialchars($_SESSION['sigi_user_name']) ?></h4>

<div class="row">
  <?php foreach ($sistemas as $s) : ?>
    <div class="col-md-4 col-lg-3 mb-4">
      <a href="<?= BASE_URL ?>/<?= strtolower($s['codigo']) ?>" class="text-decoration-none">
        <div class="card shadow-sm h-100 text-center py-4">
          <i class="<?= htmlspecialchars($s['icono']) ?> display-4 active mb-3" style="color: <?= $datos_sistema['color_correo']; ?>;"></i>
          <h5 class="card-title"><?= htmlspecialchars($s['nombre']) ?></h5>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>