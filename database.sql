-- Práctica 5. Esquema SQLite, ejecutado por tools/init-db.php al arrancar.
-- No elimina datos ni altera tablas existentes. No incluye datos personales.
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS personas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL, -- Restricción 1: no admite SQL NULL
    telefono TEXT NOT NULL,
    dpi TEXT NOT NULL,
    correo TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, -- UTC
    CONSTRAINT uq_personas_dpi UNIQUE (dpi),
    CONSTRAINT chk_personas_telefono CHECK (
        typeof(telefono) = 'text'
        AND length(telefono) = 8
        AND telefono NOT GLOB '*[^0-9]*'
        AND instr(telefono, char(0)) = 0
    )
);

-- GLOB identifica cualquier carácter ajeno a los dígitos ASCII.
-- typeof/instr rechazan además valores BLOB y bytes NUL ocultos.
-- Los ejemplos ficticios se insertan en app/DatabaseInitializer.php,
-- solo si se crea el esquema por primera vez, dentro de una transacción.
