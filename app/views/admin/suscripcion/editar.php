<?php require __DIR__ . '/../../layouts/header.php'; ?>
<?php if (!empty($errores)): ?>
  <div class="alert alert-danger">
    <ul><?php foreach($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="card p-2">
  <h4>Editar Suscripción</h4>
  <form action="<?= BASE_URL ?>/admin/suscripcion/guardar" method="post" class="card p-4 shadow-sm rounded-3" autocomplete="off">
    <input type="hidden" name="id" value="<?= (int)($sub['id'] ?? 0) ?>">
    <?php include __DIR__.'/form_fields.php'; ?>
    <div class="mt-3 text-end">
      <button type="submit" class="btn btn-success px-4">Guardar cambios</button>
      <a href="<?= BASE_URL ?>/admin/suscripcion" class="btn btn-secondary">Cancelar</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
