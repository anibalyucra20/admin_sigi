<?php require __DIR__ . '/../../layouts/header.php'; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success alert-dismissible">
    <?= $_SESSION['flash_success'] ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div><?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card p-2">
  <h3 class="mb-2">Pagos</h3>
  <div class="row mb-3">
    <div class="col-md-3">
      <label>IES</label>
      <select id="filter-ies" class="form-control">
        <option value="">Todos</option>
        <?php foreach ($ies as $x): ?>
          <option value="<?= (int)$x['id'] ?>"><?= htmlspecialchars($x['nombre_ies']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label>Recibo</label>
      <select id="filter-invoice" class="form-control">
        <option value="">Todos</option>
        <?php foreach ($invoices as $iv): ?>
          <option value="<?= (int)$iv['id'] ?>"><?= htmlspecialchars($iv['etiqueta']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label>Estado</label>
      <select id="filter-estado" class="form-control">
        <option value="">Todos</option>
        <option value="pendiente">Pendiente</option>
        <option value="pagado">Pagado</option>
        <option value="fallido">Fallido</option>
        <option value="revertido">Revertido</option>
      </select>
    </div>
    <div class="col-md-2">
      <label>Pasarela</label>
      <input id="filter-pasarela" class="form-control" placeholder="Culqi / ...">
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <a href="<?= BASE_URL ?>/admin/pagos/nuevo" class="btn btn-success mt-2">Nuevo Pago</a>
    </div>
  </div>

  <div class="table-responsive">
    <table id="tabla-pagos" class="table table-bordered table-hover table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Recibo</th>
          <th>IES</th>
          <th>Monto</th>
          <th>Moneda</th>
          <th>Pasarela</th>
          <th>Estado</th>
          <th>Pagado en</th>
          <th>Creado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const tabla = $('#tabla-pagos').DataTable({
      processing: true,
      serverSide: true,
      searching: true,
      ajax: {
        url: '<?= BASE_URL ?>/admin/pagos/data',
        type: 'GET',
        data: function(d) {
          d.filter_ies = $('#filter-ies').val();
          d.filter_invoice = $('#filter-invoice').val();
          d.filter_estado = $('#filter-estado').val();
          d.filter_pasarela = $('#filter-pasarela').val();
        }
      },
      columns: [{
          data: null,
          render: (d, t, r, m) => m.row + 1 + m.settings._iDisplayStart
        },
        {
          data: 'id_invoice',
          render: (d) => '#' + d
        },
        {
          data: 'nombre_ies'
        },
        {
          data: 'monto'
        },
        {
          data: 'moneda'
        },
        {
          data: 'pasarela',
          render: d => d || '-'
        },
        {
          data: 'estado',
          render: d => {
            const map = {
              pendiente: 'badge-warning',
              pagado: 'badge-success',
              fallido: 'badge-danger',
              revertido: 'badge-secondary'
            };
            return `<span class="badge ${map[d]||'badge-light'} text-uppercase">${d}</span>`;
          }
        },
        {
          data: 'paid_at',
          render: d => d || '-'
        },
        {
          data: 'created_at'
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: row => {
            const id = row.id;
            const btnEdit = `<a href="<?= BASE_URL ?>/admin/pagos/editar/${id}" class="btn btn-warning btn-sm m-1">Editar</a>`;
            const btnPag = (row.estado !== 'pagado') ? `<form action="<?= BASE_URL ?>/admin/pagos/marcar/${id}/pagado" method="post" class="d-inline" onsubmit="return confirm('¿Marcar como pagado?');"><button class="btn btn-success btn-sm m-1">Marcar pagado</button></form>` : '';
            const btnRev = (row.estado !== 'revertido') ? `<form action="<?= BASE_URL ?>/admin/pagos/marcar/${id}/revertido" method="post" class="d-inline" onsubmit="return confirm('¿Marcar revertido?');"><button class="btn btn-secondary btn-sm m-1">Revertir</button></form>` : '';
            return btnEdit + btnPag + btnRev;
          }
        }
      ],
      language: {
        url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
      }
    });

    $('#filter-ies').on('change', function() {
      const idIes = $(this).val();
      $('#filter-invoice').html('<option value="">Todos</option>');
      if (idIes) {
        $.getJSON('<?= BASE_URL ?>/admin/pagos/recibos-por-ies/' + idIes, function(items) {
          items.forEach(it => $('#filter-invoice').append(`<option value="${it.id}">${it.etiqueta}</option>`));
        });
      }
      tabla.ajax.reload();
    });
    $('#filter-invoice,#filter-estado').on('change', () => tabla.ajax.reload());
    $('#filter-pasarela').on('keyup', () => tabla.ajax.reload());
  });
</script>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>