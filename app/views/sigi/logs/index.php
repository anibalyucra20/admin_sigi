<?php require __DIR__ . '/../../layouts/header.php'; ?>
<?php if (\Core\Auth::esAdminSigi()): ?>
    <div class="card p-2">
        <h3>Auditoría del Sistema</h3>
        <div class="row mb-3">
            <div class="col-md-2">
                <label>Usuario:</label>
                <select id="filter-usuario" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['apellidos_nombres']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label>Acción:</label>
                <select id="filter-accion" class="form-control">
                    <option value="">Todas</option>
                    <?php foreach ($acciones as $a): ?>
                        <option value="<?= $a ?>"><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label>Tabla:</label>
                <select id="filter-tabla" class="form-control">
                    <option value="">Todas</option>
                    <?php foreach ($tablas as $t): ?>
                        <option value="<?= $t ?>"><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label>Fecha Inicio:</label>
                <input type="date" id="filter-fecha-ini" class="form-control">
            </div>
            <div class="col-md-2">
                <label>Fecha Fin:</label>
                <input type="date" id="filter-fecha-fin" class="form-control">
            </div>

        </div>

        <div class="table-responsive">
            <table id="tabla-logs" class="table table-bordered table-hover table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Tabla</th>
                        <th>ID Registro</th>
                        <th>Descripción</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabla = $('#tabla-logs').DataTable({
                processing: true,
                serverSide: true,
                searching: false,
                ajax: {
                    url: '<?= BASE_URL ?>/sigi/logs/data',
                    type: 'GET',
                    data: function(d) {
                        d.filter_usuario = $('#filter-usuario').val();
                        d.filter_accion = $('#filter-accion').val();
                        d.filter_tabla = $('#filter-tabla').val();
                        d.filter_fecha_ini = $('#filter-fecha-ini').val();
                        d.filter_fecha_fin = $('#filter-fecha-fin').val();
                    }
                },
                columns: [{
                        data: 'fecha'
                    },
                    {
                        data: 'usuario'
                    },
                    {
                        data: 'accion'
                    },
                    {
                        data: 'tabla_afectada'
                    },
                    {
                        data: 'id_registro'
                    },
                    {
                        data: 'descripcion'
                    },
                    {
                        data: 'ip_usuario'
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
                }
            });

            $('#filter-usuario, #filter-accion, #filter-tabla, #filter-fecha-ini, #filter-fecha-fin').on('change', function() {
                tabla.ajax.reload();
            });


        });
    </script>
<?php else: ?>
    <!-- Para director o coordinador en SIGI -->
    <p>El Modulo SIGI solo es para rol de Administrador</p>
<?php endif; ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>