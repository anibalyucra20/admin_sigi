<?php require __DIR__ . '/../../layouts/header.php'; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-info alert-dismissible">
    <?= $_SESSION['flash_success'] ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card p-2">
  <h3 class="mb-2">API Keys</h3>

  <div class="row mb-3">
    <div class="col-md-4">
      <label>IES</label>
      <select id="filter-ies" class="form-control">
        <option value="">Todos</option>
        <?php foreach ($ies as $x): ?>
          <option value="<?= (int)$x['id'] ?>"><?= htmlspecialchars($x['nombre_ies']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label>Estado</label>
      <select id="filter-activo" class="form-control">
        <option value="">Todos</option>
        <option value="1">Activas</option>
        <option value="0">Inactivas</option>
      </select>
    </div>
    <div class="col-md-5 d-flex align-items-end">
      <a href="<?= BASE_URL ?>/admin/api-keys/nuevo" class="btn btn-success mt-2">Nueva API Key</a>
    </div>
  </div>

  <div class="table-responsive">
    <table id="tabla-keys" class="table table-bordered table-hover table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>IES</th>
          <th>Nombre</th>
          <th>Estado</th>
          <th>Último uso</th>
          <th>Creada</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!window.jQuery) { console.error('jQuery no cargado'); return; }
  $.fn.dataTable.ext.errMode = 'none';

  const tabla = $('#tabla-keys').DataTable({
    processing: true,
    serverSide: true,
    searching: true,
    ajax: {
      url: '<?= BASE_URL ?>/admin/apiKeys/data',
      type: 'GET',
      data: function(d){
        d.filter_ies    = $('#filter-ies').val();
        d.filter_activo = $('#filter-activo').val();
      }
    },
    columns: [
      { data: null, render: (d,t,r,m) => m.row + 1 + m.settings._iDisplayStart },
      { data: 'nombre_ies' },
      { data: 'nombre' },
      { data: 'activo', render: d => d == 1 ? '<span class="badge badge-success">Activa</span>' : '<span class="badge badge-secondary">Inactiva</span>' },
      { data: 'ultimo_uso', render: d => d ? d : '-' },
      { data: 'created_at' },
      {
        data: null, orderable:false, searchable:false,
        render: function(row){
          const id = row.id;
          const btnOnOff = row.activo == 1
            ? `<form action="<?= BASE_URL ?>/admin/apiKeys/desactivar/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Desactivar esta key?');"><button class="btn btn-secondary btn-sm m-1">Desactivar</button></form>`
            : `<form action="<?= BASE_URL ?>/admin/apiKeys/activar/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Activar esta key?');"><button class="btn btn-success btn-sm m-1">Activar</button></form>`;
          const btnRot = `<form action="<?= BASE_URL ?>/admin/apiKeys/rotar/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Rotar (generar nueva clave)?');"><button class="btn btn-warning btn-sm m-1">Rotar</button></form>`;
          return btnOnOff + btnRot;
        }
      }
    ],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
  });

  $('#filter-ies, #filter-activo').on('change', function(){
    tabla.ajax.reload();
  });

  $('#tabla-keys').on('xhr.dt', function (e, settings, json, xhr) {
    if (xhr.status !== 200) {
      console.error('Ajax status:', xhr.status);
      console.error('Response:', xhr.responseText);
    }
  });
});
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
