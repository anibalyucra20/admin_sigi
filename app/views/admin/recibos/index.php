<?php require __DIR__ . '/../../layouts/header.php'; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success alert-dismissible">
    <?= $_SESSION['flash_success'] ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div><?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card p-2">
  <h3 class="mb-2">Recibos</h3>
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
    <div class="col-md-2">
      <label>Periodo (AAAAMM)</label>
      <input id="filter-periodo" class="form-control" placeholder="202508">
    </div>
    <div class="col-md-2">
      <label>Estado</label>
      <select id="filter-estado" class="form-control">
        <option value="">Todos</option>
        <option value="pendiente">Pendiente</option>
        <option value="pagada">Pagada</option>
        <option value="vencida">Vencida</option>
        <option value="anulada">Anulada</option>
      </select>
    </div>
    <div class="col-md-2">
      <label>Pasarela</label>
      <input id="filter-pasarela" class="form-control" placeholder="Culqi / ...">
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <a href="<?= BASE_URL ?>/admin/recibos/nuevo" class="btn btn-success mt-2 mr-2">Nuevo Recibo</a>
      <form action="<?= BASE_URL ?>/admin/recibos/generarPendientes" method="post" class="d-inline"
        onsubmit="return confirm('¿Generar recibos faltantes para todas las suscripciones vigentes?');">
        <button class="btn btn-primary mt-2">Generar pendientes</button>
      </form>
    </div>


  </div>

  <div class="table-responsive">
    <table id="tabla-recibos" class="table table-bordered table-hover table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>IES</th>
          <th>Periodo</th>
          <th>Total</th>
          <th>Moneda</th>
          <th>Estado</th>
          <th>Pasarela</th>
          <th>Vence</th>
          <th>Pagado</th>
          <th>Saldo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const tabla = $('#tabla-recibos').DataTable({
      processing: true,
      serverSide: true,
      searching: true,
      ajax: {
        url: '<?= BASE_URL ?>/admin/recibos/data',
        type: 'GET',
        data: function(d) {
          d.filter_ies = $('#filter-ies').val();
          d.filter_periodo = $('#filter-periodo').val();
          d.filter_estado = $('#filter-estado').val();
          d.filter_pasarela = $('#filter-pasarela').val();
        }
      },
      columns: [{
          data: null,
          render: (d, t, r, m) => m.row + 1 + m.settings._iDisplayStart
        },
        {
          data: 'nombre_ies'
        },
        {
          data: 'periodo_aaaamm'
        },
        {
          data: 'total'
        },
        {
          data: 'moneda'
        },
        {
          data: 'estado',
          render: d => {
            const map = {
              pendiente: 'badge-warning',
              pagada: 'badge-success',
              vencida: 'badge-danger',
              anulada: 'badge-secondary'
            };
            return `<span class="badge ${map[d]||'badge-light'} text-uppercase">${d}</span>`;
          }
        },
        {
          data: 'pasarela',
          render: d => d || '-'
        },
        {
          data: 'due_at',
          render: d => d || '-'
        },
        {
          data: 'pagado'
        },
        {
          data: 'saldo'
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: row => {
            const id = row.id;
            const btnEdit = `<a href="<?= BASE_URL ?>/admin/recibos/editar/${id}" class="btn btn-warning btn-sm m-1">Editar</a>`;
            const btnAnu = (row.estado !== 'anulada') ? `<form action="<?= BASE_URL ?>/admin/recibos/anular/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Anular recibo?');"><button class="btn btn-secondary btn-sm m-1">Anular</button></form>` : '';
            const btnPag = (row.estado !== 'pagada') ? `<form action="<?= BASE_URL ?>/admin/recibos/marcarPagado/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Marcar como pagada?');"><button class="btn btn-success btn-sm m-1">Marcar pagada</button></form>` : '';
            return btnEdit + btnAnu + btnPag;
          }
        }
      ],
      language: {
        url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
      }
    });
    $('#filter-ies,#filter-periodo,#filter-estado,#filter-pasarela').on('change keyup', () => tabla.ajax.reload());
  });
</script>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>