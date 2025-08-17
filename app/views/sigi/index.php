<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php if (\Core\Auth::esAdminSigi()): ?>
  <h3 class="mb-4">Dashboard SIGI</h3>

  <div class="row">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Periodo actual</h5>
          <p class="display-5"><?= htmlspecialchars($periodo) ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Sedes</h5>
          <p class="display-5"><?= $sedes_count ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Programas de estudio</h5>
          <p class="display-5"><?= $programas ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title">Docentes</h5>
          <p class="display-5"><?= $docentes ?></p>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <!-- Para director o coordinador en SIGI -->
  <p>El Modulo SIGI solo es para rol de Administrador</p>
<?php endif; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>