<div class="row">
  <div class="col-md-6 mb-3">
    <label class="form-label">IES *</label>
    <select name="id_ies" class="form-control" required>
      <option value="">Seleccione...</option>
      <?php foreach ($ies as $x): ?>
        <option value="<?= (int)$x['id'] ?>"><?= htmlspecialchars($x['nombre_ies']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-6 mb-3">
    <label class="form-label">Nombre *</label>
    <input type="text" name="nombre" class="form-control" maxlength="100" value="default" required>
    <small class="text-muted">Etiqueta para distinguir la clave (por ejemplo: "default", "backend", "reportes").</small>
  </div>
</div>
