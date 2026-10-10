# RegexLab — SQLite + Docker + Render

**Laboratorio de Expresiones Regulares y Validación de Datos**  
Práctica 5 — Expresiones Regulares y Restricciones en BD.

Esta versión adapta el proyecto existente a SQLite. Conserva su diseño Bootstrap, el laboratorio Regex, la selección explícita, el resaltado seguro, la normalización, el copiado, la anonimización, los registros y la demostración de restricciones SQL. **MariaDB ya no es necesario.** No requiere credenciales, puertos ni servidores externos de base de datos.

## 1. Tecnologías finales y objetivo académico

- PHP 8.3 con Apache en Docker; PHP 8.1+ para ejecución local sin contenedor.
- SQLite mediante PDO, con `pdo_sqlite`, `sqlite3`, `mbstring` y `session` disponibles.
- HTML5, JavaScript vanilla, CSS y Bootstrap 5.3.3 local, con su licencia MIT.
- Docker para Render; no requiere Composer, Node.js, npm, Laravel ni PHPUnit.
- Extensión PHP `curl` para pruebas HTTP opcionales; `zip` para regenerar la entrega local.

Demuestra teléfono, DPI y correo mediante Regex; `NOT NULL`, `UNIQUE` y `CHECK` mediante un motor SQLite real. Solo guarda las personas que el usuario decide registrar. El texto de los análisis no se almacena. Comprobar un formato no acredita autenticidad ni identidad.

## 2. Ejecutar localmente con SQLite

Desde la raíz del proyecto, comprueba las extensiones y crea la base:

```console
php -v
php -m
php tools/init-db.php
php -S 127.0.0.1:8765 -t public
```

Abre **http://127.0.0.1:8765**. Detén el servidor con `Ctrl+C`.

En Windows, si las DLL ya existen pero `php.ini` no las activa, habilita `extension=pdo_sqlite` y `extension=sqlite3`. También puedes usarlas temporalmente sin editar la instalación:

```console
php -d extension=pdo_sqlite -d extension=sqlite3 tools/init-db.php
php -d extension=pdo_sqlite -d extension=sqlite3 -S 127.0.0.1:8765 -t public
```

`storage/` y la carpeta de sesiones configurada en `session.save_path` deben permitir escritura al usuario de PHP. **El DocumentRoot debe ser `public/`**, nunca la raíz del proyecto. En Linux, asigna `storage/` al usuario que ejecuta PHP y concede permisos al propietario/grupo; Docker lo prepara automáticamente. No uses permisos globales `777`.

La configuración predeterminada no requiere editar archivos:

```text
storage/regexlab.sqlite
busy_timeout: 5000 ms
```

`config/config.example.php` permite copiar una configuración opcional a `config/config.local.php`. Las únicas opciones de SQLite son `path` y `busy_timeout_ms`. También se pueden definir `REGEXLAB_SQLITE_PATH` y `REGEXLAB_BUSY_TIMEOUT_MS` (0–60000 ms), útiles para pruebas aisladas. La ruta debe permanecer fuera de `public/`. No se usan las antiguas variables de credenciales MariaDB.

## 3. Inicialización automática e idempotente

**`tools/init-db.php`** invoca **`app/DatabaseInitializer.php`**. Crea el directorio si hace falta, abre SQLite, activa WAL y aplica **`database.sql`** dentro de una transacción. Un bloqueo de archivo serializa inicializaciones concurrentes.

Si el esquema de personas todavía no existe, inserta tres **personas ficticias** (`Persona Demo Alfa/Beta/Gamma`) con DPI sintéticos y correos bajo `example.invalid`, un dominio reservado para ejemplos inválidos. Si el esquema existe, no repite ejemplos, no borra filas y no repone registros que hayas eliminado. Repetir el comando es seguro para los datos existentes.

La inicialización se realiza **antes de Apache en cada arranque del contenedor** y puede ejecutarse manualmente desde consola. No se invoca en cada solicitud HTTP. Una conexión web a una base ausente falla sin crear silenciosamente un archivo vacío; el nuevo arranque recrea la base y sus ejemplos.

Si la inicialización falla por permisos, configuración o falta de extensión, el contenedor se detiene con un mensaje claro y un código de salida distinto de cero. No borra una base dañada ni la sustituye automáticamente.

## 4. Docker local

