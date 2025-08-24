<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">IES *</label>
        <select name="id_ies" class="form-control" required>
            <option value="">Seleccione...</option>
            <?php foreach ($ies as $x): ?>
                <option value="<?= (int)$x['id'] ?>"
                    <?= (!empty($sub['id_ies']) && $sub['id_ies'] == $x['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($x['nombre_ies']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Plan *</label>
        <select name="id_plan" class="form-control" required>
            <option value="">Seleccione...</option>
            <?php foreach ($planes as $pl): ?>
                <option value="<?= (int)$pl['id'] ?>"
                    <?= (!empty($sub['id_plan']) && $sub['id_plan'] == $pl['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($pl['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Ciclo *</label>
        <select name="ciclo" class="form-control" required>
            <option value="mensual" <?= (($sub['ciclo'] ?? 'mensual') === 'mensual') ? 'selected' : '' ?>>Mensual</option>
            <option value="anual" <?= (($sub['ciclo'] ?? '') === 'anual') ? 'selected' : '' ?>>Anual</option>
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Inicia *</label>
        <input type="date" name="inicia" class="form-control" required
            value="<?= htmlspecialchars($sub['inicia'] ?? '') ?>">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Vence</label>
        <input type="date" name="vence" class="form-control"
            value="<?= htmlspecialchars($sub['vence'] ?? '') ?>">
        <small class="text-muted">Si lo dejas vacío, se calculará automáticamente según el ciclo.</small>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Estado *</label>
        <select name="estado" class="form-control" required>
            <?php $est = $sub['estado'] ?? 'activa'; ?>
            <option value="trial" <?= $est === 'trial' ? 'selected' : '' ?>>Trial</option>
            <option value="activa" <?= $est === 'activa' ? 'selected' : '' ?>>Activa</option>
            <option value="suspendida" <?= $est === 'suspendida' ? 'selected' : '' ?>>Suspendida</option>
            <option value="cancelada" <?= $est === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
        </select>
    </div>
    <div class="row col-12">
        <div class="col-md-12 mb-3">
            <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" id="chkApi" name="generar_api" value="1" checked>
                <label class="form-check-label" for="chkApi">
                    Generar API Key por defecto al guardar
                </label>
                <small class="text-muted d-block">La clave se mostrará <b>una sola vez</b>. Cópiala y guárdala.</small>
            </div>
        </div>
    </div>

</div>