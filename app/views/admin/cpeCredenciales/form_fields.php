<?php
// $data (array) + $iesList (array) vienen del controller
$hasSol = !empty($data['sol_pass_enc']);
$hasPfx = !empty($data['cert_pfx_enc']);
?>

<div class="row">
  <div class="col-md-6 mb-3">
    <label class="form-label">IES *</label>
    <select name="id_ies" class="form-control" required>
      <option value="">-- Seleccione --</option>
      <?php foreach(($iesList ?? []) as $i): ?>
        <option value="<?= (int)$i['id'] ?>"
          <?= ((int)($data['id_ies'] ?? 0) === (int)$i['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($i['nombre_ies']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-3 mb-3">
    <label class="form-label">Modo *</label>
    <select name="modo" class="form-control" required>
      <?php $modo = ($data['modo'] ?? 'beta'); ?>
      <option value="beta" <?= ($modo === 'beta') ? 'selected' : '' ?>>beta</option>
      <option value="prod" <?= ($modo === 'prod') ? 'selected' : '' ?>>prod</option>
    </select>
  </div>

  <div class="col-md-3 mb-3 d-flex align-items-end">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="activo" id="chkActivo"
        <?= ((int)($data['activo'] ?? 1) === 1) ? 'checked' : '' ?>>
      <label class="form-check-label" for="chkActivo">Activo</label>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-6 mb-3">
    <label class="form-label">SOL User *</label>
    <input type="text" name="sol_user" class="form-control" maxlength="30" required
           value="<?= htmlspecialchars($data['sol_user'] ?? '') ?>">
  </div>

  <div class="col-md-6 mb-3">
    <label class="form-label">SOL Password <?= !empty($data['id']) ? '(opcional)' : '*' ?></label>
    <input type="password" name="sol_pass" class="form-control" maxlength="100"
           placeholder="<?= !empty($data['id']) ? 'Dejar vacío para no cambiar' : 'Obligatorio en primera configuración' ?>">
    <?php if (!empty($data['id'])): ?>
      <small class="text-muted">
        Estado actual: <?= $hasSol ? '<span class="badge badge-success">registrado</span>' : '<span class="badge badge-secondary">vacío</span>' ?>
      </small>
    <?php endif; ?>
  </div>
</div>

<div class="row">
  <div class="col-md-6 mb-3">
    <label class="form-label">Certificado PFX <?= !empty($data['id']) ? '(opcional)' : '*' ?></label>
    <input type="file" name="cert_pfx" class="form-control" accept=".pfx">
    <?php if (!empty($data['id'])): ?>
      <small class="text-muted">
        Estado actual: <?= $hasPfx ? '<span class="badge badge-success">registrado</span>' : '<span class="badge badge-secondary">vacío</span>' ?>
        &nbsp;|&nbsp; (si subes otro, reemplaza)
      </small>
    <?php else: ?>
      <small class="text-muted">Sube el archivo .pfx</small>
    <?php endif; ?>
  </div>

  <div class="col-md-6 mb-3">
    <label class="form-label">Password del PFX <?= !empty($data['id']) ? '(opcional)' : '*' ?></label>
    <input type="password" name="cert_pass" class="form-control" maxlength="100"
           placeholder="<?= !empty($data['id']) ? 'Dejar vacío para no cambiar' : 'Obligatorio en primera configuración' ?>">
  </div>
</div>

<?php if (empty($data['id'])): ?>
  <div class="alert alert-info">
    <b>Primera configuración:</b> requiere SOL password + PFX + password del PFX.
  </div>
<?php else: ?>
  <div class="alert alert-secondary">
    <b>Edición:</b> si dejas en blanco SOL password / PFX / password del PFX, se mantienen los valores actuales.
  </div>
<?php endif; ?>
