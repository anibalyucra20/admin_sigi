<div class="row">
  <div class="col-md-3 mb-3">
    <label class="form-label">RUC *</label>
    <input type="text" name="ruc" class="form-control" maxlength="20" required
           value="<?= htmlspecialchars($ies['ruc'] ?? '') ?>">
  </div>
  <div class="col-md-6 mb-3">
    <label class="form-label">Nombre de la IES *</label>
    <input type="text" name="nombre_ies" class="form-control" maxlength="300" required
           value="<?= htmlspecialchars($ies['nombre_ies'] ?? '') ?>">
  </div>
  <div class="col-md-3 mb-3">
    <label class="form-label">Dominio *</label>
    <input type="text" name="dominio" class="form-control" maxlength="100" required
           value="<?= htmlspecialchars($ies['dominio'] ?? '') ?>">
  </div>
</div>

<div class="row">
  <div class="col-md-6 mb-3">
    <label class="form-label">Dirección</label>
    <input type="text" name="direccion" class="form-control" maxlength="300"
           value="<?= htmlspecialchars($ies['direccion'] ?? '') ?>">
  </div>
  <div class="col-md-3 mb-3">
    <label class="form-label">Teléfono</label>
    <input type="text" name="telefono" class="form-control" maxlength="15"
           value="<?= htmlspecialchars($ies['telefono'] ?? '') ?>">
  </div>
  <div class="col-md-3 mb-3">
    <label class="form-label">Llave</label>
    <input type="text" name="llave" class="form-control" maxlength="30"
           value="<?= htmlspecialchars($ies['llave'] ?? '') ?>">
  </div>
</div>
