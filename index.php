<?php

session_start();

include("config/conexion.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST["email"];
    $password = $_POST["password"];

    // Buscar el propietario por email
    $sql = "SELECT * FROM propietarios WHERE email = '$email'";

    $resultado = $conn->query($sql);

    if ($resultado->num_rows == 0) {

        // El email no existe
        header("Location: index.php?error=email");
        exit();

    } else {

        $propietario = $resultado->fetch_assoc();

        // Comprobar contraseña
        if ($password == $propietario["password"]) {

            // Guardar datos del propietario en la sesión
            $_SESSION["id_propietario"] = $propietario["id_propietario"];
            $_SESSION["nombre"] = $propietario["nombre"];
            $_SESSION["apellido"] = $propietario["apellido"];
            $_SESSION["email"] = $propietario["email"];

            // Login correcto
            header("Location: propietarios/inicio_propietarios.php");
            exit();

        } else {

            // Contraseña incorrecta
            header("Location: index.php?error=password");
            exit();
        }
    }

    $conn->close();
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Iniciar Sesión · VetClinic Pro</title>
    <link rel="icon" type="image/png" href="img/VetClinic Pro.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

    <link rel="stylesheet" href="estilos/index.css" />
</head>

<body>
    <div class="container-fluid min-vh-100">
        <div class="row min-vh-100">

            <!-- PANEL IZQUIERDO -->
            <div class="col-12 col-lg-6 panel-izquierdo d-flex flex-column">
                <div class="logo">
                    <img src="img/VetClinic Pro.png" alt="Logo" class="logo-img">

                    <div>
                        <h4>VetClinic Pro</h4>
                        <span>Sistema de Gestión Veterinaria</span>
                    </div>
                </div>

                <div class="contenido-principal flex-grow-1 d-flex flex-column justify-content-center">
                    <h1>Una plataforma diseñada para el <span><i>bienestar animal.</i></span></h1>
                    <p>Accedé con tu cuenta para gestionar turnos, historiales clínicos y comunicación desde un solo
                        lugar.
                    </p>
                </div>


                <div class="card mascota-card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">

                            <div class="d-flex align-items-center">
                                <div class="pet-icon me-3">🐶</div>
                                <div>
                                    <h6 class="mb-1">Max — Labrador, 4 años</h6>
                                    <small>Próx. turno: hoy 09:00</small>
                                </div>
                            </div>
                            <span class="badge bg-success">
                                Confirmado
                            </span>
                        </div>
                    </div>
                </div>


                <div class="beneficios mt-2">
                    <p>
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Historial clínico digital completo
                    </p>
                    <p>
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Agendá turnos online y recibí recordatorios automáticos
                    </p>
                    <p>
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Acceso rápido a información de vacunaciones y tratamientos
                    </p>
                </div>
            </div>

            <!-- PANEL DERECHO -->
            <div class="col-12 col-lg-6 panel-derecho d-flex justify-content-center align-items-center">
                <div class="login-container">
                    <span class="portal-acceso">Portal de acceso</span>
                    <h2>Iniciar sesión</h2>
                    <p class="subtitulo">Accede a tu cuenta para continuar.</p>
                    <form action="index.php" method="POST">
                        <div class="mb-4">
                            <label class="form-label" for="exampleInputEmail1">Correo electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" class="form-control" placeholder="correo@ejemplo.com"
                                    id="exampleInputEmail1" name="email" aria-describedby="emailHelp" required>
                            </div>
                            <div id="emailHelp" class="form-text">Nunca compartiremos tu correo electrónico con nadie
                                más.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="contraseña">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" class="form-control" placeholder="••••••••" id="contraseña"
                                    name="password" required>
                                <span class="input-group-text border-start-0 bg-white">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox">
                                <label class="form-check-label">Recordarme</label>
                            </div>
                            <a href="#" class="custom-link"> ¿Olvidaste tu contraseña?</a>
                        </div>

                        <button type="submit" class="btn btn-login">
                            Iniciar sesión
                            <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </form>

                    <div class="registrarse">
                        ¿No tienes cuenta?
                        <a class="custom-link" href="propietarios/alta_propietario.php">Registrarse</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php

if (isset($_GET["error"])) {

    if ($_GET["error"] == "email") {
        echo "
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Correo no encontrado',
                text: 'No existe una cuenta registrada con ese correo.',
                confirmButtonColor: '#1a1f5e'
            });
        </script>";
    }

    if ($_GET["error"] == "password") {
        echo "
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Contraseña incorrecta',
                text: 'La contraseña ingresada no es correcta.',
                confirmButtonColor: '#1a1f5e'
            });
        </script>";
    }
}

?>
</body>

</html>