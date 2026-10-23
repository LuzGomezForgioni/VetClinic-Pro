<?php

/**
 * Envía el enlace de recuperación usando Gmail SMTP sobre TLS.
 * No requiere Composer ni librerías externas.
 */
function enviarCorreoRecuperacion(
    string $destinatario,
    string $enlace,
    string $nombreUsuario = '',
    ?string &$error = null
): bool {
    $error = null;

    $rutaConfig = __DIR__ . '/mail_config.php';

    if (!file_exists($rutaConfig)) {
        $error = 'No existe config/mail_config.php.';
        return false;
    }

    $config = require $rutaConfig;

    $host = trim((string) ($config['smtp_host'] ?? 'smtp.gmail.com'));
    $port = (int) ($config['smtp_port'] ?? 465);
    $usuario = trim((string) ($config['smtp_usuario'] ?? ''));
    $password = str_replace(' ', '', (string) ($config['smtp_password'] ?? ''));
    $nombreRemitente = trim((string) ($config['nombre_remitente'] ?? 'VetClinic Pro'));

    if (
        $usuario === '' ||
        !filter_var($usuario, FILTER_VALIDATE_EMAIL) ||
        str_contains($usuario, 'TU_CORREO') ||
        $password === '' ||
        str_contains($password, 'TU_CONTRASENA')
    ) {
        $error = 'Falta configurar el correo SMTP. En XAMPP completá config/mail_config.local.php; en un servidor también podés usar VETCLINIC_SMTP_USUARIO y VETCLINIC_SMTP_PASSWORD.';
        return false;
    }

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        $error = 'El destinatario no es un correo válido.';
        return false;
    }

    $contexto = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ],
    ]);

    $errno = 0;
    $errstr = '';

    $socket = @stream_socket_client(
        'ssl://' . $host . ':' . $port,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT,
        $contexto
    );

    if (!$socket) {
        $error = 'No se pudo conectar con Gmail SMTP: ' . $errstr . ' (' . $errno . ').';
        return false;
    }

    stream_set_timeout($socket, 20);

    try {
        smtpEsperarRespuesta($socket, [220]);

        $nombreHost = gethostname() ?: 'localhost';

        smtpEnviarComando($socket, 'EHLO ' . $nombreHost, [250]);
        smtpEnviarComando($socket, 'AUTH LOGIN', [334]);
        smtpEnviarComando($socket, base64_encode($usuario), [334]);
        smtpEnviarComando($socket, base64_encode($password), [235]);
        smtpEnviarComando($socket, 'MAIL FROM:<' . $usuario . '>', [250]);
        smtpEnviarComando($socket, 'RCPT TO:<' . $destinatario . '>', [250, 251]);
        smtpEnviarComando($socket, 'DATA', [354]);

        $saludo = $nombreUsuario !== ''
            ? 'Hola ' . htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') . ','
            : 'Hola,';

        $enlaceSeguro = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');

        $html = '
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#111827;">
<div style="max-width:600px;margin:30px auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
<div style="background:#1a1f5e;padding:28px 32px;color:#fff;">
<h1 style="margin:0;font-size:24px;">VetClinic Pro</h1>
<p style="margin:8px 0 0;opacity:.85;">Recuperación de contraseña</p>
</div>
<div style="padding:32px;">
<p style="font-size:16px;line-height:1.6;margin-top:0;">' . $saludo . '</p>
<p style="font-size:16px;line-height:1.6;">Recibimos una solicitud para restablecer la contraseña de tu cuenta en VetClinic Pro.</p>
<p style="font-size:16px;line-height:1.6;">Tocá el siguiente botón para crear una contraseña nueva:</p>
<div style="text-align:center;margin:30px 0;">
<a href="' . $enlaceSeguro . '" style="display:inline-block;background:#1a1f5e;color:#fff;text-decoration:none;padding:14px 24px;border-radius:10px;font-weight:bold;">Restablecer mi contraseña</a>
</div>
<p style="font-size:14px;line-height:1.6;color:#6b7280;">Este enlace vence en 30 minutos.</p>
<p style="font-size:14px;line-height:1.6;color:#6b7280;">Si no solicitaste este cambio, podés ignorar este correo.</p>
<hr style="border:0;border-top:1px solid #e5e7eb;margin:28px 0;">
<p style="font-size:12px;line-height:1.5;color:#9ca3af;word-break:break-all;">Si el botón no funciona, copiá este enlace en tu navegador:<br>' . $enlaceSeguro . '</p>
</div>
</div>
</body>
</html>';

        $asunto = 'Recuperar contraseña - VetClinic Pro';
        $asuntoCodificado = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
        $nombreCodificado = '=?UTF-8?B?' . base64_encode($nombreRemitente) . '?=';

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $nombreCodificado . ' <' . $usuario . '>',
            'To: <' . $destinatario . '>',
            'Subject: ' . $asuntoCodificado,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        $mensaje = implode("\r\n", $headers) . "\r\n\r\n" .
            chunk_split(base64_encode($html), 76, "\r\n");

        if (@fwrite($socket, $mensaje . "\r\n.\r\n") === false) {
            throw new RuntimeException('No se pudo enviar el contenido del correo al servidor SMTP.');
        }

        smtpEsperarRespuesta($socket, [250]);
        @fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return true;

    } catch (Throwable $e) {
        if (is_resource($socket)) {
            @fwrite($socket, "QUIT\r\n");
            fclose($socket);
        }

        $error = $e->getMessage();
        return false;
    }
}

function smtpEnviarComando($socket, string $comando, array $codigosEsperados): string
{
    if (@fwrite($socket, $comando . "\r\n") === false) {
        throw new RuntimeException('No se pudo enviar un comando al servidor SMTP.');
    }

    return smtpEsperarRespuesta($socket, $codigosEsperados);
}

function smtpEsperarRespuesta($socket, array $codigosEsperados): string
{
    $respuesta = '';
    $codigo = 0;

    while (!feof($socket)) {
        $linea = fgets($socket, 515);

        if ($linea === false) {
            break;
        }

        $respuesta .= $linea;

        if (preg_match('/^(\d{3})([ -])/', $linea, $coincidencia)) {
            $codigo = (int) $coincidencia[1];

            if ($coincidencia[2] === ' ') {
                break;
            }
        }
    }

    $meta = stream_get_meta_data($socket);

    if (!empty($meta['timed_out'])) {
        throw new RuntimeException('La conexión con el servidor SMTP agotó el tiempo de espera.');
    }

    if (!in_array($codigo, $codigosEsperados, true)) {
        throw new RuntimeException('Gmail rechazó la solicitud SMTP. Respuesta: ' . trim($respuesta));
    }

    return $respuesta;
}
