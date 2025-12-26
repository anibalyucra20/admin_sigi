<?php require __DIR__ . '/../../layouts/header.php'; ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success alert-dismissible">
    <?= $_SESSION['flash_success'] ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card p-2">
  <h3 class="mb-2">Series CPE</h3>

  <div class="row">
    <div class="col-md-3 mb-2">
      <a href="<?= BASE_URL ?>/admin/cpeSeries/nuevo" class="btn btn-success mt-2">Nueva Serie</a>
    </div>
  </div>

  <!-- Filtros -->
  <div class="card p-2 mb-2">
    <div class="row">
      <div class="col-md-5 mb-2">
        <label class="form-label">IES</label>
        <select id="filter_ies" class="form-control">
          <option value="">-- Todos --</option>
          <?php foreach (($iesList ?? []) as $i): ?>
            <option value="<?= (int)$i['id'] ?>"><?= htmlspecialchars($i['nombre_ies']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3 mb-2">
        <label class="form-label">Tipo Doc</label>
        <select id="filter_tipo_doc" class="form-control">
          <option value="">-- Todos --</option>
          <option value="01">01 - Factura</option>
          <option value="03">03 - Boleta</option>
          <option value="07">07 - Nota crédito</option>
          <option value="08">08 - Nota débito</option>
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

    <div class="row">
      <div class="col-md-4 mb-2">
        <label class="form-label">Serie</label>
        <input type="text" id="filter_serie" class="form-control" maxlength="8" placeholder="Ej: F001">
      </div>
      <div class="col-md-8 mb-2 d-flex align-items-end justify-content-end">
        <button id="btnFiltrar" class="btn btn-primary btn-sm mr-1">Filtrar</button>
        <button id="btnLimpiar" class="btn btn-secondary btn-sm">Limpiar</button>
      </div>
    </div>
  </div>

  <div class="table-responsive">
    <table id="tabla-series" class="table table-bordered table-hover table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>IES</th>
          <th>Tipo Doc</th>
          <th>Serie</th>
          <th>Correlativo actual</th>
          <th>Activo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (!window.jQuery) {
      console.error('jQuery no cargado');
      return;
    }
    $.fn.dataTable.ext.errMode = 'none';

    const tabla = $('#tabla-series').DataTable({
      processing: true,
      serverSide: true,
      searching: false,
      ajax: {
        url: '<?= BASE_URL ?>/admin/cpeSeries/data',
        type: 'GET',
        data: function(d) {
          d.filter_ies = $('#filter_ies').val();
          d.filter_tipo_doc = $('#filter_tipo_doc').val();
          d.filter_activo = $('#filter_activo').val();
          d.filter_serie = $('#filter_serie').val();
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
          data: 'tipo_doc'
        },
        {
          data: 'serie'
        },
        {
          data: 'correlativo_actual'
        },
        {
          data: 'activo',
          render: d => (parseInt(d) === 1) ?
            '<span class="badge badge-success">Sí</span>' :
            '<span class="badge badge-secondary">No</span>'
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function(row) {
            const id = row.id;
            const btnEdit = `<a href="<?= BASE_URL ?>/admin/cpeSeries/editar/${id}" class="btn btn-warning btn-sm m-1">Editar</a>`;
            const btnDel = `
            <form action="<?= BASE_URL ?>/admin/cpeSeries/eliminar/${id}" method="post" class="d-inline"
              onsubmit="return confirm('¿Eliminar esta serie?');">
              <button class="btn btn-danger btn-sm m-1">Eliminar</button>
            </form>`;
            return btnEdit + btnDel;
          }
        }
      ],
      language: {
        url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
      }
    });

    $('#btnFiltrar').on('click', function() {
      tabla.ajax.reload();
    });
    $('#btnLimpiar').on('click', function() {
      $('#filter_ies').val('');
      $('#filter_tipo_doc').val('');
      $('#filter_activo').val('');
      $('#filter_serie').val('');
      tabla.ajax.reload();
    });
    $('#filter_ies').on('click', function() {
      tabla.ajax.reload();
    });
    $('#filter_tipo_doc').on('click', function() {
      tabla.ajax.reload();
    });
    $('#filter_activo').on('click', function() {
      tabla.ajax.reload();
    });
    $('#filter_serie').on('keyup', function() {
      tabla.ajax.reload();
    });

    $('#tabla-series').on('xhr.dt', function(e, s, json, xhr) {
      if (xhr.status !== 200) {
        console.error('Ajax status:', xhr.status);
        console.error('Response:', xhr.responseText);
      }
    });
  });
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>