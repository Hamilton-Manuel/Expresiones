# Verificación de la migración a SQLite

Fecha: 10 de octubre de 2026. Estos resultados corresponden a la adaptación del proyecto existente. MariaDB ya no es necesario; no se realizó commit, push ni despliegue en Render.

## Resultados ejecutados

| Verificación | Resultado real |
| --- | --- |
| Sintaxis PHP local, `php -l` | **18 archivos sin errores.** |
| PHP 8.1.29 + SQLite 3.36.0 local, `tests/run.php` | **104 aprobadas, 0 fallidas:** 81 Regex y 23 SQLite. |
| HTTP local, PHP integrado en `127.0.0.1:8765`, `tests/http.php --db` | **51 aprobadas, 0 fallidas.** |
| `docker build -t regexlab:sqlite .` | **Construcción completada**, imagen oficial `php:8.3-apache-bookworm`. |
| Contenedor: PHP 8.3.35 + SQLite 3.40.1, `tests/run.php` | **104 aprobadas, 0 fallidas.** |
| HTTP contra Apache del contenedor en `127.0.0.1:8766`, `tests/http.php --db` | **51 aprobadas, 0 fallidas.** |
| Microsoft Edge con perfil temporal y el contenedor SQLite | **28 comprobaciones aprobadas**, sin excepciones JavaScript. |
| Arranque Docker sin base previa | Inicialización automática y **3 ejemplos ficticios**; health check 200. |
| Reinicio del mismo contenedor con un registro ficticio adicional | **4 filas conservadas**, sin duplicar ejemplos; health check 200. |
| Contenedor nuevo después de eliminar el contenedor de pruebas | Nueva base con **3 ejemplos**, sin el registro del contenedor anterior; health check 200. |
| `PORT=12345` en un segundo contenedor | Apache configura **`Listen 0.0.0.0:12345`**, crea SQLite y devuelve health check 200. |
| Archivos dentro de la imagen, antes de ejecutar el arranque | No contiene SQLite local ni `config/config.local.php`. |
| Propiedad de SQLite y escritura desde Apache | Archivo propiedad de **www-data**, UID 33; registro y eliminación HTTP comprobados. |
| Proxy HTTPS simulado con `REGEXLAB_TRUST_PROXY=1` | Cookie de sesión **Secure** con `X-Forwarded-Proto: https`. |
| Logs Docker | Arranque correcto con Apache 2.4.68; sin errores fatales; duplicados registran solo clase y códigos técnicos. |
| Configuración `render.yaml` | Revisada con la especificación oficial: Docker, free, autoDeployTrigger commit y `/health.php`; sin bases externas ni discos. |
| Entrega ZIP | **36 archivos íntegros y coincidentes con la fuente**, sin bases ni configuración privada; inicialización nueva y repetida comprobadas desde el ZIP extraído. |
| Despliegue real en Render | **No ejecutado**, pendiente de revisión y autorización del usuario. |

## Cobertura de PHP y SQLite

Los 81 casos Regex conservan teléfono con prefijo y separadores, DPI de 13 dígitos, correo básico, normalización, extracción múltiple, valores etiquetados incorrectos, límites que evitan fragmentos de DPI o números largos, anonimización, offsets UTF-8, escape XSS y límites de entrada.

Los 23 casos SQLite usan un **archivo temporal aislado** y verifican inicialización, tres ejemplos ficticios, idempotencia, PRAGMA, registro normalizado, fecha UTC, NOT NULL, UNIQUE, CHECK y ROLLBACK. También cubren teléfono de siete dígitos, letras, longitud excesiva, salto de línea, NUL y dígitos Unicode; búsqueda literal, SQL Injection, paginación, eliminación, bloqueo concurrente, recuperación tras ROLLBACK y recreación cuando desaparece el archivo.

Las tres restricciones se provocaron realmente con SQL preparado, omitiendo deliberadamente la validación PHP en las pruebas. SQLite rechazó las operaciones con código base **19 (SQLITE_CONSTRAINT)** y la aplicación identificó el tipo esperado. El panel y las pruebas conservaron filas y secuencias después de ROLLBACK. No se aceptó SQL arbitrario desde el navegador.

La inicialización local se ejecutó dos veces: la primera insertó tres ejemplos y la segunda no insertó ninguno. Una base existente vacía conserva su estado; no se reponen datos eliminados. Las solicitudes HTTP no inicializan ni crean un archivo SQLite ausente.

## HTTP y navegador

Los 51 casos HTTP cubren UTF-8, Bootstrap local, exclusión de archivos privados, CSRF, cookies, CSP y nosniff; métodos POST, JSON incorrecto, tipos y tamaños inválidos; validación PHP, extracción, resaltado XSS y anonimización. Con `--db` también comprobaron health check sin sesión, listado, las tres restricciones, guardado, búsqueda, normalización, duplicado 409 y eliminación. Cada recorrido creó y eliminó exclusivamente su propio registro ficticio; conservó el listado inicial.

El recorrido Edge comprobó las pestañas, el aviso temporal, siete coincidencias del ejemplo, selección explícita de los tres tipos, copiado real de valores y texto anonimizado, transferencia al formulario, validación JavaScript/PHP, guardado y duplicado, los tres experimentos SQL, búsqueda, cancelación y aceptación de la confirmación de eliminación, guía educativa, invalidación al editar, HTML escapado, anonimización y vista móvil de **390 px sin desbordamiento horizontal**.

El navegador usó un perfil aislado y una conexión CDP temporal con la biblioteca estándar de Python. No se instalaron paquetes, no se incorporaron dependencias y los scripts, perfiles y capturas de QA se excluyen de la entrega.

## Reproducir

En el Windows de verificación, las DLL SQLite existían pero no estaban habilitadas en el php.ini global:

```console
php -d extension=pdo_sqlite -d extension=sqlite3 tools/init-db.php
php -d extension=pdo_sqlite -d extension=sqlite3 tests/run.php
php -d extension=pdo_sqlite -d extension=sqlite3 -S 127.0.0.1:8765 -t public
```

Con ese servidor activo, en otra terminal:

```console
php tests/http.php http://127.0.0.1:8765 --db
```

Docker incluye las extensiones verificadas `pdo_sqlite`, `sqlite3`, `mbstring` y `curl`:

```console
docker build -t regexlab:sqlite .
docker run --rm --name regexlab-local -p 8765:10000 regexlab:sqlite
docker exec regexlab-local php tests/run.php
php tests/http.php http://127.0.0.1:8765 --db
docker logs regexlab-local
docker stop regexlab-local
```

La prueba HTTPS fue una simulación local del encabezado del proxy; **no acredita un despliegue en Render**. La validación del Blueprint fue documental, sin invocar APIs ni crear servicios de la plataforma.

## Entrega

`php tools/package.php` genera `Practica5_RegexLab.zip` con una lista permitida: fuente PHP, recursos, Bootstrap con licencia, esquema SQLite, configuración de ejemplo, inicializador, pruebas, Docker, Blueprint y documentación. Excluye bases SQLite, WAL/SHM/journal, configuraciones privadas, temporales, logs, metadatos Git y el propio ZIP.

Los servidores y contenedores propios de QA se detienen al terminar. La base local de demostración queda disponible en `storage/regexlab.sqlite`, ignorada por Git, Docker y ZIP. Los únicos pasos externos pendientes son publicar el repositorio y desplegarlo en Render después de la revisión del usuario. Render Free utiliza almacenamiento efímero; los registros y sesiones no tienen garantía de persistencia.
