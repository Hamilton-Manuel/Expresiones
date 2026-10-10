<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Extrae, valida, normaliza y anonimiza datos con expresiones regulares. Práctica 5, Guatemala.">
    <meta name="csrf-token" content="<?= escape($csrf) ?>">
    <title>RegexLab · Laboratorio de datos</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <script src="assets/vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Ir al contenido</a>
<div id="pattern-data" hidden data-patterns="<?= escape(json_encode(Regex::patterns(), JSON_UNESCAPED_UNICODE)) ?>"></div>
<aside class="sidebar">
    <a class="brand" href="./"><span class="brand-symbol">.*</span><span>Regex<span class="brand-light">Lab</span><small>EXPLORA. ENCUENTRA. TRANSFORMA.</small></span></a>
    <div class="sidebar-label">ESPACIO DE TRABAJO</div>
    <nav class="workspace-nav" aria-label="Secciones del proyecto">
        <button class="workspace-link active" data-section="lab" aria-current="page"><span class="nav-symbol">⌘</span> Laboratorio <span class="nav-arrow">↗</span></button>
        <button class="workspace-link" data-section="patterns"><span class="nav-symbol">{}</span> Guía de Regex</button>
        <button class="workspace-link" data-section="records"><span class="nav-symbol">▤</span> Registros</button>
        <button class="workspace-link" data-section="constraints"><span class="nav-symbol">◇</span> Restricciones SQL</button>
    </nav>
    <div class="sidebar-note"><span class="mini-dot"></span> APRENDE HACIENDO<p>Un patrón. Muchas posibilidades.</p><small>Localiza información, entiende su estructura y dale un nuevo formato.</small><span class="note-pattern">/ descubrir /g</span></div>
    <div class="sidebar-footer"><span class="gt-flag" aria-hidden="true">▌▌▌</span> Guatemala <span>PHP + SQLite</span></div>
</aside>
<div class="main-shell">
    <header class="topbar"><span>Práctica 05 <span class="topbar-separator">/</span> Expresiones regulares y restricciones en BD</span><span class="local-badge"><span class="mini-dot"></span> ENTORNO LOCAL</span></header>
    <main id="main">
        <section class="page-intro"><div><div class="eyebrow">DATOS CON ESTRUCTURA. IDEAS CON POSIBILIDADES.</div><h1>Encuentra el patrón<span>.</span></h1><p>Laboratorio de Expresiones Regulares y Validación de Datos</p></div><div class="practice-pill"><span>05</span><div>PRÁCTICA UNIVERSITARIA<small>Regex + integridad de datos</small></div></div></section>
        <div id="global-message" class="alert d-none" role="status" aria-live="polite"></div>
        <div class="demo-notice"><span aria-hidden="true">ⓘ</span> Demostración académica · Los registros son temporales y pueden perderse al reiniciar o desplegar. Usa únicamente datos ficticios.</div>

        <section id="section-lab" class="workspace-section" aria-label="Laboratorio">
            <div class="capability-strip"><div><span class="capability-number">01</span><strong>Detecta</strong><small>Localiza coincidencias</small></div><div><span class="capability-number">02</span><strong>Valida</strong><small>Comprueba el formato</small></div><div><span class="capability-number">03</span><strong>Transforma</strong><small>Normaliza y anonimiza</small></div></div>
            <div class="lab-heading"><div><h2>Tu mesa de experimentos</h2><p>Pega un texto o ingresa tus datos para comenzar.</p></div><span class="subtle-badge">3 patrones · infinitas aplicaciones</span></div>
            <ul class="nav lab-tabs" id="lab-tabs" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link active" id="text-tab" data-bs-toggle="tab" data-bs-target="#text-pane" type="button" role="tab" aria-controls="text-pane" aria-selected="true"><span>⌕</span> Análisis de texto <small>EXTRACCIÓN</small></button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-pane" type="button" role="tab" aria-controls="manual-pane" aria-selected="false"><span>✓</span> Validación manual</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="text-pane" role="tabpanel" aria-labelledby="text-tab" tabindex="0">
                    <div class="analysis-grid">
                        <div>
                            <div class="panel editor-panel"><div class="panel-heading"><div><span class="panel-kicker">ENTRADA</span><h3>De texto libre a datos útiles</h3></div><button type="button" class="btn btn-sm btn-quiet" id="load-example">↻ Cargar ejemplo</button></div>
                                <label class="visually-hidden" for="source-text">Texto para analizar</label>
                                <div class="editor-chrome"><span class="editor-dots" aria-hidden="true">● ● ●</span><span>texto_de_entrada.txt</span><span>UTF-8</span></div>
                                <textarea id="source-text" class="source-editor" rows="10" spellcheck="false" placeholder="Pega aquí información desordenada…" aria-describedby="text-help">Nombre: Juan Pérez
