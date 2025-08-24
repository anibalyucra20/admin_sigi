<div class="row">
  <div class="col-md-4 mb-3">
    <label>IES (para filtrar recibos)</label>
    <select id="sel-ies" class="form-control">
      <option value="">Todos</option>
      <?php foreach ($ies as $x): ?>
        <option value="<?= (int)$x['id'] ?>"><?= htmlspecialchars($x['nombre_ies']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-8 mb-3">
    <label>Recibo *</label>
    <select name="id_invoice" id="sel-invoice" class="form-control" required>
      <option value="">Seleccione...</option>
      <?php foreach ($invoices as $iv): ?>
        <option value="<?= (int)$iv['id'] ?>"
          <?= (!empty($pago['id_invoice']) && $pago['id_invoice'] == $iv['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($iv['etiqueta']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</div>
<div class="row">
  <div class="col-md-3 mb-3">
    <label>Monto *</label>
    <input type="number" step="0.01" min="0" name="monto" class="form-control" required
      value="<?= htmlspecialchars($pago['monto'] ?? '0.00') ?>">
  </div>
  <div class="col-md-2 mb-3">
    <label>Moneda *</label>
    <input name="moneda" class="form-control" maxlength="3" required value="<?= htmlspecialchars($pago['moneda'] ?? 'PEN') ?>">
  </div>
  <div class="col-md-3 mb-3">
    <label>Pasarela</label>
    <input name="pasarela" class="form-control" value="<?= htmlspecialchars($pago['pasarela'] ?? '') ?>">
  </div>
  <div class="col-md-4 mb-3">
    <label>External ID</label>
    <input name="external_id" class="form-control" value="<?= htmlspecialchars($pago['external_id'] ?? '') ?>">
  </div>
</div>
<div class="row">
  <div class="col-md-3 mb-3">
    <label>Estado *</label>
    <?php $st = $pago['estado'] ?? 'pendiente'; ?>
    <select name="estado" class="form-control" required>
      <option value="pendiente" <?= $st === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
      <option value="pagado" <?= $st === 'pagado' ? 'selected' : '' ?>>Pagado</option>
      <option value="fallido" <?= $st === 'fallido' ? 'selected' : '' ?>>Fallido</option>
      <option value="revertido" <?= $st === 'revertido' ? 'selected' : '' ?>>Revertido</option>
    </select>
  </div>
  <div class="col-md-3 mb-3">
    <label>Fecha/Hora pago</label>
    <input type="datetime-local" name="paid_at" class="form-control"
      value="<?= !empty($pago['paid_at']) ? date('Y-m-d\TH:i', strtotime($pago['paid_at'])) : date('Y-m-d\TH:i'); ?>">
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    $('#sel-ies').on('change', function() {
      const v = $(this).val();
      $('#sel-invoice').html('<option value="">Seleccione...</option>');
      if (v) {
        $.getJSON('<?= BASE_URL ?>/admin/pagos/recibosPorIes/' + v, function(items) {
          items.forEach(it => $('#sel-invoice').append(`<option value="${it.id}">${it.etiqueta}</option>`));
        });
      }
    });
  });
</script>