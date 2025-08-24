<?php require __DIR__ . '/../../layouts/header.php'; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible">
        <?= $_SESSION['flash_success'] ?>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="card p-2">
    <h3 class="mb-2">Planes</h3>
    <div class="col-md-3 mb-2">
        <a href="<?= BASE_URL ?>/admin/plan/nuevo" class="btn btn-success mt-2">Nuevo Plan</a>
    </div>

    <div class="table-responsive">
        <table id="tabla-planes" class="table table-bordered table-hover table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Monto (PEN)</th>
                    <th>Usuarios</th>
                    <th>RENIEC</th>
                    <th>ESCALE</th>
                    <th>Facturación</th>
                    <th>Activo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody><!-- DataTables --></tbody>
        </table>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (!window.jQuery) {
            console.error('jQuery no cargado');
            return;
        }
        var $ = window.jQuery;

        $.fn.dataTable.ext.errMode = 'none';

        const tabla = $('#tabla-planes').DataTable({
            processing: true,
            serverSide: true,
            searching: false,
            ajax: {
                url: '<?= BASE_URL ?>/admin/plan/data',
                type: 'GET'
            },
            columns: [{
                    data: null,
                    render: (d, t, r, m) => m.row + 1 + m.settings._iDisplayStart
                },
                {
                    data: 'nombre'
                },
                {
                    data: 'monto',
                    render: d => parseFloat(d).toFixed(2)
                },
                {
                    data: 'limite_usuarios'
                },
                {
                    data: 'limite_reniec'
                },
                {
                    data: 'limite_escale'
                },
                {
                    data: 'limite_facturacion'
                },
                {
                    data: 'activo',
                    render: d => d == 1 ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-secondary">No</span>'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(row) {
                        const id = row.id;
                        const btnEdit = `<a href="<?= BASE_URL ?>/admin/plan/editar/${id}" class="btn btn-warning btn-sm m-1">Editar</a>`;
                        const btnAct = row.activo == 1 ?
                            `<form action="<?= BASE_URL ?>/admin/plan/desactivar/${id}" method="post" class="d-inline"><button class="btn btn-secondary btn-sm m-1" onclick="return confirm('¿Desactivar plan?')">Desactivar</button></form>` :
                            `<form action="<?= BASE_URL ?>/admin/plan/activar/${id}" method="post" class="d-inline"><button class="btn btn-success btn-sm m-1" onclick="return confirm('¿Activar plan?')">Activar</button></form>`;
                        const btnDel = `<form action="<?= BASE_URL ?>/admin/plan/eliminar/${id}" method="post" class="d-inline"><button class="btn btn-danger btn-sm m-1" onclick="return confirm('¿Eliminar? Puede desactivarse si hay dependencias.')">Eliminar</button></form>`;
                        return btnEdit + btnAct + btnDel;
                    }
                }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            }
        });

        // Log por si falla el ajax
        $('#tabla-planes').on('xhr.dt', function(e, settings, json, xhr) {
            if (xhr.status !== 200) {
                console.error('Ajax status:', xhr.status);
                console.error('Response:', xhr.responseText);
            }
        });
    });
</script>


<?php require __DIR__ . '/../../layouts/footer.php'; ?>