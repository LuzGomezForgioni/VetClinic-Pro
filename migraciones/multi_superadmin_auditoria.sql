-- ============================================================
-- VetClinic Pro
-- Migración: múltiples Superadmins + auditoría administrativa
--
-- La tabla administradores YA soporta varias cuentas.
-- Esta migración NO modifica ni elimina administradores existentes.
-- Solo agrega el historial de actividad administrativa.
-- ============================================================

CREATE TABLE IF NOT EXISTS auditoria_administradores (
    id_auditoria BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_administrador INT NULL,
    accion VARCHAR(100) NOT NULL,
    entidad VARCHAR(80) NOT NULL,
    id_entidad INT NULL,
    detalle VARCHAR(2000) NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_auditoria),
    KEY idx_auditoria_admin_fecha (id_administrador, fecha),
    KEY idx_auditoria_entidad (entidad, id_entidad),
    KEY idx_auditoria_fecha (fecha),
    CONSTRAINT fk_auditoria_administrador
        FOREIGN KEY (id_administrador)
        REFERENCES administradores (id_administrador)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
