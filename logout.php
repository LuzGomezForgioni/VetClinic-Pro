<?php
// ============================================================
//  VetClinic Pro — Cerrar sesión
//  Archivo: logout.php (en la raíz del proyecto)
// ============================================================
session_start();
session_unset();    // Limpia todas las variables de sesión
session_destroy();  // Destruye la sesión

header('Location: index.php');
exit;
