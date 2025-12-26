<?php require __DIR__ . '/../../layouts/header.php'; ?>
<div class="card p-2">
  <h4>Nuevo IES</h4>
  <form action="<?= BASE_URL ?>/admin/ies/guardar" method="post" class="card p-4 shadow-sm rounded-3" autocomplete="off">
    <?php $ies = $ies ?? []; include __DIR__.'/form_fields.php'; ?>
    <div class="mt-3 text-end">
      <button type="submit" class="btn btn-success px-4">Guardar</button>
      <a href="<?= BASE_URL ?>/admin/ies" class="btn btn-secondary">Cancelar</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