La imagen usa **`php:8.3-apache-bookworm`**, oficial de PHP. Incluye los controladores SQLite y comprueba que `pdo_sqlite`, `sqlite3` y `mbstring` estén cargados. Apache sirve exclusivamente `public/`; `storage/` queda fuera de la web, con escritura para `www-data`.

```console
docker build -t regexlab:sqlite .
docker run --rm --name regexlab-local -p 8765:10000 regexlab:sqlite
```

Abre **http://127.0.0.1:8765** y **http://127.0.0.1:8765/health.php**. `/health.php` comprueba que SQLite y la tabla estén disponibles; devuelve JSON 200 o 503, sin sesiones ni datos personales.

El arranque lee `PORT`, con valor predeterminado `10000`, valida su rango y genera `Listen 0.0.0.0:PORT` y el VirtualHost correspondiente. Para probar otro puerto interno:

```console
docker run --rm --name regexlab-otro-puerto -e PORT=12345 -p 8766:12345 regexlab:sqlite
```

Desde otra terminal puedes verificar:

```console
docker exec regexlab-local php tests/run.php
docker exec regexlab-local php tests/http.php http://127.0.0.1:10000 --db
docker logs regexlab-local
docker stop regexlab-local
```

Con `--rm`, el contenedor y su SQLite temporal desaparecen al detenerse. Un nuevo `docker run` comienza con los tres ejemplos ficticios. No se configura ningún volumen ni disco persistente de pago. Si reinicias el mismo contenedor y su archivo aún existe, el inicializador conserva sus registros.

`.dockerignore` y las instrucciones `COPY` excluyen bases locales, WAL/SHM, configuración privada, archivos temporales y ZIP. La imagen no incluye los registros de tu equipo. Los errores PHP se envían a stderr y los logs de acceso Apache omiten los parámetros de búsqueda.

## 5. Uso del laboratorio

**Análisis de texto:** pulsa Cargar ejemplo o pega campos como:

```text
Nombre: Persona ficticia
Teléfono: +502 5123-4567
DPI: 1234 56789 0101
Correo: demo@example.invalid

Teléfono: 5234 5678
Correo: usuario@
```

Analizar texto reconoce cada coincidencia, muestra el original y el normalizado y señala los valores etiquetados inválidos. Elige explícitamente un teléfono, un DPI y un correo para completar el formulario; escribe el nombre y confirma que los datos pertenezcan al mismo registro ficticio. No se agrupan personas automáticamente. Cambiar el texto invalida los resultados y selecciones anteriores.

**Validación manual:** JavaScript muestra condiciones y PHP tiene la autoridad final. Solo se guardan datos válidos y normalizados. **Registros** permite buscar, paginar de veinte en veinte y eliminar con confirmación; los comodines `%` y `_` se tratan literalmente. SQLite `LIKE` ignora mayúsculas ASCII, pero no garantiza equivalencia de todas las mayúsculas/acentos Unicode.

**Anonimizar texto:** oculta coincidencias válidas con `preg_replace_callback()` y mantiene el resto del texto. Los valores incorrectos quedan visibles para revisión. Es ocultamiento parcial, no una garantía de anonimato. Puedes copiar tanto resultados normalizados como texto anonimizado.

| Tipo | Original | Normalizado | Ocultamiento parcial |
| --- | --- | --- | --- |
| Teléfono | `+502 5123-4567` | `51234567` | `****4567` |
| DPI | `1234 56789 0101` | `1234567890101` | `*********0101` |
| Correo | `Demo@EXAMPLE.INVALID` | `Demo@example.invalid` | `D***@EXAMPLE.INVALID` |

## 6. Expresiones regulares de validación

Las constantes están en **`app/Regex.php`**. Se usa `\A(?:patrón)\z` en PHP para exigir el valor completo, incluido el rechazo de un salto de línea final. Los cuerpos del patrón se pasan desde PHP a JavaScript; no hay una segunda lista de patrones que pueda desincronizarse. JavaScript también comprueba que la coincidencia cubra toda la cadena.

### Teléfono

```regex
\A(?:(?:\+502[ ]?)?[0-9]{4}[ -]?[0-9]{4})\z
```

- `(?: ... )?`: grupo opcional sin captura.
- `\+502[ ]?`: prefijo literal `+502` seguido opcionalmente por un espacio.
- `[0-9]{4}`: exactamente cuatro dígitos ASCII.
- `[ -]?`: cero o un espacio/guion entre los dos grupos.

