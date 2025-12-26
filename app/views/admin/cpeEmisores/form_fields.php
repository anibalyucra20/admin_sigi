<?php
// $data y $iesList vienen del controller
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

  <div class="col-md-6 mb-3">
    <label class="form-label">RUC *</label>
    <input type="text" name="ruc" class="form-control" maxlength="11" required
           value="<?= htmlspecialchars($data['ruc'] ?? '') ?>">
    <small class="text-muted">11 dígitos</small>
  </div>
</div>

<div class="row">
  <div class="col-md-8 mb-3">
    <label class="form-label">Razón social *</label>
    <input type="text" name="razon_social" class="form-control" maxlength="255" required
           value="<?= htmlspecialchars($data['razon_social'] ?? '') ?>">
  </div>

  <div class="col-md-4 mb-3">
    <label class="form-label">Nombre comercial</label>
    <input type="text" name="nombre_comercial" class="form-control" maxlength="255"
           value="<?= htmlspecialchars($data['nombre_comercial'] ?? '') ?>">
  </div>
</div>

<div class="row">
  <div class="col-md-3 mb-3">
    <label class="form-label">Ubigeo</label>
    <input type="text" name="ubigeo" class="form-control" maxlength="6"
           value="<?= htmlspecialchars($data['ubigeo'] ?? '') ?>">
  </div>

  <div class="col-md-9 mb-3">
    <label class="form-label">Dirección</label>
    <input type="text" name="direccion" class="form-control" maxlength="300"
           value="<?= htmlspecialchars($data['direccion'] ?? '') ?>">
  </div>
</div>

<div class="row">
  <div class="col-md-4 mb-3">
    <label class="form-label">Departamento</label>
    <input type="text" name="departamento" class="form-control" maxlength="100"
           value="<?= htmlspecialchars($data['departamento'] ?? '') ?>">
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label">Provincia</label>
    <input type="text" name="provincia" class="form-control" maxlength="100"
           value="<?= htmlspecialchars($data['provincia'] ?? '') ?>">
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label">Distrito</label>
    <input type="text" name="distrito" class="form-control" maxlength="100"
           value="<?= htmlspecialchars($data['distrito'] ?? '') ?>">
  </div>
</div>

<div class="row">
  <div class="col-md-8 mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" maxlength="150"
           value="<?= htmlspecialchars($data['email'] ?? '') ?>">
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label">Teléfono</label>
    <input type="text" name="telefono" class="form-control" maxlength="30"
           value="<?= htmlspecialchars($data['telefono'] ?? '') ?>">
  </div>
</div>
