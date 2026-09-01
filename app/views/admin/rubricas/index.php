<?php require __DIR__ . '/../../layouts/header.php'; ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success alert-dismissible">
    <?= $_SESSION['flash_success'] ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card p-2">
  <h3 class="mb-2">Rúbricas Institucionales (Máster)</h3>
  <div class="col-md-3 mb-2">
    <a href="<?= BASE_URL ?>/admin/rubricas/nuevo" class="btn btn-success mt-2">Nueva Rúbrica</a>
  </div>

  <div class="table-responsive">
    <table id="tabla-rubricas" class="table table-bordered table-hover table-sm align-middle w-100">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Nombre</th>
          <th>Técnica</th>
          <th>Estado</th>
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

  const tabla = $('#tabla-rubricas').DataTable({
    processing: true,
    serverSide: true,
    searching: true, // Activado porque tu modelo soporta búsqueda ($search)
    ajax: { url: '<?= BASE_URL ?>/admin/rubricas/data', type: 'GET' },
    columns: [
      { data: null, render: (d,t,r,m) => m.row + 1 + m.settings._iDisplayStart },
      { data: 'nombre' },
      { data: 'tipo_tecnica' },
      { data: 'estado', render: d => d == 1 || d === '1' 
          ? '<span class="badge badge-success">Activa</span>' 
          : '<span class="badge badge-danger">Inactiva</span>' },
      {
        data: null, orderable:false, searchable:false,
        render: function(row){
          const id = row.id;
          const btnEdit = `<a href="<?= BASE_URL ?>/admin/rubricas/editar/${id}" class="btn btn-warning btn-sm m-1">Editar</a>`;
          
          const btnSusp = (row.estado == 1 || row.estado === '1')
            ? `<form action="<?= BASE_URL ?>/admin/rubricas/suspender/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Inactivar esta Rúbrica?');">
                 <button class="btn btn-secondary btn-sm m-1">Inactivar</button>
               </form>`
            : `<form action="<?= BASE_URL ?>/admin/rubricas/reactivar/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Reactivar esta Rúbrica?');">
                 <button class="btn btn-success btn-sm m-1">Reactivar</button>
               </form>`;
          return btnEdit + btnSusp;
        }
      }
    ],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
  });

  $('#tabla-rubricas').on('xhr.dt', function (e, s, json, xhr) {
    if (xhr.status !== 200) {
      console.error('Ajax status:', xhr.status);
      console.error('Response:', xhr.responseText);
    }
  });
});
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>