Válidos: `51234567`, `5123-4567`, `5123 4567`, `+502 5123-4567`, `+50251234567`. Inválidos: `5123456`, `512345678`, `5123/4567`, `+503 51234567`.

La práctica verifica **formato de ocho dígitos y prefijo guatemalteco opcional**. No verifica asignación real ni introduce reglas adicionales sobre el primer dígito. Primero se valida; luego se retiran prefijo y separadores. No se eliminan letras de un valor inválido para convertirlo artificialmente en válido.

### DPI

```regex
\A(?:(?:[0-9]{13}|[0-9]{4} [0-9]{5} [0-9]{4}))\z
```

`|` acepta una de dos alternativas: trece dígitos consecutivos o grupos exactos de 4, 5 y 4 separados por un espacio. Válidos: `1234567890101`, `1234 56789 0101`. Inválidos: doce/catorce dígitos, guiones, grupos incorrectos o letras. La normalización elimina los dos espacios reconocidos.

**No incluye validación oficial del dígito verificador, departamento, municipio ni autenticidad del documento.** Incluso un DPI compuesto por trece ceros cumple este ejercicio de formato.

### Correo

```regex
\A(?:[A-Za-z0-9_%+\-]+(?:\.[A-Za-z0-9_%+\-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,63})\z
```

El usuario acepta letras ASCII, números, `_`, `%`, `+`, `-` y segmentos no vacíos separados por puntos. `@` separa usuario y dominio. Cada segmento del dominio comienza y termina en letra/número; puede contener guiones internos. La extensión final contiene entre 2 y 63 letras.

Además del patrón, se limita el usuario a 64 caracteres y el correo completo a 254. Se normaliza solo el dominio a minúsculas. Válidos: `juan@gmail.com`, `Ana.Perez+lab@ejemplo.com.gt`. Inválidos: `usuario@`, `@gmail.com`, `usuario.com`, valores con espacios o `a..b@gmail.com`.

Es una expresión comprensible para estructuras comunes; no implementa todos los formatos de RFC 5322, direcciones internacionales, DNS ni verificación de entregabilidad.

## 7. Extracción, límites y transformación

Encontrar un candidato **no** implica que su formato sea válido. Los patrones de búsqueda son más amplios que los validadores:

| Constante en `app/Regex.php` | Uso |
| --- | --- |
| `FIND_LABELS` | Captura el valor completo tras `Teléfono:`, `DPI:` o `Correo:`; también acepta `=` y `Correo electrónico`. Incluye valores vacíos/incorrectos. |
| `FIND_EMAILS` | Encuentra tokens con `@`, incluso inválidos; reserva sus rangos antes de buscar números. |
| `FIND_NUMBERS` | Captura bloques numéricos completos y usa límites de contexto para evitar fragmentos incrustados en otros datos. |

La guía de Regex de la aplicación muestra los tres patrones exactos de extracción y los componentes de validación. **`app/TextLab.php`** sigue este proceso:

1. Reconoce valores etiquetados completos y reserva sus rangos. Una etiqueta indica un valor; separa campos por líneas, comas, punto y coma o la siguiente etiqueta.
2. Reconoce candidatos de correo fuera de esos rangos. No extrae números del usuario de un correo.
3. Consume bloques numéricos completos con cuantificadores posesivos, evitando retroceder a un prefijo más corto. Evalúa primero DPI y luego teléfono.
4. Comprueba solapamientos y vecinos numéricos. Un DPI no produce un teléfono; un número demasiado largo no produce fragmentos válidos.
5. Solo divide un bloque separado por espacios si **cada parte ya es un valor completo válido**. Los grupos parciales ambiguos como `1234 5678 9012` no se dividen en posibles teléfonos.
6. Mantiene cada aparición como resultado independiente y ordenado, incluso coincidencias repetidas. No agrupa personas ni asocia nombres automáticamente.

Para evitar ambigüedad, separa varios valores libres con **comas, punto y coma o líneas nuevas**, especialmente teléfonos agrupados. Un bloque numérico libre mal formado puede quedar sin clasificar; si quieres demostrar su error, añade una etiqueta. En un correo libre, un signo final de oración (`.`, `!`, `?`) se trata como puntuación; el correo etiquetado se valida exactamente.

