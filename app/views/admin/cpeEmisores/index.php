<?php require __DIR__ . '/../../layouts/header.php'; ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success alert-dismissible">
    <?= $_SESSION['flash_success'] ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card p-2">
  <h3 class="mb-2">Emisores CPE</h3>

  <div class="row">
    <div class="col-md-3 mb-2">
      <a href="<?= BASE_URL ?>/admin/cpeEmisores/nuevo" class="btn btn-success mt-2">Nuevo Emisor</a>
    </div>
  </div>
  <!-- Filtros -->
  <div class="card p-2 mb-2">
    <div class="row">
      <div class="col-md-4 mb-2">
        <label class="form-label">RUC</label>
        <input type="text" id="filter_ruc" class="form-control" maxlength="11" placeholder="Buscar por RUC">
      </div>
      <div class="col-md-4 mb-2">
        <label class="form-label">Razón social</label>
        <input type="text" id="filter_razon" class="form-control" maxlength="255" placeholder="Buscar por razón social">
      </div>
    </div>

    <div class="text-end">
      <button id="btnFiltrar" class="btn btn-primary btn-sm">Filtrar</button>
      <button id="btnLimpiar" class="btn btn-secondary btn-sm">Limpiar</button>
    </div>
  </div>

  <div class="table-responsive">
    <table id="tabla-emisores" class="table table-bordered table-hover table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>IES</th>
          <th>RUC</th>
          <th>Razón social</th>
          <th>Comercial</th>
          <th>Ubigeo</th>
          <th>Email</th>
          <th>Teléfono</th>
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

  const tabla = $('#tabla-emisores').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    ajax: {
      url: '<?= BASE_URL ?>/admin/cpeEmisores/data',
      type: 'GET',
      data: function(d){
        d.filter_ruc   = $('#filter_ruc').val();
        d.filter_razon = $('#filter_razon').val();
      }
    },
    columns: [
      { data: null, render: (d,t,r,m) => m.row + 1 + m.settings._iDisplayStart },
      { data: 'nombre_ies' },
      { data: 'ruc' },
      { data: 'razon_social' },
      { data: 'nombre_comercial', defaultContent: '' },
      { data: 'ubigeo', defaultContent: '' },
      { data: 'email', defaultContent: '' },
      { data: 'telefono', defaultContent: '' },
      {
        data: null, orderable:false, searchable:false,
        render: function(row){
          const id = row.id;
          const btnEdit = `<a href="<?= BASE_URL ?>/admin/cpeEmisores/editar/${id}" class="btn btn-warning btn-sm m-1">Editar</a>`;
          const btnDel = `
            <form action="<?= BASE_URL ?>/admin/cpeEmisores/eliminar/${id}" method="post" class="d-inline"
              onsubmit="return confirm('¿Eliminar este emisor?');">
              <button class="btn btn-danger btn-sm m-1">Eliminar</button>
            </form>`;
          return btnEdit + btnDel;
        }
      }
    ],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
  });

  $('#btnFiltrar').on('click', function(){ tabla.ajax.reload(); });
  $('#btnLimpiar').on('click', function(){
    $('#filter_ruc').val('');
    $('#filter_razon').val('');
    tabla.ajax.reload();
  });
  $('#filter_ruc').on('keyup', function(){ tabla.ajax.reload(); });
  $('#filter_razon').on('keyup', function(){ tabla.ajax.reload(); });

  $('#tabla-emisores').on('xhr.dt', function (e, s, json, xhr) {
    if (xhr.status !== 200) {
      console.error('Ajax status:', xhr.status);
      console.error('Response:', xhr.responseText);
    }
  });
});
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
