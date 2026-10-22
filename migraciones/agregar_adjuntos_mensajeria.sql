-- Ejecutar UNA VEZ sobre tu base veterinaria existente, después de crear_mensajeria.sql.
-- Agrega adjuntos a mensajes sin modificar los mensajes previos ni eliminar registros.
CREATE TABLE IF NOT EXISTS `mensajes_adjuntos` (
    `id_adjunto` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_mensaje` BIGINT UNSIGNED NOT NULL,
    `nombre_original` VARCHAR(160) NOT NULL,
    `nombre_guardado` CHAR(40) NOT NULL,
    `tipo_mime` VARCHAR(100) NOT NULL,
    `tamano_bytes` INT UNSIGNED NOT NULL,
    `fecha_subida` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_adjunto`),
    UNIQUE KEY `uq_adjunto_mensaje` (`id_mensaje`),
    UNIQUE KEY `uq_adjunto_archivo` (`nombre_guardado`),
    CONSTRAINT `fk_adjunto_mensaje` FOREIGN KEY (`id_mensaje`)
        REFERENCES `mensajes` (`id_mensaje`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
