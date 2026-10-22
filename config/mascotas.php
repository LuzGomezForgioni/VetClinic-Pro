<?php
/** Utilidades de presentación y validación para mascotas. */
function iconoEspecieMascota(?string $nombre): string
{
    $especie = mb_strtolower(trim($nombre ?? ''), 'UTF-8');
    if (str_contains($especie, 'perro') || str_contains($especie, 'canino')) return '🐶';
    if (str_contains($especie, 'gato') || str_contains($especie, 'felino')) return '🐱';
    if (str_contains($especie, 'conejo')) return '🐰';
    if (str_contains($especie, 'ave') || str_contains($especie, 'pájaro') || str_contains($especie, 'pajaro')) return '🐦';
    if (str_contains($especie, 'reptil')) return '🦎';
    return '🐾';
}

function colorMascotaValido(string $color): bool
{
    // Se admiten descripciones: «Marrón y blanco», «Gris/negro», «Atigrado (claro)».
    // Nunca números, símbolos extraños ni contenido que no contenga letras.
    return $color === '' || (
        mb_strlen($color, 'UTF-8') <= 60 &&
        preg_match('/\p{L}/u', $color) === 1 &&
        preg_match('/^[\p{L}\p{M}\s,\.\-\/()]+$/u', $color) === 1
    );
}
