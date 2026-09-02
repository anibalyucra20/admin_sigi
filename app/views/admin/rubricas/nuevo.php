<?php require __DIR__ . '/../../layouts/header.php'; ?>

<?php if (!empty($errores)): ?>
    <div class="alert alert-danger alert-dismissible">
        <ul class="mb-0">
            <?php foreach ($errores as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
<?php endif; ?>

<div class="card p-2">
    <h3 class="mb-3">Nueva Rúbrica Institucional</h3>

    <form action="<?= BASE_URL ?>/admin/rubricas/guardar" method="POST" id="frmRubrica">
        <!-- Input oculto donde inyectaremos el JSON formateado para Moodle -->
        <input type="hidden" name="contenido_json" id="input_contenido_json" value="">

        <div class="row mb-3">
            <div class="col-md-4">
                <label>Nombre <span class="text-danger">*</span></label>
                <input type="text" name="nombre" class="form-control" required placeholder="Ej: Rúbrica de Exposición">
            </div>
            <div class="col-md-4">
                <label>Técnica <span class="text-danger">*</span></label>
                <select name="tipo_tecnica" class="form-control" required>
                    <option value="">Seleccione...</option>
                    <option value="Observación">Observación</option>
                    <option value="Desempeño">Desempeño</option>
                    <option value="Producto">Producto</option>
                </select>
            </div>
            <div class="col-md-4">
                <label>Descripción</label>
                <input type="text" name="descripcion" class="form-control">
            </div>
        </div>

        <hr>
        <div class="alert alert-info small">
            <i class="fa fa-info-circle"></i> Construya la matriz de evaluación. Puede asignar puntos de forma independiente a cada celda de nivel.
        </div>
        <h5 class="mb-3">Constructor de Criterios</h5>

        <div class="table-responsive">
            <table class="table table-bordered table-sm text-center align-middle" id="tablaRubrica">
                <thead class="table-light">
                    <tr id="trCabeceraNiveles">
                        <th style="width: 20%;">Criterios</th>
                        <!-- Columnas dinámicas de niveles -->
                        <th style="width: 11%;" id="thAddNivel">
                            <button type="button" class="btn btn-sm btn-info w-100" id="btn-add-nivel">+ Nivel</button>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyCriterios">
                    <!-- Filas dinámicas de criterios -->
                </tbody>
            </table>
        </div>

        <div class="mb-4">
            <button type="button" class="btn btn-primary btn-sm" id="btn-add-criterio">+ Agregar Criterio</button>
            <button type="button" class="btn btn-info btn-sm ml-2" data-toggle="modal" data-target="#modalImportarJSON">
                <i class="fa fa-code"></i> Importar JSON
            </button>
            <!-- NUEVO BOTÓN -->
            <button type="button" class="btn btn-dark btn-sm ml-2" onclick="previsualizarJSON()">
                <i class="fa fa-eye"></i> Ver / Copiar JSON
            </button>
        </div>

        <!-- ========================================== -->
        <!-- NUEVO MODAL: Ver y Copiar JSON Generado    -->
        <!-- ========================================== -->
        <div class="modal fade" id="modalVerJSON" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Previsualización del JSON (Moodle Format)</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <!-- Textarea readonly para que no lo editen por error aquí -->
                            <textarea id="textarea_export_json" class="form-control text-monospace" rows="15" readonly></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-success" onclick="copiarJSON()">
                            <i class="fa fa-copy"></i> Copiar al Portapapeles
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Bootstrap 4 para Importar JSON -->
        <div class="modal fade" id="modalImportarJSON" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Importar JSON de Rúbrica (Formato Moodle)</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning small">
                            <strong>Atención:</strong> Al importar, se reemplazará la matriz actual. Asegúrate de que el JSON contenga los arreglos <code>criterios</code> y <code>niveles</code>.
                        </div>
                        <div class="form-group">
                            <textarea id="textarea_import_json" class="form-control text-monospace" rows="12" placeholder='{
  "criterios": [
    {
      "description": "Criterio 1",
      "niveles": [
        {"score": 0, "definition": "Malo"},
        {"score": 10, "definition": "Bueno"}
      ]
    }
  ]
}'></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" onclick="procesarImportacionJSON()">Cargar a la Matriz</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-right">
            <a href="<?= BASE_URL ?>/admin/rubricas" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success">Guardar Rúbrica</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let nivelCount = 0;

        const trCabecera = document.getElementById('trCabeceraNiveles');
        const thAddNivel = document.getElementById('thAddNivel');
        const tbody = document.getElementById('tbodyCriterios');

        // Inicializar con 1 Criterio y 3 Niveles
        agregarNivel(0);
        agregarNivel(10);
        agregarNivel(20);
        agregarCriterio();

        document.getElementById('btn-add-nivel').addEventListener('click', () => agregarNivel(0));
        document.getElementById('btn-add-criterio').addEventListener('click', () => agregarCriterio());

        function agregarNivel(puntajeDefecto = 0) {
            nivelCount++;
            const th = document.createElement('th');
            th.className = 'nivel-col';
            // Cabecera sirve como puntaje Referencia/Base
            th.innerHTML = `
                <div class="input-group input-group-sm mb-1">
                    <div class="input-group-prepend"><span class="input-group-text" title="Puntaje Base/Referencia">Ref. Pts</span></div>
                    <input type="number" class="form-control input-score" value="${puntajeDefecto}" min="0" step="any" required>
                </div>
                <button type="button" class="btn btn-xs btn-danger btn-sm" onclick="eliminarNivel(this)">X</button>
            `;
            trCabecera.insertBefore(th, thAddNivel);

            // Agregar celda a los criterios ya existentes
            document.querySelectorAll('#tbodyCriterios tr').forEach(tr => {
                const td = document.createElement('td');
                td.innerHTML = `
                    <div class="input-group input-group-sm mb-1">
                        <div class="input-group-prepend"><span class="input-group-text bg-light">Pts</span></div>
                        <input type="number" class="form-control input-cell-score text-center font-weight-bold text-primary" value="${puntajeDefecto}" min="0" step="any" required>
                    </div>
                    <textarea class="form-control input-def" rows="3" placeholder="Descripción de nivel" required></textarea>
                `;
                tr.appendChild(td);
            });
        }

        window.eliminarNivel = function(btn) {
            const th = btn.closest('th');
            const index = Array.from(th.parentNode.children).indexOf(th);
            th.remove();
            document.querySelectorAll('#tbodyCriterios tr').forEach(tr => {
                tr.children[index].remove();
            });
            nivelCount--;
        };

        function agregarCriterio(descripcion = '', nivelesData = []) {
            if (typeof descripcion !== 'string') descripcion = '';

            const tr = document.createElement('tr');
            tr.className = 'criterio-row';

            let tds = `
                <td>
                    <textarea class="form-control input-criterio-desc mb-1" rows="3" placeholder="Nombre/Desc. del Criterio" required>${descripcion}</textarea>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">Eliminar</button>
                </td>
            `;
            
            for (let i = 0; i < nivelCount; i++) {
                let defText = (nivelesData[i] && nivelesData[i].definition !== undefined) ? nivelesData[i].definition : '';
                let cellScore = (nivelesData[i] && nivelesData[i].score !== undefined) ? nivelesData[i].score : 0;
                
                // Si es un criterio nuevo, copiamos el puntaje de la cabecera correspondiente
                if (!nivelesData[i]) {
                    const headerInput = document.querySelectorAll('.input-score')[i];
                    if(headerInput) cellScore = headerInput.value;
                }

                tds += `<td>
                    <div class="input-group input-group-sm mb-1">
                        <div class="input-group-prepend"><span class="input-group-text bg-light">Pts</span></div>
                        <input type="number" class="form-control input-cell-score text-center font-weight-bold text-primary" value="${cellScore}" min="0" step="any" required>
                    </div>
                    <textarea class="form-control input-def" rows="3" placeholder="Descripción de nivel" required>${defText}</textarea>
                </td>`;
            }

            tr.innerHTML = tds;
            tbody.appendChild(tr);
        }

        // =========================================================
        // LÓGICA DE EXTRACCIÓN (NÚCLEO)
        // =========================================================

        // Extraída para ser usada tanto por el Submit como por el Modal de Previsualización
        function compilarJSONMatriz() {
            const payload = {
                criterios: []
            };

            let sortOrder = 1;
            document.querySelectorAll('.criterio-row').forEach(tr => {
                const descCriterio = tr.querySelector('.input-criterio-desc').value.trim();
                if (!descCriterio) return; // Ignorar filas vacías

                const criterio = {
                    sortorder: sortOrder++,
                    description: descCriterio,
                    niveles: []
                };

                const cellScores = tr.querySelectorAll('.input-cell-score');
                const textareasNiveles = tr.querySelectorAll('.input-def');
                
                textareasNiveles.forEach((textarea, idx) => {
                    const scoreVal = parseFloat(cellScores[idx].value) || 0;
                    criterio.niveles.push({
                        score: scoreVal,
                        definition: textarea.value.trim()
                    });
                });
                payload.criterios.push(criterio);
            });

            return payload;
        }

        // =========================================================
        // NUEVAS FUNCIONES PARA VER/COPIAR JSON
        // =========================================================

        window.previsualizarJSON = function() {
            const payload = compilarJSONMatriz();
            // Convertimos el JSON a string con indentación de 2 espacios para que sea legible (Pretty Print)
            document.getElementById('textarea_export_json').value = JSON.stringify(payload, null, 2);
            $('#modalVerJSON').modal('show');
        };

        window.copiarJSON = function() {
            const textarea = document.getElementById('textarea_export_json');
            textarea.select();
            textarea.setSelectionRange(0, 99999); // Para compatibilidad con móviles

            // Usamos la API del portapapeles moderna con fallback
            navigator.clipboard.writeText(textarea.value).then(() => {
                alert("JSON copiado al portapapeles.");
            }).catch(err => {
                document.execCommand('copy');
                alert("JSON copiado al portapapeles.");
            });
        };

        // =========================================================
        // FUNCIONES DE IMPORTACIÓN JSON 
        // =========================================================

        function limpiarMatrizDOM() {
            document.querySelectorAll('.nivel-col').forEach(el => el.remove());
            tbody.innerHTML = '';
            nivelCount = 0;
        }

        function construirMatriz(data) {
            if (data.criterios && data.criterios.length > 0) {
                const nivelesBase = data.criterios[0].niveles;
                nivelesBase.forEach(nivel => agregarNivel(nivel.score));
                data.criterios.forEach(criterio => {
                    // Pasamos todo el arreglo de niveles (con score y definition)
                    agregarCriterio(criterio.description, criterio.niveles);
                });
            } else {
                agregarNivel(0);
                agregarNivel(10);
                agregarNivel(20);
                agregarCriterio();
            }
        }

        window.procesarImportacionJSON = function() {
            const jsonText = document.getElementById('textarea_import_json').value.trim();
            if (!jsonText) {
                alert("Por favor, pegue un código JSON válido.");
                return;
            }

            try {
                const dataImportada = JSON.parse(jsonText);
                if (!dataImportada.criterios || !Array.isArray(dataImportada.criterios)) {
                    throw new Error("El JSON carece del nodo 'criterios' en formato array.");
                }
                limpiarMatrizDOM();
                construirMatriz(dataImportada);
                $('#modalImportarJSON').modal('hide');
                document.getElementById('textarea_import_json').value = '';
                alert("JSON importado a la matriz correctamente.");
            } catch (error) {
                alert("Error de validación JSON:\n" + error.message);
            }
        };

        // =========================================================
        // INTERCEPTOR DEL SUBMIT (Optimizada)
        // =========================================================

        document.getElementById('frmRubrica').addEventListener('submit', function(e) {
            // Reutilizamos la función abstracta
            const payload = compilarJSONMatriz();

            // Lo pasamos a string plano (sin espacios) para optimizar el almacenamiento en BD
            document.getElementById('input_contenido_json').value = JSON.stringify(payload);

            // Permite que el formulario continúe su envío POST natural
        });

    });
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>