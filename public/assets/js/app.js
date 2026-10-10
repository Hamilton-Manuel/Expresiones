'use strict';

(() => {
    const $ = (id) => document.getElementById(id);
    const patterns = JSON.parse($('pattern-data').dataset.patterns);
    const names = { telefono: 'Teléfono', dpi: 'DPI', correo: 'Correo' };
    const types = Object.keys(patterns);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const source = $('source-text');
    const example = source.value;
    let analysis = null;
    let selected = {};
    let textVersion = 0;
    let recordsPage = 1;
    let recordsPages = 1;
    let recordsVersion = 0;

    function node(tag, className = '', text = '') {
        const element = document.createElement(tag);
        element.className = className;
        element.textContent = text;
        return element;
    }

    function notice(target, text, kind = 'info') {
        target.textContent = text;
        target.className = `alert alert-${kind}${text ? '' : ' d-none'}`;
    }

    async function request(action, data = null, query = {}) {
        const url = new URL('api.php', location.href);
        url.searchParams.set('action', action);
        Object.entries(query).forEach(([key, value]) => url.searchParams.set(key, value));
        let response;
        try {
            response = await fetch(url, data === null ? { headers: { Accept: 'application/json' } } : {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify(data),
            });
        } catch (cause) {
            throw new Error('No se pudo contactar con PHP. Comprueba que el servidor local esté ejecutándose.');
        }
        let result;
        try { result = await response.json(); }
        catch (cause) { throw new Error('El servidor no devolvió JSON válido. Revisa su registro de errores.'); }
        if (!response.ok) {
            const error = new Error(result.error || 'No se pudo completar la operación.');
            error.data = result;
            throw error;
        }
        return result;
    }

    async function busy(button, action) {
        const wasDisabled = button.disabled;
        const label = button.textContent;
        button.disabled = true;
        button.textContent = 'Procesando…';
        try { await action(); }
        finally { button.disabled = wasDisabled; button.textContent = label; }
    }

    function openSection(section) {
        document.querySelectorAll('.workspace-section').forEach((element) => element.classList.toggle('d-none', element.id !== `section-${section}`));
        document.querySelectorAll('.workspace-link').forEach((button) => {
            const active = button.dataset.section === section;
            button.classList.toggle('active', active);
            active ? button.setAttribute('aria-current', 'page') : button.removeAttribute('aria-current');
        });
        notice($('global-message'), '');
        if (section === 'records') loadRecords();
    }
    document.querySelectorAll('.workspace-link').forEach((button) => button.addEventListener('click', () => openSection(button.dataset.section)));

    function resetSelection() {
        selected = {};
        document.querySelectorAll('.result-select input').forEach((input) => { input.checked = false; });
        document.querySelectorAll('.result-card').forEach((card) => card.classList.remove('is-selected'));
        updateSelection();
    }

    function updateSelection() {
        types.forEach((type) => { $(`selected-${type}`).textContent = selected[type]?.normalized || 'Sin seleccionar'; });
        $('apply-selection').disabled = !types.every((type) => selected[type]);
    }

    function invalidateAnalysis() {
        textVersion++;
        analysis = null;
        resetSelection();
        $('analysis-output').classList.add('d-none');
        $('results-list').replaceChildren();
        $('highlighted-text').replaceChildren();
        $('anonymized-text').textContent = '';
        ['telefono', 'dpi', 'correo', 'valid', 'invalid'].forEach((key) => { $(`count-${key}`).textContent = '0'; });
        const bytes = new TextEncoder().encode(source.value).length;
        $('byte-count').textContent = `${bytes.toLocaleString('es-GT')} / 30 000 bytes`;
        $('byte-count').classList.toggle('invalid-text', bytes > 30000);
    }
    source.addEventListener('input', invalidateAnalysis);
    $('load-example').addEventListener('click', () => { source.value = example; invalidateAnalysis(); notice($('global-message'), 'Ejemplo cargado. Son datos ficticios para experimentar.'); });
    $('clear-text').addEventListener('click', () => { source.value = ''; invalidateAnalysis(); source.focus(); });
    $('clear-selection').addEventListener('click', resetSelection);

    async function copyText(text, button) {
        try {
            if (!navigator.clipboard) throw new Error('clipboard unavailable');
            await navigator.clipboard.writeText(text);
            const label = button.textContent;
            button.textContent = '¡Copiado!';
            setTimeout(() => { button.textContent = label; }, 1600);
        } catch (error) {
            notice($('global-message'), 'No se pudo acceder al portapapeles. Selecciona el resultado y cópialo manualmente.', 'warning');
        }
    }

    function renderAnalysis(result) {
        analysis = result;
        resetSelection();
        Object.entries(result.counts).forEach(([key, value]) => { $(`count-${key}`).textContent = value; });
        $('result-count').textContent = `${result.results.length} coincidencias`;
        const container = $('results-list');
        container.replaceChildren();
        if (!result.results.length) container.append(node('p', 'panel', 'No se encontraron datos reconocibles. Puedes añadir etiquetas Teléfono:, DPI: o Correo: para comprobar valores incorrectos.'));
        result.results.forEach((item) => {
            const card = node('article', 'result-card');
            card.dataset.type = item.type;
            card.dataset.result = item.id;
            const content = node('div');
            const meta = node('div', 'result-meta');
            meta.append(node('span', `type-tag type-${item.type}`, names[item.type]), node('span', item.valid ? 'valid-status' : 'invalid-status', item.valid ? '✓ Válido' : '× Inválido'));
            content.append(meta, node('div', 'result-value', item.original || '(vacío)'));
            if (item.valid) {
                const normalized = node('div', 'result-normalized', 'Normalizado → ');
                normalized.append(node('code', '', item.normalized));
                content.append(normalized);
            } else content.append(node('div', 'result-error', item.message));
            card.append(content);
            if (item.valid) {
                const actions = node('div', 'result-actions');
                const label = node('label', 'result-select');
                const input = node('input');
                input.type = 'radio';
                input.name = `select-${item.type}`;
                input.value = item.id;
                input.setAttribute('aria-label', `Seleccionar ${names[item.type]} ${item.original}`);
                input.addEventListener('change', () => {
                    selected[item.type] = item;
                    container.querySelectorAll('.result-card').forEach((other) => {
                        if (other.dataset.type === item.type) other.classList.toggle('is-selected', other.dataset.result === String(item.id));
                    });
                    updateSelection();
                });
                label.append(input, node('span', '', 'Seleccionar'));
                const copy = node('button', 'btn btn-sm btn-quiet', 'Copiar valor');
                copy.type = 'button';
                copy.addEventListener('click', () => copyText(item.normalized, copy));
                actions.append(label, copy);
                card.append(actions);
            }
            container.append(card);
        });
        // Único HTML dinámico: generado en PHP con texto escapado y marcado fijo.
        $('highlighted-text').innerHTML = result.highlighted;
        $('anonymized-text').textContent = result.anonymized;
        $('analysis-output').classList.remove('d-none');
    }

    async function analyze(button, action) {
        if (!source.value.trim()) { notice($('global-message'), 'Ingresa un texto para comenzar.', 'warning'); source.focus(); return; }
        if (new TextEncoder().encode(source.value).length > 30000) { notice($('global-message'), 'El texto supera el límite de 30 000 bytes.', 'warning'); return; }
        const version = textVersion;
        await busy(button, async () => {
            try {
                const result = await request(action, { text: source.value });
                if (version !== textVersion) return;
                renderAnalysis(result);
                notice($('global-message'), `${result.counts.valid} coincidencias válidas y ${result.counts.invalid} inválidas. Selecciona explícitamente los datos que quieres registrar.`, 'success');
                if (action === 'anonymize') $('anonymized-text').scrollIntoView({ block: 'center' });
            } catch (error) { if (version === textVersion) notice($('global-message'), error.message, 'danger'); }
        });
    }
    $('analyze-button').addEventListener('click', () => analyze($('analyze-button'), 'analyze'));
    $('anonymize-button').addEventListener('click', () => analyze($('anonymize-button'), 'anonymize'));
    $('copy-anonymized').addEventListener('click', () => { if (analysis) copyText(analysis.anonymized, $('copy-anonymized')); });

    function fieldValidation(type, value) {
        const match = new RegExp(`^(?:${patterns[type]})$`).exec(value);
        const limits = { telefono: 20, dpi: 20, correo: 254 };
        const format = !!match && match[0].length === value.length && value.length <= limits[type];
        const length = type !== 'correo' || value.split('@')[0].length <= 64;
        const valid = format && length;
        let normalized = null;
        if (valid) {
            if (type === 'telefono') normalized = value.replace(/^\+502/, '').replace(/[ -]/g, '');
            else if (type === 'dpi') normalized = value.replace(/ /g, '');
            else { const [user, domain] = value.split('@'); normalized = `${user}@${domain.toLowerCase()}`; }
        }
        const errors = {
            telefono: 'Usa 8 dígitos, con un espacio o guion opcional entre grupos de 4 y prefijo +502 opcional.',
            dpi: 'Usa 13 dígitos o el formato 1234 56789 0101. Solo se comprueba el formato.',
            correo: 'Usa usuario@dominio.ext, sin espacios ni puntos consecutivos en el usuario.',
        };
        return { valid, original: value, normalized, message: valid ? 'Formato válido.' : value ? errors[type] : 'El campo es obligatorio.', checks: [
            { label: 'Campo presente', ok: value !== '' },
            { label: 'Coincide con la expresión regular', ok: format },
            { label: type === 'correo' ? 'Longitud permitida (usuario ≤ 64, total ≤ 254)' : 'Formato completo, sin fragmentos', ok: type === 'correo' ? value.length <= 254 && length : format },
        ] };
    }

    function showField(type, result) {
        const input = $(type);
        input.classList.toggle('is-valid', result.valid);
        input.classList.toggle('is-invalid', !result.valid);
        input.setAttribute('aria-invalid', String(!result.valid));
        const target = $(`feedback-${type}`);
        target.replaceChildren(node('span', result.valid ? 'check-pass' : 'check-fail', result.message));
        const checklist = node('div', 'validation-checks');
        result.checks.forEach((check) => checklist.append(node('span', check.ok ? 'check-pass' : 'check-fail', `${check.ok ? '✓' : '×'} ${check.label}`)));
        target.append(checklist);
    }

    function checkName() {
        const value = $('nombre').value.trim();
        const valid = value.length > 0 && Array.from(value).length <= 120 && !/[\x00-\x1f\x7f]/.test(value);
        $('nombre').classList.toggle('is-valid', valid);
        $('nombre').classList.toggle('is-invalid', !valid);
        $('nombre').setAttribute('aria-invalid', String(!valid));
        $(`feedback-nombre`).textContent = valid ? '✓ Nombre presente.' : 'Ingresa un nombre de 1 a 120 caracteres, sin caracteres de control.';
        return valid;
    }

    function updatePreview() {
        const container = $('normalization-preview');
        container.replaceChildren();
        types.forEach((type) => {
            const result = fieldValidation(type, $(type).value);
            const entry = node('div', 'normalization-entry');
            entry.append(node('strong', '', names[type]), node('small', '', `Original: ${result.original || '—'}`), node('code', '', result.normalized || 'Pendiente de un formato válido'));
            if (result.valid) {
                const copy = node('button', 'btn btn-sm btn-quiet mt-2', 'Copiar normalizado');
                copy.type = 'button';
                copy.addEventListener('click', () => copyText(result.normalized, copy));
                entry.append(copy);
            }
            container.append(entry);
        });
    }

    function checkAll() { checkName(); types.forEach((type) => showField(type, fieldValidation(type, $(type).value))); updatePreview(); }
    types.forEach((type) => {
        $(type).addEventListener('input', () => { showField(type, fieldValidation(type, $(type).value)); updatePreview(); notice($('form-message'), ''); });
        $(type).addEventListener('blur', () => showField(type, fieldValidation(type, $(type).value)));
    });
    $('nombre').addEventListener('input', checkName);
    function personData() { return Object.fromEntries(new FormData($('person-form')).entries()); }
    function showServerValidation(result) {
        types.forEach((type) => showField(type, result.fields[type]));
        checkName();
        if (result.errors.nombre) { $('feedback-nombre').textContent = result.errors.nombre; $('nombre').classList.add('is-invalid'); $('nombre').classList.remove('is-valid'); }
        updatePreview();
    }

    $('apply-selection').addEventListener('click', () => {
        if (!types.every((type) => selected[type])) return;
        $('person-form').reset();
        // Reset tiene un listener asíncrono; completar después de que termine.
        setTimeout(() => {
            types.forEach((type) => { $(type).value = selected[type].normalized; showField(type, fieldValidation(type, $(type).value)); });
            updatePreview();
            notice($('form-message'), 'Datos seleccionados cargados. Escribe el nombre y comprueba que los tres datos correspondan a la misma persona.');
            $('nombre').focus();
        }, 0);
        bootstrap.Tab.getOrCreateInstance($('manual-tab')).show();
    });

    $('person-form').addEventListener('reset', () => {
        setTimeout(() => {
            ['nombre', ...types].forEach((type) => { $(type).classList.remove('is-valid', 'is-invalid'); $(type).removeAttribute('aria-invalid'); $(`feedback-${type}`).replaceChildren(); });
            notice($('form-message'), '');
            updatePreview();
        }, 0);
    });

    $('validate-server').addEventListener('click', () => busy($('validate-server'), async () => {
        checkAll();
        try {
            const result = await request('validate', personData());
            showServerValidation(result);
            notice($('form-message'), result.valid ? 'PHP confirmó todos los formatos. Puedes guardar el registro.' : 'PHP encontró errores. Revisa las condiciones de cada campo.', result.valid ? 'success' : 'warning');
        } catch (error) { notice($('form-message'), error.message, 'danger'); }
    }));

    $('person-form').addEventListener('submit', (event) => {
        event.preventDefault();
        const button = $('person-form').querySelector('[type="submit"]');
        if (button.disabled) return;
        checkAll();
        busy(button, async () => {
            try {
                const result = await request('save', personData());
                showServerValidation(result.validation);
                notice($('form-message'), result.message, 'success');
            } catch (error) {
                if (error.data?.validation) showServerValidation(error.data.validation);
                notice($('form-message'), error.message, 'danger');
            }
        });
    });

    async function loadRecords() {
        const version = ++recordsVersion;
        $('records-total').textContent = 'Consultando…';
        try {
            const result = await request('records', null, { q: $('search-records').value, page: recordsPage });
            if (version !== recordsVersion) return;
            recordsPage = result.page; recordsPages = result.pages;
            $('records-total').textContent = `${result.total} registros`;
            $('page-label').textContent = `Página ${result.page} de ${result.pages}`;
            $('previous-page').disabled = result.page <= 1;
            $('next-page').disabled = result.page >= result.pages;
            notice($('records-message'), '');
            const body = $('records-body');
            body.replaceChildren();
            if (!result.records.length) { const row = node('tr'); const cell = node('td', 'empty-table', 'No hay registros para mostrar. Guarda una persona desde Validación manual.'); cell.colSpan = 6; row.append(cell); body.append(row); }
            result.records.forEach((person) => {
                const row = node('tr');
                ['nombre', 'telefono', 'dpi', 'correo', 'created_at'].forEach((key) => row.append(node('td', '', person[key])));
                const cell = node('td');
                const button = node('button', 'delete-button', 'Eliminar');
                button.type = 'button';
                button.setAttribute('aria-label', `Eliminar registro de ${person.nombre}`);
                button.addEventListener('click', () => {
                    if (!confirm(`¿Eliminar el registro de ${person.nombre}? Esta acción eliminará sus datos de SQLite.`)) return;
                    busy(button, async () => {
                        try { await request('delete', { id: Number(person.id) }); await loadRecords(); }
                        catch (error) { notice($('records-message'), error.message, 'danger'); }
                    });
                });
                cell.append(button); row.append(cell); body.append(row);
            });
        } catch (error) {
            if (version !== recordsVersion) return;
            $('records-total').textContent = 'Conexión pendiente';
            $('records-body').replaceChildren();
            $('previous-page').disabled = true; $('next-page').disabled = true;
            notice($('records-message'), error.message, 'warning');
        }
    }
    $('search-form').addEventListener('submit', (event) => { event.preventDefault(); recordsPage = 1; loadRecords(); });
    $('previous-page').addEventListener('click', () => { if (recordsPage > 1) { recordsPage--; loadRecords(); } });
    $('next-page').addEventListener('click', () => { if (recordsPage < recordsPages) { recordsPage++; loadRecords(); } });
    $('new-person').addEventListener('click', () => { openSection('lab'); bootstrap.Tab.getOrCreateInstance($('manual-tab')).show(); $('nombre').focus(); });

    document.querySelectorAll('.run-constraint').forEach((button) => button.addEventListener('click', () => busy(button, async () => {
        const target = $(`constraint-${button.dataset.case}`);
        target.className = 'constraint-result';
        target.textContent = 'Ejecutando la transacción en SQLite…';
        try {
            const result = await request('constraints', { case: button.dataset.case });
            target.className = `constraint-result ${result.passed ? 'passed' : 'failed'}`;
            target.replaceChildren(node('strong', '', result.passed ? '✓ Restricción comprobada' : '× Prueba no aprobada'), node('div', '', result.result), node('div', '', result.explanation), node('small', '', `Código SQLite: ${result.driver_code ?? 'sin rechazo'} · ROLLBACK realizado`));
        } catch (error) { target.className = 'constraint-result failed'; target.textContent = `${error.message} La prueba queda pendiente; no se considera aprobada.`; }
    })));

    invalidateAnalysis();
    updatePreview();
})();
