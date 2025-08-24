<?php require __DIR__ . '/../../layouts/header.php'; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success alert-dismissible">
    <?= $_SESSION['flash_success'] ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card p-2">
  <h3 class="mb-2">IES</h3>
  <div class="col-md-3 mb-2">
    <a href="<?= BASE_URL ?>/admin/ies/nuevo" class="btn btn-success mt-2">Nuevo IES</a>
  </div>

  <div class="table-responsive">
    <table id="tabla-ies" class="table table-bordered table-hover table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>RUC</th>
          <th>Nombre</th>
          <th>Dominio</th>
          <th>Teléfono</th>
          <th>Dirección</th>
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

  const tabla = $('#tabla-ies').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    ajax: { url: '<?= BASE_URL ?>/admin/ies/data', type: 'GET' },
    columns: [
      { data: null, render: (d,t,r,m) => m.row + 1 + m.settings._iDisplayStart },
      { data: 'ruc' },
      { data: 'nombre_ies' },
      { data: 'dominio' },
      { data: 'telefono' },
      { data: 'direccion' },
      { data: 'estado', render: d => d === 'activa'
          ? '<span class="badge badge-success">Activa</span>'
          : '<span class="badge badge-danger">Suspendida</span>' },
      {
        data: null, orderable:false, searchable:false,
        render: function(row){
          const id = row.id;
          const btnEdit = `<a href="<?= BASE_URL ?>/admin/ies/editar/${id}" class="btn btn-warning btn-sm m-1">Editar</a>`;
          const btnSusp = row.estado === 'activa'
            ? `<form action="<?= BASE_URL ?>/admin/ies/suspender/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Suspender este IES?');">
                 <input type="hidden" name="motivo" value="">
                 <button class="btn btn-secondary btn-sm m-1">Suspender</button>
               </form>`
            : `<form action="<?= BASE_URL ?>/admin/ies/reactivar/${id}" method="post" class="d-inline" onsubmit="return confirm('¿Reactivar este IES?');">
                 <button class="btn btn-success btn-sm m-1">Reactivar</button>
               </form>`;
          return btnEdit + btnSusp;
        }
      }
    ],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
  });

  $('#tabla-ies').on('xhr.dt', function (e, s, json, xhr) {
    if (xhr.status !== 200) {
      console.error('Ajax status:', xhr.status);
      console.error('Response:', xhr.responseText);
    }
  });
});
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
