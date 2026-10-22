<?php
// Valores locales conservados para XAMPP; en producción usá variables de entorno.
$servername = getenv('VETCLINIC_DB_HOST') ?: 'localhost';
$username = getenv('VETCLINIC_DB_USER') ?: 'root';
$password = getenv('VETCLINIC_DB_PASSWORD') !== false ? getenv('VETCLINIC_DB_PASSWORD') : '';
$dbname = getenv('VETCLINIC_DB_NAME') ?: 'veterinaria';

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('VetClinic Pro: error al conectar con MySQL.');
    http_response_code(503);
    exit('No se pudo conectar con la base de datos. Intentá nuevamente más tarde.');
}
