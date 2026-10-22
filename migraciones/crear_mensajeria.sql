-- VetClinic Pro · Mensajería interna entre propietario y veterinario.
-- Migración incremental: CREA dos tablas; NO borra ni altera registros existentes.
-- Requiere las tablas `propietarios` y `veterinarios` del sistema.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `conversaciones` (
    `id_conversacion` INT NOT NULL AUTO_INCREMENT,
    `id_propietario` INT NOT NULL,
    `id_veterinario` INT NOT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_conversacion`),
    UNIQUE KEY `uq_conversacion_participantes` (`id_propietario`, `id_veterinario`),
    KEY `idx_conversacion_vet_fecha` (`id_veterinario`, `fecha_actualizacion`),
    CONSTRAINT `fk_conversacion_propietario` FOREIGN KEY (`id_propietario`)
        REFERENCES `propietarios` (`id_propietario`) ON UPDATE CASCADE,
    CONSTRAINT `fk_conversacion_veterinario` FOREIGN KEY (`id_veterinario`)
        REFERENCES `veterinarios` (`id_veterinario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `mensajes` (
    `id_mensaje` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_conversacion` INT NOT NULL,
    `remitente_tipo` ENUM('propietario', 'veterinario') NOT NULL,
    `contenido` TEXT NOT NULL,
    `fecha_envio` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `leido_en` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id_mensaje`),
    KEY `idx_mensajes_conversacion` (`id_conversacion`, `id_mensaje`),
    KEY `idx_mensajes_no_leidos` (`id_conversacion`, `remitente_tipo`, `leido_en`),
    CONSTRAINT `fk_mensaje_conversacion` FOREIGN KEY (`id_conversacion`)
        REFERENCES `conversaciones` (`id_conversacion`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
