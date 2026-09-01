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
    <h3 class="mb-3"><?= $pageTitle ?></h3>

    <form action="<?= BASE_URL ?>/admin/rubricas/guardar" method="POST" id="frmRubrica">
        <!-- El ID es crucial para que el Modelo haga UPDATE y no INSERT -->
        <input type="hidden" name="id" value="<?= htmlspecialchars($rubrica['id']) ?>">
        <input type="hidden" name="contenido_json" id="input_contenido_json" value="">

        <div class="row mb-3">
            <div class="col-md-4">
                <label>Nombre <span class="text-danger">*</span></label>
                <input type="text" name="nombre" class="form-control" required
                    value="<?= htmlspecialchars($rubrica['nombre']) ?>">
            </div>
            <div class="col-md-4">
                <label>Técnica <span class="text-danger">*</span></label>
                <select name="tipo_tecnica" class="form-control" required>
                    <option value="Observación" <?= $rubrica['tipo_tecnica'] === 'Observación' ? 'selected' : '' ?>>Observación</option>
                    <option value="Desempeño" <?= $rubrica['tipo_tecnica'] === 'Desempeño' ? 'selected' : '' ?>>Desempeño</option>
                    <option value="Producto" <?= $rubrica['tipo_tecnica'] === 'Producto' ? 'selected' : '' ?>>Producto</option>
                </select>
            </div>
            <div class="col-md-4">
                <label>Descripción</label>
                <input type="text" name="descripcion" class="form-control"
                    value="<?= htmlspecialchars($rubrica['descripcion'] ?? '') ?>">
            </div>
        </div>

        <hr>
        <h5 class="mb-3">Constructor de Criterios</h5>

        <div class="table-responsive">
            <table class="table table-bordered table-sm text-center align-middle" id="tablaRubrica">
                <thead class="table-light">
                    <tr id="trCabeceraNiveles">
                        <th style="width: 25%;">Criterios</th>
                        <th style="width: 10%;" id="thAddNivel">
                            <button type="button" class="btn btn-sm btn-info w-100" id="btn-add-nivel">+ Nivel</button>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyCriterios">
                </tbody>
            </table>
        </div>

        <!-- Reemplaza tu div de botones por este -->
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
                        <h5 class="modal-title">Previsualización del JSON</h5>
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
            <button type="submit" class="btn btn-warning"><i class="fa fa-edit"></i> Actualizar Rúbrica</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables de estado y referencias al DOM
        let nivelCount = 0;
        const trCabecera = document.getElementById('trCabeceraNiveles');
        const thAddNivel = document.getElementById('thAddNivel');
        const tbody = document.getElementById('tbodyCriterios');

        // Inyectamos el JSON almacenado en la BD hacia una constante JS
        const rubricaData = <?= $rubrica['contenido_json'] ?: '{"criterios":[]}' ?>;

        /**
         * Constructor Central: Reconstruye la matriz visual a partir de un objeto JSON.
         * @param {Object} data - Objeto JSON con la estructura { criterios: [...] }
         */
        function construirMatriz(data) {
            if (data.criterios && data.criterios.length > 0) {
                // Extraemos los niveles del primer criterio para armar las columnas
                const nivelesBase = data.criterios[0].niveles;

                // Crear las columnas (Cabecera de Puntajes)
                nivelesBase.forEach(nivel => {
                    agregarNivelDOM(nivel.score);
                });

                // Crear las filas (Criterios y sus definiciones)
                data.criterios.forEach(criterio => {
                    const defs = criterio.niveles.map(n => n.definition);
                    agregarCriterioDOM(criterio.description, defs);
                });
            } else {
                // Si el JSON está vacío, inicializar con estructura por defecto
                agregarNivelDOM(0);
                agregarNivelDOM(10);
                agregarNivelDOM(20);
                agregarCriterioDOM('', ['', '', '']);
            }
        }

        /**
         * Limpia completamente el DOM de la matriz para prepararlo para una nueva inyección.
         */
        function limpiarMatrizDOM() {
            document.querySelectorAll('.nivel-col').forEach(el => el.remove());
            tbody.innerHTML = '';
            nivelCount = 0; // Reiniciar estado
        }

        /**
         * LÓGICA DE EXTRACCIÓN (NÚCLEO)
         * Lee el DOM actual y construye el objeto JSON.
         * Usado tanto por el Submit como por el Modal de Previsualización.
         */
        function compilarJSONMatriz() {
            const payload = { criterios: [] };
            const puntajes = [];

            document.querySelectorAll('.input-score').forEach(input => puntajes.push(parseFloat(input.value) || 0));

            let sortOrder = 1;
            document.querySelectorAll('.criterio-row').forEach(tr => {
                const descCriterio = tr.querySelector('.input-criterio-desc').value.trim();
                if (!descCriterio) return;

                const criterio = {
                    sortorder: sortOrder++,
                    description: descCriterio,
                    niveles: []
                };
                
                const textareasNiveles = tr.querySelectorAll('.input-def');
                textareasNiveles.forEach((textarea, idx) => {
                    criterio.niveles.push({
                        score: puntajes[idx],
                        definition: textarea.value.trim()
                    });
                });
                
                payload.criterios.push(criterio);
            });
            
            return payload;
        }

        /**
         * Expuesto a window para ser llamado desde el botón del modal HTML.
         * Procesa, valida e inyecta el JSON pegado por el usuario.
         */
        window.procesarImportacionJSON = function() {
            const jsonText = document.getElementById('textarea_import_json').value.trim();

            if (!jsonText) {
                alert("Por favor, pegue un código JSON válido.");
                return;
            }

            try {
                // Parseo estricto
                const dataImportada = JSON.parse(jsonText);

                // Validación proactiva de la estructura requerida por Moodle
                if (!dataImportada.criterios || !Array.isArray(dataImportada.criterios)) {
                    throw new Error("El JSON carece del nodo principal 'criterios' en formato array.");
                }
                if (dataImportada.criterios.length > 0 && (!dataImportada.criterios[0].niveles || !Array.isArray(dataImportada.criterios[0].niveles))) {
                    throw new Error("Los criterios no contienen el array interno de 'niveles'.");
                }

                // Ejecución del flujo de reemplazo
                limpiarMatrizDOM();
                construirMatriz(dataImportada);

                // Cierre de UI y feedback
                $('#modalImportarJSON').modal('hide');
                document.getElementById('textarea_import_json').value = '';
                alert("JSON importado correctamente. Revise la matriz antes de guardar los cambios.");

            } catch (error) {
                alert("Error de validación JSON:\n" + error.message);
                console.error("Detalle del error:", error);
            }
        };

        /**
         * Expuesto a window para previsualizar el JSON antes de guardar.
         */
        window.previsualizarJSON = function() {
            const payload = compilarJSONMatriz();
            // JSON.stringify con indentación de 2 espacios (Pretty Print)
            document.getElementById('textarea_export_json').value = JSON.stringify(payload, null, 2);
            $('#modalVerJSON').modal('show');
        };

        /**
         * Expuesto a window para copiar el JSON al portapapeles.
         */
        window.copiarJSON = function() {
            const textarea = document.getElementById('textarea_export_json');
            textarea.select();
            textarea.setSelectionRange(0, 99999); // Compatibilidad móvil
            
            navigator.clipboard.writeText(textarea.value).then(() => {
                alert("JSON copiado al portapapeles.");
            }).catch(err => {
                document.execCommand('copy');
                alert("JSON copiado al portapapeles.");
            });
        };

        // Lógica para agregar una columna (Nivel) al DOM
        function agregarNivelDOM(puntaje = 0) {
            nivelCount++;
            const th = document.createElement('th');
            th.className = 'nivel-col';
            th.innerHTML = `
                <div class="input-group input-group-sm mb-1">
                    <div class="input-group-prepend"><span class="input-group-text">Pts</span></div>
                    <input type="number" class="form-control input-score" value="${puntaje}" required>
                </div>
                <button type="button" class="btn btn-xs btn-danger btn-sm" onclick="eliminarNivel(this)">X</button>
            `;
            trCabecera.insertBefore(th, thAddNivel);
        }

        // Lógica para agregar una fila (Criterio) al DOM
        function agregarCriterioDOM(descripcion = '', definicionesNiveles = []) {
            const tr = document.createElement('tr');
            tr.className = 'criterio-row';

            let tds = `
                <td>
                    <textarea class="form-control input-criterio-desc mb-1" rows="5" placeholder="Definición del Criterio" required>${descripcion}</textarea>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">Eliminar</button>
                </td>
            `;

            // Rellenamos las celdas con la información existente o en blanco
            for (let i = 0; i < nivelCount; i++) {
                let defText = definicionesNiveles[i] !== undefined ? definicionesNiveles[i] : '';
                tds += `<td><textarea class="form-control input-def" rows="5" placeholder="Descripción" required>${defText}</textarea></td>`;
            }

            tr.innerHTML = tds;
            tbody.appendChild(tr);
        }

        // Eventos para botones manuales
        document.getElementById('btn-add-nivel').addEventListener('click', () => {
            agregarNivelDOM(0);
            document.querySelectorAll('#tbodyCriterios tr').forEach(tr => {
                const td = document.createElement('td');
                td.innerHTML = `<textarea class="form-control input-def" rows="5" placeholder="Descripción" required></textarea>`;
                tr.appendChild(td);
            });
        });

        document.getElementById('btn-add-criterio').addEventListener('click', () => agregarCriterioDOM());

        window.eliminarNivel = function(btn) {
            const th = btn.closest('th');
            const index = Array.from(th.parentNode.children).indexOf(th);
            th.remove();
            document.querySelectorAll('#tbodyCriterios tr').forEach(tr => {
                tr.children[index].remove();
            });
            nivelCount--;
        };

        // Compilar JSON en el Submit (Refactorizado para usar la función central)
        document.getElementById('frmRubrica').addEventListener('submit', function(e) {
            const payload = compilarJSONMatriz();
            // Se envía minificado (sin espacios) para optimizar BD
            document.getElementById('input_contenido_json').value = JSON.stringify(payload);
            // Permite que el formulario continúe su envío POST natural
        });

        // Disparar la reconstrucción inicial al cargar la página
        construirMatriz(rubricaData);

    });
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>