`preg_match()` comprueba cada valor; `preg_match_all()` devuelve candidatos y offsets. El resaltado escapa todo el HTML mediante `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` antes de incorporar `<mark>` con clases fijas. Se usan offsets de bytes y `substr()` de forma consistente para respetar UTF-8.

`preg_replace_callback()` reemplaza literales detectados solamente en sus posiciones verificadas; no oculta un fragmento idéntico que aparezca dentro de un número inválido más largo. El texto anonimizado se presenta con `textContent` en JavaScript.

## 8. Restricciones SQLite reales

Esquema en **`database.sql`**:

| Restricción | Implementación | Efecto |
| --- | --- | --- |
| NOT NULL | `nombre TEXT NOT NULL` | Rechaza SQL `NULL`; PHP también rechaza nombres vacíos. |
| UNIQUE | `CONSTRAINT uq_personas_dpi UNIQUE (dpi)` | Impide dos DPI normalizados iguales. |
| CHECK | `length(telefono) = 8 AND telefono NOT GLOB '*[^0-9]*'` | Exige ocho dígitos ASCII. `telefono` también es NOT NULL. |

La condición completa añade `typeof(telefono) = 'text'` e `instr(telefono, char(0)) = 0` para rechazar BLOB y bytes NUL ocultos. `GLOB '*[^0-9]*'` detecta cualquier carácter que no sea un dígito; `NOT GLOB` prohíbe su presencia. No se utiliza `REGEXP`, que no viene incorporado en SQLite.

`id` es `INTEGER PRIMARY KEY AUTOINCREMENT`; `created_at` es texto UTC con `DEFAULT CURRENT_TIMESTAMP`. DPI y correo siguen validados por PHP; SQLite garantiza las tres restricciones académicas y los campos requeridos no nulos.

**`app/Database.php`** conecta con `sqlite:` por PDO, activa `PRAGMA foreign_keys = ON`, `busy_timeout = 5000` y `ignore_check_constraints = OFF`. SQLite permite un escritor a la vez; WAL facilita lectores concurrentes y el timeout espera un bloqueo antes de devolver un mensaje seguro.

## 9. Panel de pruebas SQL

**`app/ConstraintLab.php`** conserva las tres operaciones fijas, ahora ejecutadas en SQLite mediante PDO:

1. INSERT con `nombre = NULL` → NOT NULL.
2. Dos INSERT temporales con el mismo DPI → UNIQUE.
3. INSERT directo con teléfono de siete dígitos → CHECK.

Cada prueba abre una transacción, genera un DPI de prueba ausente y ejecuta `ROLLBACK` en `finally`. No llama al validador PHP para provocar el rechazo y no modifica destructivamente personas existentes. Las filas y cambios de secuencia de las pruebas se revierten. No admite SQL arbitrario ni pruebas elegidas fuera de la lista y requiere POST con CSRF.

Los tres rechazos comparten el código base SQLite **19 (`SQLITE_CONSTRAINT`)**. La aplicación clasifica el tipo mediante el diagnóstico específico emitido por SQLite; también acepta códigos extendidos cuya base sea 19. Solo un rechazo real del tipo esperado aprueba la prueba. La UI muestra restricción, operación, resultado, explicación, código real y ROLLBACK. Un bloqueo, error de conexión o aceptación inesperada no cuenta como aprobación.

## 10. Archivos y seguridad

| Archivo | Responsabilidad |
| --- | --- |
| `app/Regex.php` | Fuente única de patrones compartidos con JavaScript. |
| `app/Validation.php` | Validación PHP y normalización existentes. |
| `app/TextLab.php` | Extracción, límites, resaltado escapado y anonimización existentes. |
| `app/Database.php` | PDO SQLite, PRAGMA y clasificación segura de excepciones. |
| `app/DatabaseInitializer.php` | Esquema y ejemplos idempotentes con bloqueo/transacción. |
| `app/PersonRepository.php` | INSERT, búsqueda, paginación y DELETE preparados. |
| `app/ConstraintLab.php` | Experimentos reales con ROLLBACK. |
| `app/bootstrap.php` | Sesiones, escape HTML y cabeceras de seguridad. |
| `public/index.php`, `public/api.php`, `public/health.php` | Entrada, API JSON y comprobación de salud. |
| `views/laboratory.php`, `public/assets/` | Interfaz conservada y aviso de demostración temporal. |
| `storage/.gitkeep` | Directorio de datos; SQLite generado no se versiona. |
| `config/config.example.php` | Configuración opcional sin credenciales. |
| `database.sql` | Definición de las tres restricciones SQLite. |
| `Dockerfile`, `.dockerignore`, `docker/` | Imagen, Apache, configuración PHP y arranque. |
| `render.yaml` | Blueprint gratuito con Docker y despliegue por commit. |
| `tests/run.php`, `tests/sqlite.php`, `tests/http.php` | Pruebas Regex, SQLite aislado y HTTP. |
| `tools/init-db.php`, `tools/package.php` | Inicialización y ZIP. |
| `docs/VERIFICACION.md` | Resultados reales de esta versión. |

