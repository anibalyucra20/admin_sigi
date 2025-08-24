<div class="row">
  <div class="col-md-6 mb-3">
    <label class="form-label">Nombre *</label>
    <input type="text" name="nombre" class="form-control" maxlength="30" required
           value="<?= htmlspecialchars($plan['nombre'] ?? '') ?>">
  </div>
  <div class="col-md-3 mb-3">
    <label class="form-label">Monto (PEN) *</label>
    <input type="number" step="0.01" min="0" name="monto" class="form-control" required
           value="<?= htmlspecialchars($plan['monto'] ?? '0.00') ?>">
  </div>
  <div class="col-md-3 mb-3 d-flex align-items-end">
    <div class="form-check">
      <input type="checkbox" name="activo" id="activo" class="form-check-input"
             <?= (isset($plan['activo']) ? ((int)$plan['activo']===1?'checked':'') : 'checked') ?>>
      <label class="form-check-label" for="activo">Activo</label>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-3 mb-3">
    <label class="form-label">Límite Usuarios *</label>
    <input type="number" min="0" name="limite_usuarios" class="form-control" required
           value="<?= htmlspecialchars($plan['limite_usuarios'] ?? 0) ?>">
  </div>
  <div class="col-md-3 mb-3">
    <label class="form-label">Límite RENIEC *</label>
    <input type="number" min="0" name="limite_reniec" class="form-control" required
           value="<?= htmlspecialchars($plan['limite_reniec'] ?? 0) ?>">
  </div>
  <div class="col-md-3 mb-3">
    <label class="form-label">Límite ESCALE *</label>
    <input type="number" min="0" name="limite_escale" class="form-control" required
           value="<?= htmlspecialchars($plan['limite_escale'] ?? 0) ?>">
  </div>
  <div class="col-md-3 mb-3">
    <label class="form-label">Límite Facturación *</label>
    <input type="number" min="0" name="limite_facturacion" class="form-control" required
           value="<?= htmlspecialchars($plan['limite_facturacion'] ?? 0) ?>">
  </div>
</div>
