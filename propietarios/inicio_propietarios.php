<?php
// ============================================================
// VetClinic Pro — Inicio Propietario
// Archivo: propietarios/inicio_propietarios.php
// ============================================================

session_start();

// Si no está logueado, mandar al login
if (empty($_SESSION['logueado'])) {
    header('Location: ../index.php');
    exit;
}

$nombre   = $_SESSION['usuario_nombre']   ?? 'Propietario';
$apellido = $_SESSION['usuario_apellido'] ?? '';

// Día de la semana en español
$dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

$hoy = $dias[date('w')] . ' ' . date('j') . ' de ' . 
       $meses[date('n') - 1] . ' de ' . date('Y');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Inicio — Propietario · VetClinic Pro</title>

    <link rel="icon" type="image/png" href="../img/VetClinic Pro.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    />

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    />

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    />

    <!-- CSS -->
    <link rel="stylesheet" href="../estilos/inicio.css" />
</head>

<body>

<div class="container-fluid p-0">

<div class="row g-0 min-vh-100">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <div class="col-12 col-lg-2 panel-lateral">

        <!-- Logo -->
        <a href="../propietarios/inicio_propietarios.php" class="logo-lateral">

            <img
                src="../img/VetClinic Pro.png"
                alt="Logo VetClinic Pro"
            />

            <div>
                <div class="nombre-sistema">
                    VetClinic Pro
                </div>

                <div class="subtitulo-sistema">
                    Portal del propietario
                </div>
            </div>

        </a>


        <!-- Navegación -->

        <div class="seccion-nav">
            Mi espacio
        </div>

        <a
            href="inicio_propietarios.php"
            class="item-menu activo"
        >
            <i class="bi bi-grid"></i>
            Inicio
        </a>

        <a
            href="../mascotas/inicio_mascota.php"
            class="item-menu"
        >
            <i class="bi bi-heart-pulse"></i>
            Mis mascotas
        </a>

        <a href="#" class="item-menu">
            <i class="bi bi-calendar-check"></i>
            Mis turnos
        </a>

        <a href="#" class="item-menu">
            <i class="bi bi-journal-medical"></i>
            Historial clínico
        </a>


        <div class="seccion-nav">
            Más
        </div>

        <a href="#" class="item-menu">
            <i class="bi bi-shield-plus"></i>
            Vacunaciones
        </a>

        <a href="#" class="item-menu">
            <i class="bi bi-chat-dots"></i>
            Mensajes

            <span
                class="badge ms-auto"
                style="background:var(--azul-claro);color:var(--azul-oscuro);font-size:10px;"
            >
                1
            </span>
        </a>


        <!-- Usuario desde sesión -->

        <div class="usuario-lateral">

            <div class="avatar-usuario">
                <?= strtoupper(substr($nombre, 0, 1)) ?>
            </div>

            <div>

                <div class="nombre-usuario">
                    <?= htmlspecialchars($nombre . ' ' . $apellido) ?>
                </div>

                <div class="rol-usuario">
                    Propietario
                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ÁREA PRINCIPAL
    ====================================================== -->

    <div class="col-12 col-lg-10 area-principal">


        <!-- Barra superior -->

        <div class="barra-superior">

            <div>

                <p class="titulo-pagina">
                    ¡Buen día, <?= htmlspecialchars($nombre) ?>! 👋
                </p>

                <p class="fecha-hoy">
                    <?= $hoy ?> · VetClinic Pro
                </p>

            </div>


            <div class="d-flex gap-2">

                <a
                    href="#"
                    class="icono-barra"
                    title="Notificaciones"
                >
                    <i class="bi bi-bell"></i>
                </a>

                <a
                    href="../logout.php"
                    class="icono-barra"
                    title="Cerrar sesión"
                >
                    <i class="bi bi-box-arrow-right"></i>
                </a>

            </div>

        </div>


        <!-- =================================================
             FILA SUPERIOR
             Mascota + Turno + Vacunas
        ================================================== -->

        <div class="row g-3 mb-4">


            <!-- MASCOTA PRINCIPAL -->

            <div class="col-12 col-lg-4">

                <div class="tarjeta-mascota-principal">

                    <div class="d-flex align-items-start justify-content-between mb-3">

                        <div>

                            <div class="nombre-mascota-hero">
                                Max
                            </div>

                            <div class="raza-mascota">
                                Labrador Dorado · 4 años
                            </div>

                        </div>

                        <div class="avatar-mascota-hero">
                            🐕
                        </div>

                    </div>


                    <div class="dato-mascota">
                        <i class="bi bi-gender-male"></i>
                        <span>Macho · 28 kg</span>
                    </div>

                    <div class="dato-mascota">
                        <i class="bi bi-droplet-half"></i>
                        <span>Grupo sanguíneo: DEA 1.1+</span>
                    </div>

                    <div class="dato-mascota">
                        <i class="bi bi-cpu"></i>
                        <span>Microchip: 982000411234567</span>
                    </div>

                    <div class="dato-mascota">
                        <i class="bi bi-person-circle"></i>
                        <span>Veterinaria: Dra. Valentina Ruiz</span>
                    </div>


                    <div
                        class="mt-3 pt-3"
                        style="border-top:1px solid rgba(255,255,255,.2);"
                    >

                        <a
                            href="#"
                            class="btn-secundario"
                            style="background:rgba(255,255,255,.15); border-color:rgba(255,255,255,.3); color:#fff; font-size:13px;"
                        >
                            <i class="bi bi-eye"></i>
                            Ver ficha completa
                        </a>

                    </div>

                </div>

            </div>


            <!-- PRÓXIMO TURNO -->

            <div class="col-12 col-lg-4">

                <div class="tarjeta h-100">

                    <div class="titulo-tarjeta">

                        <i class="bi bi-calendar-event"></i>
                        Próximo turno

                    </div>


                    <div class="text-center py-2">

                        <div
                            class="icono-metrica icono-azul-oscuro mx-auto mb-3"
                            style="width:56px;height:56px;font-size:26px;"
                        >
                            <i class="bi bi-calendar-check"></i>
                        </div>

                        <div
                            style="font-size:22px; font-weight:800; color:var(--azul-oscuro);"
                        >
                            Jueves 12 de junio
                        </div>

                        <div
                            style="font-size:28px; font-weight:800; color:var(--azul-oscuro); margin:4px 0;"
                        >
                            09:00 hs
                        </div>

                        <div
                            style="font-size:13px; color:var(--texto-gris);"
                        >
                            Dra. Valentina Ruiz · Control general
                        </div>

                    </div>


                    <div
                        class="mt-3 pt-3"
                        style="border-top: 1px solid var(--borde);"
                    >

                        <div class="d-flex gap-2">

                            <a
                                href="#"
                                class="btn-secundario flex-grow-1 justify-content-center"
                                style="font-size:13px;"
                            >
                                <i class="bi bi-pencil"></i>
                                Modificar
                            </a>

                            <a
                                href="#"
                                class="btn-secundario flex-grow-1 justify-content-center"
                                style="font-size:13px; border-color:#fca5a5; color:#991b1b; background:#fff0f0;"
                            >
                                <i class="bi bi-x-circle"></i>
                                Cancelar
                            </a>

                        </div>

                    </div>

                </div>

            </div>


            <!-- VACUNACIÓN -->

            <div class="col-12 col-lg-4">

                <div class="tarjeta h-100">

                    <div class="titulo-tarjeta">

                        <i class="bi bi-shield-check"></i>
                        Estado de vacunación

                    </div>


                    <ul class="lista-vacunas">

                        <li class="item-vacuna">

                            <div class="icono-vacuna">
                                <i class="bi bi-shield-fill-check"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="nombre-vacuna">
                                    Séxtuple
                                </div>

                                <div class="fecha-vacuna">
                                    Vence: 15/11/2025
                                </div>

                            </div>

                            <span class="badge-estado estado-ok">
                                Al día
                            </span>

                        </li>


                        <li class="item-vacuna">

                            <div class="icono-vacuna">
                                <i class="bi bi-shield-fill-check"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="nombre-vacuna">
                                    Antirrábica
                                </div>

                                <div class="fecha-vacuna">
                                    Vence: 07/06/2025
                                </div>

                            </div>

                            <span class="badge-estado estado-alerta">
                                Próxima
                            </span>

                        </li>


                        <li class="item-vacuna">

                            <div class="icono-vacuna">
                                <i class="bi bi-shield-fill-check"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="nombre-vacuna">
                                    Leptospirosis
                                </div>

                                <div class="fecha-vacuna">
                                    Vence: 20/01/2026
                                </div>

                            </div>

                            <span class="badge-estado estado-ok">
                                Al día
                            </span>

                        </li>


                        <li class="item-vacuna">

                            <div
                                class="icono-vacuna"
                                style="background:#fee2e2; color:#991b1b;"
                            >
                                <i class="bi bi-shield-fill-exclamation"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="nombre-vacuna">
                                    Bordetella
                                </div>

                                <div class="fecha-vacuna">
                                    Venció: 01/04/2025
                                </div>

                            </div>

                            <span class="badge-estado estado-vencida">
                                Vencida
                            </span>

                        </li>

                    </ul>

                </div>

            </div>

        </div>


        <!-- =================================================
             BOTONES DE ACCIÓN
        ================================================== -->

        <div class="row g-3 mb-4">

            <div class="col-12">

                <div class="tarjeta">

                    <div class="titulo-tarjeta">

                        <i class="bi bi-lightning-charge-fill"></i>
                        ¿Qué necesitás hacer hoy?

                    </div>


                    <div class="d-flex flex-wrap gap-3">

                                            <a
                            href="../mascotas/inicio_mascota.php"
                            class="btn-principal"
                        >
                            <i class="bi bi-calendar-plus"></i>
                            Ver Mis Mascotas
                        </a>

                        <a
                            href="#"
                            class="btn-principal"
                        >
                            <i class="bi bi-calendar-plus"></i>
                            Solicitar turno
                        </a>

                        <a
                            href="#"
                            class="btn-secundario"
                        >
                            <i class="bi bi-journal-medical"></i>
                            Ver historial clínico
                        </a>

                        <a
                            href="#"
                            class="btn-secundario"
                        >
                            <i class="bi bi-shield-plus"></i>
                            Consultar vacunaciones
                        </a>

                        <a
                            href="#"
                            class="btn-secundario"
                        >
                            <i class="bi bi-chat-dots"></i>
                            Escribir a la veterinaria
                        </a>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             HISTORIAL + RECORDATORIOS + MENSAJES
        ================================================== -->

        <div class="row g-3">


            <!-- ÚLTIMAS CONSULTAS -->

            <div class="col-12 col-lg-4">

                <div class="tarjeta h-100">

                    <div class="titulo-tarjeta">

                        <i class="bi bi-clock-history"></i>
                        Últimas consultas

                    </div>


                    <ul class="lista-historial">

                        <li class="item-historial">

                            <div class="icono-historial">
                                <i class="bi bi-stethoscope"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="tipo-registro">
                                    Control general
                                </div>

                                <div class="fecha-registro">
                                    15/05/2025 · Dra. Ruiz
                                </div>

                            </div>

                        </li>


                        <li class="item-historial">

                            <div class="icono-historial">
                                <i class="bi bi-shield-plus"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="tipo-registro">
                                    Vacunación séxtuple
                                </div>

                                <div class="fecha-registro">
                                    10/04/2025 · Dra. Ruiz
                                </div>

                            </div>

                        </li>


                        <li class="item-historial">

                            <div class="icono-historial">
                                <i class="bi bi-bandaid"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="tipo-registro">
                                    Consulta por dermatitis
                                </div>

                                <div class="fecha-registro">
                                    02/03/2025 · Dra. Ruiz
                                </div>

                            </div>

                        </li>


                        <li class="item-historial">

                            <div class="icono-historial">
                                <i class="bi bi-stethoscope"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="tipo-registro">
                                    Control anual
                                </div>

                                <div class="fecha-registro">
                                    18/01/2025 · Dra. Ruiz
                                </div>

                            </div>

                        </li>

                    </ul>


                    <div class="mt-3">

                        <a
                            href="#"
                            class="btn-secundario w-100 justify-content-center"
                            style="font-size:13px;"
                        >
                            Ver historial completo
                            <i class="bi bi-arrow-right"></i>
                        </a>

                    </div>

                </div>

            </div>


            <!-- RECORDATORIOS -->

            <div class="col-12 col-lg-4">

                <div class="tarjeta h-100">

                    <div class="titulo-tarjeta">

                        <i class="bi bi-bell-fill"></i>
                        Recordatorios

                    </div>


                    <ul class="lista-recordatorios">

                        <li class="item-recordatorio urgente">

                            <i class="bi bi-exclamation-circle-fill"></i>

                            <div>

                                <div class="texto-recordatorio">
                                    Vacuna antirrábica vence en 2 días
                                </div>

                                <div class="sub-recordatorio">
                                    Llamar para coordinar turno urgente
                                </div>

                            </div>

                        </li>


                        <li class="item-recordatorio urgente">

                            <i class="bi bi-exclamation-circle-fill"></i>

                            <div>

                                <div class="texto-recordatorio">
                                    Bordetella vencida desde abril
                                </div>

                                <div class="sub-recordatorio">
                                    Solicitar turno para actualizar
                                </div>

                            </div>

                        </li>


                        <li class="item-recordatorio info">

                            <i class="bi bi-calendar-event"></i>

                            <div>

                                <div class="texto-recordatorio">
                                    Próximo control general: 12/06
                                </div>

                                <div class="sub-recordatorio">
                                    Turno ya confirmado con Dra. Ruiz
                                </div>

                            </div>

                        </li>


                        <li class="item-recordatorio ok">

                            <i class="bi bi-check-circle-fill"></i>

                            <div>

                                <div class="texto-recordatorio">
                                    Desparasitación realizada
                                </div>

                                <div class="sub-recordatorio">
                                    Próxima: diciembre 2025
                                </div>

                            </div>

                        </li>

                    </ul>

                </div>

            </div>


            <!-- MENSAJES -->

            <div class="col-12 col-lg-4">

                <div class="tarjeta h-100">

                    <div class="d-flex align-items-center justify-content-between mb-3">

                        <div class="titulo-tarjeta mb-0">

                            <i class="bi bi-chat-left-dots"></i>
                            Mensajes

                        </div>


                        <a
                            href="#"
                            class="btn-secundario"
                            style="font-size:12px; padding:6px 14px;"
                        >
                            Ver todos
                            <i class="bi bi-arrow-right"></i>
                        </a>

                    </div>


                    <ul class="lista-mensajes">

                        <li class="item-mensaje">

                            <div class="avatar-mensaje">
                                <i class="bi bi-person-circle"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="remitente-mensaje">
                                    Dra. Valentina Ruiz
                                </div>

                                <div class="texto-mensaje">
                                    Hola Carlos! Max está muy bien, seguimos con la dieta indicada y...
                                </div>

                            </div>

                            <div class="d-flex flex-column align-items-end gap-1">

                                <span class="hora-mensaje">
                                    09:30
                                </span>

                                <div class="punto-nuevo"></div>

                            </div>

                        </li>


                        <li class="item-mensaje">

                            <div class="avatar-mensaje">
                                <i class="bi bi-hospital"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="remitente-mensaje">
                                    VetClinic Pro
                                </div>

                                <div class="texto-mensaje">
                                    Recordatorio: tu turno del jueves 12/06 está confirmado.
                                </div>

                            </div>

                            <span class="hora-mensaje">
                                Ayer
                            </span>

                        </li>


                        <li class="item-mensaje">

                            <div class="avatar-mensaje">
                                <i class="bi bi-person-circle"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="remitente-mensaje">
                                    Dra. Valentina Ruiz
                                </div>

                                <div class="texto-mensaje">
                                    La dermatitis de Max mejoró mucho. ¡Excelente trabajo!
                                </div>

                            </div>

                            <span class="hora-mensaje">
                                02/06
                            </span>

                        </li>

                    </ul>


                    <div class="mt-3">

                        <a
                            href="#"
                            class="btn-principal w-100 justify-content-center"
                            style="font-size:13px;"
                        >
                            <i class="bi bi-chat-dots"></i>
                            Enviar mensaje
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>