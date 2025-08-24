<?php require __DIR__ . '/../../layouts/header.php'; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success alert-dismissible">
    <?= $_SESSION['flash_success'] ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card p-2">
  <h3 class="mb-2">Suscripciones</h3>

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
      <label>Plan</label>
      <select id="filter-plan" class="form-control">
        <option value="">Todos</option>
        <?php foreach ($planes as $pl): ?>
          <option value="<?= (int)$pl['id'] ?>"><?= htmlspecialchars($pl['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label>Estado</label>
      <select id="filter-estado" class="form-control">
        <option value="">Todos</option>
        <option value="trial">Trial</option>
        <option value="activa">Activa</option>
        <option value="suspendida">Suspendida</option>
        <option value="cancelada">Cancelada</option>
      </select>
    </div>
    <div class="col-md-2">
      <label>Ciclo</label>
      <select id="filter-ciclo" class="form-control">
        <option value="">Todos</option>
        <option value="mensual">Mensual</option>
        <option value="anual">Anual</option>
      </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <a href="<?= BASE_URL ?>/admin/suscripcion/nuevo" class="btn btn-success mt-2">Nueva Suscripción</a>
    </div>
  </div>

  <div class="table-responsive">
    <table id="tabla-subs" class="table table-bordered table-hover table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>IES</th>
          <th>Plan</th>
          <th>Ciclo</th>
          <th>Inicia</th>
          <th>Vence</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody><!-- DataTables --></tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!window.jQuery) { console.error('jQuery no cargado'); return; }
  $.fn.dataTable.ext.errMode = 'none';

  const tabla = $('#tabla-subs').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    ajax: {
      url: '<?= BASE_URL ?>/admin/suscripcion/data',
      type: 'GET',
      data: function(d){
        d.filter_ies    = $('#filter-ies').val();
        d.filter_plan   = $('#filter-plan').val();
        d.filter_estado = $('#filter-estado').val();
        d.filter_ciclo  = $('#filter-ciclo').val();
      }
    },
    columns: [
      { data: null, render: (d,t,r,m) => m.row + 1 + m.settings._iDisplayStart },
      { data: 'nombre_ies' },
      { data: 'plan_nombre' },
      { data: 'ciclo', render: d => d === 'anual' ? 'Anual' : 'Mensual' },
      { data: 'inicia' },
      { data: 'vence' },
      { data: 'estado', render: function(d){
          const map = {
            'trial':      'badge-info',
            'activa':     'badge-success',
            'suspendida': 'badge-danger',
            'cancelada':  'badge-secondary'
          };
          const cls = map[d] || 'badge-light';
          return `<span class="badge ${cls} text-uppercase">${d}</span>`;
        }
      },
      {
        data: null, orderable:false, searchable:false,
        render: function(row){
          const id = row.id;
          const btnEdit = `<a href="<?= BASE_URL ?>/admin/suscripcion/editar/${id}" class="btn btn-warning btn-sm m-1">Editar</a>`;
          let btnEstado = '';
          if (row.estado === 'activa' || row.estado === 'trial') {
            btnEstado = `<form action="<?= BASE_URL ?>/admin/suscripcion/suspender/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Suspender esta suscripción?');">
                           <button class="btn btn-secondary btn-sm m-1">Suspender</button>
                         </form>`;
          } else if (row.estado === 'suspendida') {
            btnEstado = `<form action="<?= BASE_URL ?>/admin/suscripcion/reactivar/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Reactivar esta suscripción?');">
                           <button class="btn btn-success btn-sm m-1">Reactivar</button>
                         </form>`;
          }
          const btnCancel = (row.estado !== 'cancelada')
            ? `<form action="<?= BASE_URL ?>/admin/suscripcion/cancelar/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Cancelar definitivamente?');">
                 <button class="btn btn-danger btn-sm m-1">Cancelar</button>
               </form>` : '';
          return btnEdit + btnEstado + btnCancel;
        }
      }
    ],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
  });

  $('#filter-ies, #filter-plan, #filter-estado, #filter-ciclo').on('change', function(){
    tabla.ajax.reload();
  });

  $('#tabla-subs').on('xhr.dt', function (e, settings, json, xhr) {
    if (xhr.status !== 200) {
      console.error('Ajax status:', xhr.status);
      console.error('Response:', xhr.responseText);
    }
  });
});
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
