<?php require __DIR__ . '/../../layouts/header.php'; ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success alert-dismissible">
    <?= $_SESSION['flash_success'] ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card p-2">
  <h3 class="mb-2">Credenciales SUNAT</h3>

  <div class="row">
    <div class="col-md-3 mb-2">
      <a href="<?= BASE_URL ?>/admin/cpeCredenciales/nuevo" class="btn btn-success mt-2">Nueva Credencial</a>
    </div>
  </div>

  <!-- Filtros -->
  <div class="card p-2 mb-2">
    <div class="row">
      <div class="col-md-5 mb-2">
        <label class="form-label">IES</label>
        <select id="filter_ies" class="form-control">
          <option value="">-- Todos --</option>
          <?php foreach(($iesList ?? []) as $i): ?>
            <option value="<?= (int)$i['id'] ?>"><?= htmlspecialchars($i['nombre_ies']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3 mb-2">
        <label class="form-label">Modo</label>
        <select id="filter_modo" class="form-control">
          <option value="">-- Todos --</option>
          <option value="beta">beta</option>
          <option value="prod">prod</option>
        </select>
      </div>

      <div class="col-md-4 mb-2">
        <label class="form-label">Activo</label>
        <select id="filter_activo" class="form-control">
          <option value="">-- Todos --</option>
          <option value="1">Sí</option>
          <option value="0">No</option>
        </select>
      </div>
    </div>

    <div class="text-end">
      <button id="btnFiltrar" class="btn btn-primary btn-sm">Filtrar</button>
      <button id="btnLimpiar" class="btn btn-secondary btn-sm">Limpiar</button>
    </div>
  </div>

  <div class="table-responsive">
    <table id="tabla-cred" class="table table-bordered table-hover table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>IES</th>
          <th>Modo</th>
          <th>SOL User</th>
          <th>SOL Pass</th>
          <th>PFX</th>
          <th>Activo</th>
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

  const tabla = $('#tabla-cred').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    ajax: {
      url: '<?= BASE_URL ?>/admin/cpeCredenciales/data',
      type: 'GET',
      data: function(d){
        d.filter_ies    = $('#filter_ies').val();
        d.filter_modo   = $('#filter_modo').val();
        d.filter_activo = $('#filter_activo').val();
      }
    },
    columns: [
      { data: null, render: (d,t,r,m) => m.row + 1 + m.settings._iDisplayStart },
      { data: 'nombre_ies' },
      { data: 'modo', render: d => d === 'prod'
          ? '<span class="badge badge-danger">prod</span>'
          : '<span class="badge badge-info">beta</span>' },
      { data: 'sol_user' },
      { data: 'has_sol_pass', render: d => (parseInt(d) === 1)
          ? '<span class="badge badge-success">OK</span>'
          : '<span class="badge badge-secondary">Vacío</span>' },
      { data: 'has_pfx', render: d => (parseInt(d) === 1)
          ? '<span class="badge badge-success">OK</span>'
          : '<span class="badge badge-secondary">Vacío</span>' },
      { data: 'activo', render: d => (parseInt(d) === 1)
          ? '<span class="badge badge-success">Sí</span>'
          : '<span class="badge badge-secondary">No</span>' },
      {
        data: null, orderable:false, searchable:false,
        render: function(row){
          const id = row.id;
          return `<a href="<?= BASE_URL ?>/admin/cpeCredenciales/editar/${id}" class="btn btn-warning btn-sm m-1">Editar</a>`;
        }
      }
    ],
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
  });

  $('#btnFiltrar').on('click', function(){ tabla.ajax.reload(); });
  $('#btnLimpiar').on('click', function(){
    $('#filter_ies').val('');
    $('#filter_modo').val('');
    $('#filter_activo').val('');
    tabla.ajax.reload();
  });
  $('#filter_ies').on('change', function(){ tabla.ajax.reload(); });
  $('#filter_modo').on('change', function(){ tabla.ajax.reload(); });
  $('#filter_activo').on('change', function(){ tabla.ajax.reload(); });

  $('#tabla-cred').on('xhr.dt', function (e, s, json, xhr) {
    if (xhr.status !== 200) {
      console.error('Ajax status:', xhr.status);
      console.error('Response:', xhr.responseText);
    }
  });
});
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
