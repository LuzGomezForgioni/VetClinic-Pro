<?php
/** Helpers de protección de formularios. Requieren una sesión PHP activa. */
function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCsrf(?string $token): bool
{
    return is_string($token) && hash_equals(tokenCsrf(), $token);
}

function exigirCsrf(): void
{
    if (!validarCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Solicitud no válida. Actualizá la página y volvé a intentarlo.');
    }
}