Se conservan consultas preparadas, PHP como autoridad, escape HTML, CSP, protección de framing y `nosniff`, límites de texto/JSON, cookies `HttpOnly` y `SameSite=Strict` y token CSRF en todos los POST. No hay autenticación ni módulos ajenos a la práctica.

Render termina HTTPS en su proxy. El Blueprint define `REGEXLAB_TRUST_PROXY=1` para que el encabezado `X-Forwarded-Proto: https` marque la cookie como Secure. Fuera de un proxy confiable, deja esa variable sin definir. Los logs técnicos no incluyen consultas, texto de análisis, credenciales ni valores personales. Una base ausente o inaccesible se indica como error de entorno, sin detalles internos en el navegador.

## 11. Pruebas

```console
php tests/run.php
```

Ejecuta Regex y SQLite en una **base temporal aislada**: no requiere inicializar tu base local ni modifica sus registros. `--db` se conserva por compatibilidad, pero SQLite ya se prueba por defecto. `--regex-only` ejecuta solo Regex e informa que falta probar SQLite. Salida: 0 si pasan, 1 ante fallos y 2 si falta el controlador SQLite.

La integración prueba inicialización, idempotencia, datos ficticios, recuperación de archivo perdido, registro normalizado, NOT NULL, UNIQUE, CHECK, teléfono con letras/siete dígitos/salto/NUL/Unicode, búsqueda literal, defensa ante SQL Injection, paginación, eliminación, timeout de bloqueo y ROLLBACK. Regex conserva sus casos de extracción múltiple, límites, anonimización y XSS.

Con el servidor local o Docker activo:

```console
php tests/http.php http://127.0.0.1:8765
php tests/http.php http://127.0.0.1:8765 --db
```

El primer comando no guarda registros. `--db` verifica health check, listado, los tres rechazos SQL, registro/normalización, búsqueda, duplicado 409 y eliminación. Crea exclusivamente un registro ficticio propio y lo elimina en el recorrido y en la limpieza final; conserva el listado original. Solo acepta direcciones de loopback para evitar ejecutar estas pruebas contra una aplicación pública por accidente.

En el Windows de esta entrega se usaron `-d extension=pdo_sqlite -d extension=sqlite3` para las pruebas locales. Docker ya incluye las extensiones. Los resultados comprobados y las verificaciones pendientes se indican en **`docs/VERIFICACION.md`**; no se considera probado Render hasta desplegarlo realmente.

## 12. Publicar en GitHub después de revisar

**No se realizó commit ni push durante esta adaptación.** Primero revisa los cambios y el ZIP. Crea en GitHub un repositorio vacío llamado `regexlab`, sin claves ni archivos de base de datos.

Usa una carpeta independiente que contenga únicamente RegexLab. Si `git rev-parse --show-toplevel` apunta a una carpeta padre con otros proyectos, extrae el ZIP en una carpeta nueva fuera de ese repositorio antes de seguir. Así los comandos afectan exclusivamente esta entrega.

```console
cd RUTA_A_LA_CARPETA_INDEPENDIENTE_DE_REGEXLAB
git init -b main
git add .
git status --short
git diff --cached --stat
git commit -m "Migrar RegexLab a SQLite y preparar Docker para Render"
git remote add origin https://github.com/TU_USUARIO/regexlab.git
git push -u origin main
```

Los comandos anteriores son instrucciones para ti; no fueron ejecutados. Antes del commit comprueba que no aparezcan `config.local.php`, archivos `.sqlite`, `-wal`, `-shm`, `.env`, logs, temporales ni ZIP. `.gitignore` los excluye y el paquete solo contiene fuente permitida.

