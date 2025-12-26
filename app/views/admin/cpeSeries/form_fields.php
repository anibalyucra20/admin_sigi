<?php
// $data (array) + $iesList (array) vienen del controller
$tipo = (string)($data['tipo_doc'] ?? '01');
$activo = (int)($data['activo'] ?? 1);
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
    <label class="form-label">Tipo Doc *</label>
    <select name="tipo_doc" class="form-control" required>
      <option value="01" <?= ($tipo === '01') ? 'selected' : '' ?>>01 - Factura</option>
      <option value="03" <?= ($tipo === '03') ? 'selected' : '' ?>>03 - Boleta</option>
      <option value="07" <?= ($tipo === '07') ? 'selected' : '' ?>>07 - Nota crédito</option>
      <option value="08" <?= ($tipo === '08') ? 'selected' : '' ?>>08 - Nota débito</option>
    </select>
    <small class="text-muted">char(2)</small>
  </div>

  <div class="col-md-3 mb-3 d-flex align-items-end">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="activo" id="chkActivo"
        <?= ($activo === 1) ? 'checked' : '' ?>>
      <label class="form-check-label" for="chkActivo">Activo</label>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-4 mb-3">
    <label class="form-label">Serie *</label>
    <input type="text" name="serie" class="form-control" maxlength="8" required
           placeholder="Ej: F001"
           value="<?= htmlspecialchars($data['serie'] ?? '') ?>">
    <small class="text-muted">varchar(8)</small>
  </div>

  <div class="col-md-4 mb-3">
    <label class="form-label">Correlativo actual *</label>
    <input type="number" name="correlativo_actual" class="form-control" min="0" step="1" required
           value="<?= htmlspecialchars((string)($data['correlativo_actual'] ?? 0)) ?>">
    <small class="text-muted">int</small>
  </div>

  <div class="col-md-4 mb-3">
    <label class="form-label">Nota</label>
    <div class="alert alert-secondary p-2 m-0">
      UNIQUE: (id_ies, tipo_doc, serie)
    </div>
  </div>
</div>
