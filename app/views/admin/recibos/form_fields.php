<div class="row">
  <div class="col-md-5 mb-3">
    <label>IES *</label>
    <select name="id_ies" class="form-control" required>
      <option value="">Seleccione...</option>
      <?php foreach($ies as $x): ?>
        <option value="<?= (int)$x['id'] ?>" <?= (!empty($iv['id_ies']) && $iv['id_ies']==$x['id'])?'selected':'' ?>>
          <?= htmlspecialchars($x['nombre_ies']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3 mb-3">
    <label>Periodo (AAAAMM) *</label>
    <input name="periodo_aaaamm" class="form-control" maxlength="6" required
           value="<?= htmlspecialchars($iv['periodo_aaaamm'] ?? '') ?>" placeholder="202508">
  </div>
  <div class="col-md-2 mb-3">
    <label>Moneda *</label>
    <input name="moneda" class="form-control" maxlength="3" required
           value="<?= htmlspecialchars($iv['moneda'] ?? 'PEN') ?>">
  </div>
  <div class="col-md-2 mb-3">
    <label>Total *</label>
    <input name="total" type="number" step="0.01" min="0" class="form-control" required
           value="<?= htmlspecialchars($iv['total'] ?? '0.00') ?>">
  </div>
</div>
<div class="row">
  <div class="col-md-3 mb-3">
    <label>Estado</label>
    <?php $st=$iv['estado'] ?? 'pendiente'; ?>
    <select name="estado" class="form-control">
      <option value="pendiente" <?= $st==='pendiente'?'selected':'' ?>>Pendiente</option>
      <option value="pagada"    <?= $st==='pagada'?'selected':'' ?>>Pagada</option>
      <option value="vencida"   <?= $st==='vencida'?'selected':'' ?>>Vencida</option>
      <option value="anulada"   <?= $st==='anulada'?'selected':'' ?>>Anulada</option>
    </select>
  </div>
  <div class="col-md-3 mb-3">
    <label>Pasarela</label>
    <input name="pasarela" class="form-control" value="<?= htmlspecialchars($iv['pasarela'] ?? '') ?>">
  </div>
  <div class="col-md-3 mb-3">
    <label>External ID</label>
    <input name="external_id" class="form-control" value="<?= htmlspecialchars($iv['external_id'] ?? '') ?>">
  </div>
  <div class="col-md-3 mb-3">
    <label>Fecha de vencimiento</label>
    <input type="date" name="due_at" class="form-control" value="<?= htmlspecialchars($iv['due_at'] ?? '') ?>">
  </div>
</div>