Teléfono: +502 5123-4567
DPI: 1234 56789 0101
Correo: juan.perez@gmail.com

Nombre: María López
Teléfono: 5234 5678
DPI: 2345 67890 0101
Correo: maria@example.com

Correo: usuario@</textarea>
                                <div class="editor-footer"><span id="byte-count">0 / 30 000 bytes</span><span>El texto no se almacena</span></div>
                                <p id="text-help" class="helper-text">Una etiqueta por dato. Usa líneas nuevas, comas o punto y coma para separar valores libres.</p>
                                <div class="editor-actions"><button class="btn btn-primary" type="button" id="analyze-button">Analizar texto <span aria-hidden="true">↗</span></button><button class="btn btn-outline-secondary" type="button" id="anonymize-button">Anonimizar texto</button><button class="btn btn-link ms-auto" type="button" id="clear-text">Limpiar</button></div>
                            </div>
                            <div id="analysis-output" class="d-none" aria-live="polite">
                                <div class="results-title"><h3>Coincidencias detectadas</h3><span id="result-count" class="subtle-badge"></span></div>
                                <div id="results-list" class="results-list"></div>
                                <div class="panel text-views"><div class="panel-heading"><h3>El texto bajo la lupa</h3><div class="legend"><span class="legend-phone">Teléfono</span><span class="legend-dpi">DPI</span><span class="legend-email">Correo</span></div></div><pre id="highlighted-text" class="text-preview"></pre></div>
                                <div class="panel text-views"><div class="panel-heading"><div><span class="panel-kicker">TRANSFORMACIÓN</span><h3>Texto anonimizado</h3></div><button class="btn btn-sm btn-outline-secondary" type="button" id="copy-anonymized">Copiar texto</button></div><pre id="anonymized-text" class="text-preview"></pre><p class="helper-text">Se ocultan las coincidencias válidas. Revisa los valores inválidos antes de compartir; este ocultamiento parcial no garantiza anonimato.</p></div>
                            </div>
                        </div>
                        <aside class="analysis-aside">
                            <div class="panel detection-panel"><span class="panel-kicker">RADAR DE PATRONES</span><h3>¿Qué estamos buscando?</h3><div class="detector detector-phone"><span class="detector-icon">#</span><div><strong>Teléfonos</strong><small>8 dígitos · prefijo +502 opcional</small></div><span id="count-telefono" class="detector-count">0</span></div><div class="detector detector-dpi"><span class="detector-icon">▥</span><div><strong>DPI</strong><small>13 dígitos · con o sin espacios</small></div><span id="count-dpi" class="detector-count">0</span></div><div class="detector detector-email"><span class="detector-icon">@</span><div><strong>Correos</strong><small>usuario@dominio.ext</small></div><span id="count-correo" class="detector-count">0</span></div><div class="radar-summary"><span><b id="count-valid">0</b> válidos</span><span><b id="count-invalid">0</b> inválidos</span></div></div>
                            <div class="panel selection-panel"><span class="panel-kicker">DEL ANÁLISIS AL REGISTRO</span><h3>Tú eliges los datos</h3><p>Selecciona una coincidencia válida de cada tipo. Los datos de distintas personas nunca se combinan automáticamente.</p><dl id="selected-values"><dt>Teléfono</dt><dd id="selected-telefono">Sin seleccionar</dd><dt>DPI</dt><dd id="selected-dpi">Sin seleccionar</dd><dt>Correo</dt><dd id="selected-correo">Sin seleccionar</dd></dl><button id="apply-selection" class="btn btn-dark w-100" type="button" disabled>Completar formulario →</button><button id="clear-selection" class="btn btn-link w-100 btn-sm" type="button">Quitar selección</button></div>
                            <div class="context-note"><span aria-hidden="true">ⓘ</span><p>Una coincidencia demuestra un <strong>formato</strong>, nunca la autenticidad de una persona, teléfono o documento.</p></div>
                        </aside>
                    </div>
                </div>
                <div class="tab-pane fade" id="manual-pane" role="tabpanel" aria-labelledby="manual-tab" tabindex="0">
                    <div class="manual-grid"><div class="panel manual-panel"><div class="panel-heading"><div><span class="panel-kicker">VALIDACIÓN + NORMALIZACIÓN</span><h3>Un registro, tres patrones</h3></div><span class="subtle-badge">PHP tiene la última palabra</span></div>
                        <form id="person-form" novalidate>
                            <div class="mb-4"><label for="nombre" class="form-label">Nombre completo <span>*</span></label><input class="form-control" id="nombre" name="nombre" maxlength="120" autocomplete="name" placeholder="Ej. Juan Pérez" required aria-describedby="feedback-nombre"><div id="feedback-nombre" class="field-feedback"></div></div>
                            <?php foreach (['telefono' => ['Teléfono de Guatemala', '+502 5123-4567', 'tel', 'tel'], 'dpi' => ['DPI de Guatemala', '1234 56789 0101', 'off', 'text'], 'correo' => ['Correo electrónico', 'juan@example.com', 'email', 'email']] as $type => [$label, $placeholder, $autocomplete, $inputType]): ?>
                                <div class="mb-4"><label for="<?= $type ?>" class="form-label"><?= $label ?> <span>*</span></label><input class="form-control" id="<?= $type ?>" name="<?= $type ?>" type="<?= $inputType ?>" autocomplete="<?= $autocomplete ?>" maxlength="<?= $type === 'correo' ? 254 : 20 ?>" placeholder="<?= $placeholder ?>" required aria-describedby="feedback-<?= $type ?>"><div id="feedback-<?= $type ?>" class="field-feedback"></div></div>
                            <?php endforeach; ?>
                            <div id="form-message" role="status" class="alert d-none"></div><div class="form-actions"><button class="btn btn-primary" type="submit">Guardar registro <span aria-hidden="true">↗</span></button><button id="validate-server" class="btn btn-outline-secondary" type="button">Validar</button><button class="btn btn-link" type="reset">Limpiar</button></div>
                            <p class="helper-text mt-3 mb-0">Todos los campos son obligatorios. Solo se guardan valores válidos y normalizados.</p>
                        </form></div><aside><div class="panel normalization-panel"><span class="panel-kicker">VISTA PREVIA</span><h3>Así se guardará</h3><p>Los separadores desaparecen; la información conserva su estructura.</p><div id="normalization-preview" class="normalization-list"></div><div class="context-note"><span>ⓘ</span><p>El dominio del correo se convierte a minúsculas. El usuario conserva sus mayúsculas.</p></div></div><div class="context-note"><span>◇</span><p>SQLite añade una segunda defensa: <strong>NOT NULL, UNIQUE y CHECK</strong>.</p></div></aside></div>
                </div>
            </div>
        </section>

        <section id="section-patterns" class="workspace-section d-none" aria-labelledby="patterns-heading">
            <div class="lab-heading"><div><h2 id="patterns-heading">La anatomía de un patrón</h2><p>Encuentra candidatos con patrones amplios. Valida valores completos con patrones estrictos.</p></div><span class="subtle-badge">Guía para tu presentación</span></div>
            <div class="educational-note">PHP utiliza <code>\A</code> y <code>\z</code> para exigir el inicio y fin absolutos del valor. JavaScript comparte el mismo cuerpo del patrón y exige que la coincidencia cubra toda la cadena. <code>[0-9]</code> acepta únicamente dígitos ASCII.</div>
            <?php $examples = ['telefono' => ['Teléfono', ['51234567', '5123-4567', '+502 5123-4567'], ['5123456', '5123/4567', '+503 51234567'], 'El grupo (?:\+502[ ]?)? reconoce el prefijo opcional. [0-9]{4} exige cuatro dígitos y [ -]? permite un separador entre los grupos. Se normaliza a ocho dígitos.'], 'dpi' => ['DPI', ['1234567890101', '1234 56789 0101'], ['123456789012', '1234-56789-0101'], 'La alternativa | acepta trece dígitos continuos o grupos exactos de 4, 5 y 4 con espacios. No comprueba dígito verificador, departamento ni autenticidad.'], 'correo' => ['Correo', ['juan@gmail.com', 'Ana.Perez+lab@ejemplo.com.gt'], ['usuario@', '@gmail.com', 'usuario.com', 'u suario@gmail.com'], 'El usuario contiene letras, números y símbolos sencillos; los puntos separan segmentos no vacíos. @ separa usuario y dominio. El dominio admite subdominios, sin guiones al inicio o final, y una extensión de 2 a 63 letras. No pretende cubrir todo RFC 5322.']]; ?>
            <div class="pattern-grid">
                <?php foreach ($examples as $type => [$title, $valid, $invalid, $explanation]): ?>
                    <article class="panel pattern-card"><span class="type-tag type-<?= $type ?>"><?= $title ?></span><h3>Validación del valor completo</h3><pre class="regex-code"><code><?= escape(Regex::full($type)) ?></code></pre><p><?= escape($explanation) ?></p><div class="example-columns"><div><strong class="valid-text">✓ Válidos</strong><?php foreach ($valid as $example): ?><code><?= escape($example) ?></code><?php endforeach; ?></div><div><strong class="invalid-text">× Inválidos</strong><?php foreach ($invalid as $example): ?><code><?= escape($example) ?></code><?php endforeach; ?></div></div><span class="code-location">app/Regex.php → app/Validation.php</span></article>
                <?php endforeach; ?>
            </div>
            <article class="panel extraction-guide"><span class="panel-kicker">ENCONTRAR ≠ VALIDAR</span><h3>Patrones de extracción y contexto</h3><p><code>preg_match_all()</code> obtiene candidatos y posiciones en bytes. Los campos etiquetados se evalúan enteros, aunque sean incorrectos. Primero se reservan sus rangos y los correos; después se revisan los bloques numéricos, dando prioridad al DPI.</p><dl><dt>Bloques numéricos</dt><dd><code><?= escape(Regex::FIND_NUMBERS) ?></code></dd><dt>Posibles correos</dt><dd><code><?= escape(Regex::FIND_EMAILS) ?></code></dd><dt>Valores etiquetados</dt><dd><code><?= escape(Regex::FIND_LABELS) ?></code></dd></dl><p>Las comprobaciones de límites y solapamiento impiden aceptar números incrustados en otro dato. Los bloques ambiguos de grupos incompletos no se dividen; separa valores libres con comas, punto y coma o líneas nuevas.</p><p><code>preg_match()</code> valida, <code>preg_match_all()</code> extrae y <code>preg_replace_callback()</code> transforma las coincidencias válidas en sus posiciones originales.</p></article>
        </section>

        <section id="section-records" class="workspace-section d-none" aria-labelledby="records-heading">
            <div class="lab-heading"><div><h2 id="records-heading">Datos con un lugar seguro</h2><p>Registros elegidos por ti, validados por PHP y protegidos por SQLite.</p></div><button class="btn btn-primary" id="new-person" type="button">+ Nuevo registro</button></div>
            <div class="panel records-panel"><div class="records-toolbar"><form id="search-form" class="search-form" role="search"><label class="visually-hidden" for="search-records">Buscar registros</label><input class="form-control" id="search-records" maxlength="120" placeholder="Buscar nombre, teléfono, DPI o correo"><button class="btn btn-outline-secondary" type="submit">Buscar</button></form><span id="records-total" class="subtle-badge">Consultando…</span></div><div id="records-message" class="alert d-none" role="status"></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nombre</th><th>Teléfono</th><th>DPI</th><th>Correo</th><th>Fecha de registro</th><th><span class="visually-hidden">Acciones</span></th></tr></thead><tbody id="records-body"></tbody></table></div><div class="pagination-toolbar"><button class="btn btn-sm btn-outline-secondary" id="previous-page" type="button">← Anterior</button><span id="page-label">Página 1</span><button class="btn btn-sm btn-outline-secondary" id="next-page" type="button">Siguiente →</button></div></div>
        </section>

        <section id="section-constraints" class="workspace-section d-none" aria-labelledby="constraints-heading">
            <div class="lab-heading"><div><h2 id="constraints-heading">Pruebas de restricciones</h2><p>Observa cómo SQLite protege los datos, incluso cuando PHP no los valida.</p></div><span class="subtle-badge">PDO · transacción · ROLLBACK</span></div>
            <div class="educational-note"><strong>Experimentos reales, datos temporales.</strong> Cada botón ejecuta un INSERT fijo directamente en SQLite y revierte la transacción. UNIQUE usa dos registros temporales con el mismo DPI; no requiere modificar personas existentes. SQLite informa de un error de restricción y PHP identifica cuál se incumplió.</div>
            <div class="constraint-grid">
                <?php foreach (ConstraintLab::CASES as $key => $case): ?>
                    <article class="panel constraint-card"><span class="constraint-icon"><?= ['not_null' => '∅', 'unique' => '≠', 'check' => '✓'][$key] ?></span><span class="panel-kicker">RESTRICCIÓN <?= array_search($key, array_keys(ConstraintLab::CASES)) + 1 ?></span><h3><?= escape($case['name']) ?></h3><p><?= escape($case['explanation']) ?></p><pre class="regex-code"><code><?= escape(['not_null' => 'nombre TEXT NOT NULL', 'unique' => 'CONSTRAINT uq_personas_dpi UNIQUE (dpi)', 'check' => "CHECK (length(telefono) = 8\nAND telefono NOT GLOB '*[^0-9]*')"][$key]) ?></code></pre><div class="constraint-operation"><small>OPERACIÓN DE PRUEBA</small><strong><?= escape($case['operation']) ?></strong></div><button class="btn btn-outline-secondary w-100 run-constraint" data-case="<?= $key ?>" type="button">Ejecutar prueba ↗</button><div class="constraint-result" id="constraint-<?= $key ?>" role="status" aria-live="polite">Esperando ejecución contra SQLite.</div></article>
                <?php endforeach; ?>
            </div>
            <div class="context-note"><span>ⓘ</span><p>Un error de conexión no cuenta como prueba aprobada. Solo el código de rechazo esperado demuestra la restricción. No se acepta SQL desde el navegador.</p></div>
        </section>
        <footer class="page-footer"><span><strong>RegexLab</strong> · Aprende a leer entre los datos.</span><span>Práctica 5 <span>↗</span> PHP / SQLite / Bootstrap 5</span></footer>
    </main>
</div>
<noscript><div class="noscript-notice">Activa JavaScript para usar el laboratorio. PHP siempre valida los datos en el servidor.</div></noscript>
</body>
</html>
