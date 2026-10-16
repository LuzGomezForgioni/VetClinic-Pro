<?php
session_start();
unset($_SESSION['admin_logueado'], $_SESSION['admin_id'], $_SESSION['admin_nombre'], $_SESSION['admin_apellido']);
header('Location: login.php');
exit;
