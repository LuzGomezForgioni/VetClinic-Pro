-- ============================================================
-- VetClinic Pro
-- Migración: estadísticas, reportes y atribución de vacunaciones
-- Ejecutar UNA sola vez sobre la base existente.
-- ============================================================


-- La tabla vacunaciones no indicaba qué veterinario registró la aplicación.
-- Este campo es necesario para métricas y reportes por profesional.
ALTER TABLE vacunaciones
    ADD COLUMN id_veterinario INT NULL AFTER id_mascota;

-- No se completa el veterinario de vacunaciones históricas.
-- La tabla original no almacenaba ese dato y asignarlo por inferencia podría
-- atribuir una aplicación al profesional equivocado. Los registros existentes
-- quedan con id_veterinario = NULL y las nuevas vacunaciones sí guardan el
-- veterinario autenticado que las registra.

ALTER TABLE vacunaciones
    ADD KEY idx_vacunaciones_veterinario_fecha (id_veterinario, fecha_aplicacion),
    ADD CONSTRAINT fk_vacunacion_veterinario
        FOREIGN KEY (id_veterinario)
        REFERENCES veterinarios (id_veterinario)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- Índices compuestos usados por los paneles del veterinario.
ALTER TABLE turnos
    ADD KEY idx_turnos_veterinario_fecha_estado (id_veterinario, fecha_hora, estado);

ALTER TABLE historia_clinica
    ADD KEY idx_historia_veterinario_fecha (id_veterinario, fecha_consulta);