## 13. Vincular GitHub con Render

**Todavía no se creó ni desplegó un servicio en Render.** Después del push autorizado:

1. Entra en Render y conecta GitHub, concediendo acceso únicamente al repositorio de RegexLab.
2. Selecciona **New → Blueprint**, elige ese repositorio y la rama `main`; Render debe encontrar `render.yaml` en la raíz.
3. Revisa el único servicio: nombre `regexlab`, tipo `web`, runtime `docker`, plan **free**; no hay bases externas ni discos.
4. Confirma la creación cuando estés listo. Render construirá `Dockerfile` y ejecutará el arranque automático antes de Apache.
5. Comprueba en los logs la inicialización de tres ejemplos y que el health check `/health.php` devuelva 200.
6. Abre la URL HTTPS proporcionada por Render y verifica Regex, registros y las tres pruebas SQL. No declares aprobado el despliegue si la construcción o salud falla.

`autoDeployTrigger: commit` usa el campo vigente del Blueprint para desplegar nuevos commits de la rama vinculada. Si no se especifica `branch`, se utiliza la rama predeterminada del repositorio, que estos comandos crean como `main`. El servicio escucha en `0.0.0.0` y utiliza `PORT` (10000 por defecto). No requiere build/start commands adicionales del panel porque usa Docker.

Alternativamente, crea un **New → Web Service** desde ese repositorio, selecciona Docker y Free, usa `Dockerfile`, health check `/health.php`, `PORT=10000`, `REGEXLAB_TRUST_PROXY=1` y despliegue automático por commit. El Blueprint es la opción documentada preferida para reproducir la configuración.

## 14. Almacenamiento temporal en Render Free

SQLite está en el sistema de archivos efímero de la instancia. **Los registros pueden perderse al reiniciar, redeployar o suspender el servicio; no existe garantía de persistencia.** Si el archivo ya no está, el arranque crea una base con los ejemplos. Si aún existe, conserva sus filas y no duplica ejemplos.

La interfaz muestra un aviso discreto: demostración académica, datos temporales, solo información ficticia. No introduzcas datos personales auténticos ni uses esta versión como repositorio de información duradera. Las sesiones también pueden expirar tras un reinicio; si CSRF falla, recarga la página.

No se configura PostgreSQL, Neon, MariaDB, discos persistentes ni planes de pago. La elección `free` no elimina las cuotas y condiciones de la plataforma; revisa sus límites actuales antes de publicar.

## 15. ZIP y evidencias académicas

```console
php tools/package.php
```

Genera **`Practica5_RegexLab.zip`** con fuente PHP, HTML de las vistas, JS/CSS, Bootstrap y licencia, esquema, configuración de ejemplo, inicializador, pruebas, Docker, Blueprint y documentación. Excluye SQLite, sidecars, configuración privada, temporales, logs, metadatos Git y el propio ZIP.

Evidencias sugeridas: análisis con múltiples personas y un dato inválido; DPI que produce cero teléfonos; comparación original/normalizado/anonimizado; HTML mostrado sin ejecutarse; validación PHP; registros ficticios; rechazo de DPI duplicado; las tres restricciones con código SQLite y ROLLBACK; pruebas por consola; health check; logs Docker de dos arranques y captura del aviso de registros temporales.

### Referencias oficiales consultadas

- [SQLite: CHECK y CREATE TABLE](https://sqlite.org/lang_createtable.html), [códigos de error](https://sqlite.org/rescode.html), [PRAGMA](https://sqlite.org/pragma.html).
- [Imagen oficial PHP](https://hub.docker.com/_/php), [fuente PHP 8.3 con Apache](https://github.com/docker-library/php/blob/master/8.3/bookworm/apache/Dockerfile).
- [Render: Blueprint vigente](https://render.com/docs/blueprint-spec), [puerto HTTP y proxy HTTPS](https://render.com/docs/web-services), [límites y almacenamiento Free](https://render.com/docs/free).
- [PHP: preg_match](https://www.php.net/manual/en/function.preg-match.php), [preg_match_all](https://www.php.net/manual/en/function.preg-match-all.php), [preg_replace_callback](https://www.php.net/manual/en/function.preg-replace-callback.php).
