<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once '../config/conexion.php';
require_once '../config/suscripciones.php';

$plan = obtenerPlanProfesional($conn);

if (!$plan) {
    http_response_code(503);
    exit('El plan profesional no está disponible en este momento.');
}

$precio = (float) $plan['precio_mensual'];
$diasTolerancia = (int) $plan['dias_tolerancia'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Plan Profesional · VetClinic Pro</title>

    <link
        rel="icon"
        type="image/png"
        href="../img/VetClinic Pro.png">

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="../estilos/suscripciones.css">
</head>

<body class="pagina-plan-profesional">

    <!-- ============================================================
         NAVEGACIÓN
    ============================================================= -->

    <nav class="plan-navbar">
        <div class="container plan-navbar-inner">

            <a
                href="../index.php"
                class="plan-marca">

                <img
                    src="../img/VetClinic Pro.png"
                    alt="VetClinic Pro">

                <div>
                    <span class="plan-marca-nombre">
                        VetClinic Pro
                    </span>

                    <span class="plan-marca-subtitulo">
                        Gestión veterinaria
                    </span>
                </div>

            </a>

            <div class="plan-navbar-acciones">

                <a
                    href="../index.php"
                    class="plan-link-acceso">

                    Ya tengo una cuenta

                </a>

                <a
                    href="registro_veterinario.php"
                    class="plan-btn plan-btn-sm">

                    Empezar ahora

                    <i class="bi bi-arrow-right"></i>

                </a>

            </div>

        </div>
    </nav>


    <main>

        <!-- ========================================================
             HERO
        ========================================================= -->

        <section class="plan-hero">

            <div class="plan-orbe plan-orbe-uno"></div>
            <div class="plan-orbe plan-orbe-dos"></div>

            <div class="container position-relative">

                <div class="row align-items-center g-5">

                    <!-- TEXTO -->

                    <div class="col-lg-7">

                        <div class="plan-kicker">
                            <span class="plan-kicker-punto"></span>
                            Plan profesional para veterinarios
                        </div>

                        <h1 class="plan-titulo-principal">

                            Menos tiempo administrando.
                            <span>Más tiempo cuidando pacientes.</span>

                        </h1>

                        <p class="plan-bajada">

                            Centralizá turnos, pacientes, propietarios,
                            historias clínicas, vacunaciones y horarios
                            de atención en una sola plataforma.

                        </p>

                        <div class="plan-hero-acciones">

                            <a
                                href="registro_veterinario.php"
                                class="plan-btn plan-btn-principal">

                                Comenzar con VetClinic Pro

                                <i class="bi bi-arrow-up-right"></i>

                            </a>

                            <a
                                href="#incluye"
                                class="plan-btn plan-btn-secundario">

                                Ver todo lo que incluye

                            </a>

                        </div>

                        <div class="plan-confianza">

                            <div class="plan-confianza-item">
                                <i class="bi bi-check-circle-fill"></i>
                                Sin permanencia
                            </div>

                            <div class="plan-confianza-item">
                                <i class="bi bi-check-circle-fill"></i>
                                Activación inmediata
                            </div>

                            <div class="plan-confianza-item">
                                <i class="bi bi-check-circle-fill"></i>
                                Todo en un solo lugar
                            </div>

                        </div>

                    </div>


                    <!-- TARJETA DE PRECIO -->

                    <div class="col-lg-5">

                        <div class="plan-precio-card">

                            <div class="plan-precio-top">

                                <div>

                                    <span class="plan-chip">
                                        Más elegido
                                    </span>

                                    <h2>
                                        <?= htmlspecialchars($plan['nombre']) ?>
                                    </h2>

                                    <p>
                                        Todo lo necesario para gestionar
                                        tu actividad profesional.
                                    </p>

                                </div>

                                <div class="plan-icono-premium">
                                    <i class="bi bi-stars"></i>
                                </div>

                            </div>


                            <div class="plan-precio">

                                <span class="plan-precio-moneda">
                                    AR$
                                </span>

                                <span class="plan-precio-numero">
                                    <?= number_format($precio, 0, ',', '.') ?>
                                </span>

                                <span class="plan-precio-periodo">
                                    / mes
                                </span>

                            </div>


                            <div class="plan-precio-detalle">

                                <div>
                                    <i class="bi bi-lightning-charge-fill"></i>
                                    Acceso inmediato al aprobarse el pago
                                </div>

                                <div>
                                    <i class="bi bi-shield-check"></i>
                                    <?= $diasTolerancia ?> días de tolerancia
                                    luego del vencimiento
                                </div>

                                <div>
                                    <i class="bi bi-arrow-repeat"></i>
                                    Renovación mensual
                                </div>

                            </div>


                            <a
                                href="registro_veterinario.php"
                                class="plan-btn plan-btn-precio">

                                Crear mi cuenta profesional

                                <i class="bi bi-arrow-right"></i>

                            </a>


                            <p class="plan-precio-nota">

                                Podés cancelar cuando quieras.
                                No hay cargos de permanencia.

                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- ========================================================
             FRANJA DE VALOR
        ========================================================= -->

        <section class="plan-franja">

            <div class="container">

                <div class="plan-franja-grid">

                    <div class="plan-franja-item">
                        <span>01</span>
                        <div>
                            <strong>Agenda organizada</strong>
                            <small>Turnos y disponibilidad en tiempo real</small>
                        </div>
                    </div>

                    <div class="plan-franja-item">
                        <span>02</span>
                        <div>
                            <strong>Información centralizada</strong>
                            <small>Pacientes, propietarios e historiales</small>
                        </div>
                    </div>

                    <div class="plan-franja-item">
                        <span>03</span>
                        <div>
                            <strong>Gestión profesional</strong>
                            <small>Vacunaciones, controles y seguimiento</small>
                        </div>
                    </div>

                </div>

            </div>

        </section>


        <!-- ========================================================
             BENEFICIOS
        ========================================================= -->

        <section
            class="plan-seccion"
            id="incluye">

            <div class="container">

                <div class="plan-encabezado-seccion">

                    <span class="plan-eyebrow">
                        Todo incluido
                    </span>

                    <h2>
                        Una plataforma pensada para simplificar
                        tu trabajo diario.
                    </h2>

                    <p>
                        Desde que comienza tu jornada hasta el último
                        paciente del día, VetClinic Pro mantiene la
                        información organizada y accesible.
                    </p>

                </div>


                <div class="row g-4">

                    <?php
                    $beneficios = [
                        [
                            'icono' => 'bi-calendar2-check',
                            'titulo' => 'Agenda y turnos',
                            'texto' => 'Organizá tu disponibilidad y gestioná turnos de manera clara y ordenada.'
                        ],
                        [
                            'icono' => 'bi-heart-pulse',
                            'titulo' => 'Pacientes',
                            'texto' => 'Accedé rápidamente a los datos de cada mascota y su información principal.'
                        ],
                        [
                            'icono' => 'bi-people',
                            'titulo' => 'Propietarios',
                            'texto' => 'Mantené centralizados los datos de contacto de los responsables de cada paciente.'
                        ],
                        [
                            'icono' => 'bi-journal-medical',
                            'titulo' => 'Historias clínicas',
                            'texto' => 'Registrá diagnósticos, tratamientos, medicación, peso y próximos pasos.'
                        ],
                        [
                            'icono' => 'bi-shield-plus',
                            'titulo' => 'Vacunaciones',
                            'texto' => 'Llevá un seguimiento de vacunas aplicadas y próximos vencimientos.'
                        ],
                        [
                            'icono' => 'bi-clock-history',
                            'titulo' => 'Horarios de atención',
                            'texto' => 'Configurá tus días y franjas horarias para ofrecer solamente turnos disponibles.'
                        ],
                        [
                            'icono' => 'bi-bell',
                            'titulo' => 'Avisos y vencimientos',
                            'texto' => 'Visualizá información importante y próximos eventos desde tu panel.'
                        ],
                        [
                            'icono' => 'bi-credit-card',
                            'titulo' => 'Suscripción y pagos',
                            'texto' => 'Consultá vencimientos, historial de pagos y comprobantes desde tu propia cuenta.'
                        ]
                    ];

                    foreach ($beneficios as $beneficio):
                    ?>

                        <div class="col-md-6 col-xl-3">

                            <article class="plan-beneficio-card">

                                <div class="plan-beneficio-icono">

                                    <i class="bi <?= htmlspecialchars($beneficio['icono']) ?>"></i>

                                </div>

                                <h3>
                                    <?= htmlspecialchars($beneficio['titulo']) ?>
                                </h3>

                                <p>
                                    <?= htmlspecialchars($beneficio['texto']) ?>
                                </p>

                            </article>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </section>


        <!-- ========================================================
             PANEL / EXPERIENCIA
        ========================================================= -->

        <section class="plan-seccion plan-seccion-suave">

            <div class="container">

                <div class="row align-items-center g-5">

                    <div class="col-lg-6">

                        <div class="plan-panel-preview">

                            <div class="plan-preview-barra">

                                <div class="plan-preview-puntos">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>

                                <small>
                                    Panel profesional
                                </small>

                            </div>


                            <div class="plan-preview-contenido">

                                <div class="plan-preview-saludo">

                                    <div>
                                        <small>
                                            Tu jornada
                                        </small>

                                        <strong>
                                            Todo bajo control
                                        </strong>
                                    </div>

                                    <div class="plan-preview-avatar">
                                        VP
                                    </div>

                                </div>


                                <div class="plan-preview-metricas">

                                    <div>
                                        <span>
                                            Turnos de hoy
                                        </span>
                                        <strong>08</strong>
                                    </div>

                                    <div>
                                        <span>
                                            Próximos pacientes
                                        </span>
                                        <strong>05</strong>
                                    </div>

                                    <div>
                                        <span>
                                            Vacunas próximas
                                        </span>
                                        <strong>03</strong>
                                    </div>

                                </div>


                                <div class="plan-preview-lista">

                                    <div>
                                        <span class="plan-preview-hora">
                                            09:00
                                        </span>

                                        <div>
                                            <strong>
                                                Control general
                                            </strong>

                                            <small>
                                                Luna · Canino
                                            </small>
                                        </div>

                                        <span class="plan-preview-estado">
                                            Confirmado
                                        </span>
                                    </div>

                                    <div>
                                        <span class="plan-preview-hora">
                                            09:30
                                        </span>

                                        <div>
                                            <strong>
                                                Vacunación
                                            </strong>

                                            <small>
                                                Milo · Felino
                                            </small>
                                        </div>

                                        <span class="plan-preview-estado">
                                            Confirmado
                                        </span>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="col-lg-6">

                        <span class="plan-eyebrow">
                            Tu espacio profesional
                        </span>

                        <h2 class="plan-titulo-seccion">
                            La información que necesitás,
                            cuando la necesitás.
                        </h2>

                        <p class="plan-texto-seccion">

                            VetClinic Pro reúne las tareas más importantes
                            de tu actividad en un entorno simple, moderno
                            y pensado para trabajar con mayor orden.

                        </p>


                        <div class="plan-lista-destacada">

                            <div>
                                <i class="bi bi-check2"></i>
                                <span>
                                    Encontrá rápidamente información
                                    de pacientes y propietarios.
                                </span>
                            </div>

                            <div>
                                <i class="bi bi-check2"></i>
                                <span>
                                    Evitá superposición de turnos con
                                    horarios disponibles de 30 minutos.
                                </span>
                            </div>

                            <div>
                                <i class="bi bi-check2"></i>
                                <span>
                                    Conservá historiales clínicos y
                                    vacunaciones organizados.
                                </span>
                            </div>

                            <div>
                                <i class="bi bi-check2"></i>
                                <span>
                                    Permití que los propietarios consulten
                                    su información desde su propio portal.
                                </span>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- ========================================================
             CÓMO FUNCIONA
        ========================================================= -->

        <section class="plan-seccion">

            <div class="container">

                <div class="plan-encabezado-seccion plan-encabezado-centro">

                    <span class="plan-eyebrow">
                        Empezá en pocos pasos
                    </span>

                    <h2>
                        Tu cuenta profesional puede estar lista
                        en minutos.
                    </h2>

                </div>


                <div class="plan-pasos">

                    <div class="plan-paso">

                        <div class="plan-paso-numero">
                            1
                        </div>

                        <div class="plan-paso-icono">
                            <i class="bi bi-person-vcard"></i>
                        </div>

                        <h3>
                            Creá tu cuenta
                        </h3>

                        <p>
                            Completá tus datos profesionales,
                            matrícula y especialidad.
                        </p>

                    </div>


                    <div class="plan-paso-linea"></div>


                    <div class="plan-paso">

                        <div class="plan-paso-numero">
                            2
                        </div>

                        <div class="plan-paso-icono">
                            <i class="bi bi-credit-card-2-front"></i>
                        </div>

                        <h3>
                            Completá el pago
                        </h3>

                        <p>
                            Elegí tu medio de pago y confirmá
                            la primera mensualidad.
                        </p>

                    </div>


                    <div class="plan-paso-linea"></div>


                    <div class="plan-paso">

                        <div class="plan-paso-numero">
                            3
                        </div>

                        <div class="plan-paso-icono">
                            <i class="bi bi-rocket-takeoff"></i>
                        </div>

                        <h3>
                            Empezá a usarlo
                        </h3>

                        <p>
                            Al aprobarse el pago, el acceso
                            profesional se activa automáticamente.
                        </p>

                    </div>

                </div>


                <div class="plan-demo-aviso">

                    <i class="bi bi-info-circle"></i>

                    <div>

                        <strong>
                            Entorno de demostración
                        </strong>

                        <span>
                            En esta versión académica, el circuito de pago
                            se visualiza completo pero no realiza cobros reales.
                        </span>

                    </div>

                </div>

            </div>

        </section>


        <!-- ========================================================
             SUSCRIPCIÓN
        ========================================================= -->

        <section class="plan-seccion plan-seccion-suave">

            <div class="container">

                <div class="row align-items-center g-5">

                    <div class="col-lg-6">

                        <span class="plan-eyebrow">
                            Una suscripción clara
                        </span>

                        <h2 class="plan-titulo-seccion">
                            Sabés cuánto pagás y cuándo vence.
                        </h2>

                        <p class="plan-texto-seccion">

                            Desde tu cuenta podés consultar el estado
                            de la suscripción, próximo vencimiento,
                            historial de pagos y comprobantes.

                        </p>

                        <div class="plan-lista-destacada">

                            <div>
                                <i class="bi bi-check2"></i>
                                <span>
                                    Precio mensual visible en todo momento.
                                </span>
                            </div>

                            <div>
                                <i class="bi bi-check2"></i>
                                <span>
                                    <?= $diasTolerancia ?> días de tolerancia
                                    después del vencimiento.
                                </span>
                            </div>

                            <div>
                                <i class="bi bi-check2"></i>
                                <span>
                                    Reactivación automática al regularizar
                                    una suscripción suspendida por deuda.
                                </span>
                            </div>

                            <div>
                                <i class="bi bi-check2"></i>
                                <span>
                                    Tus datos permanecen guardados durante
                                    una suspensión.
                                </span>
                            </div>

                        </div>

                    </div>


                    <div class="col-lg-6">

                        <div class="plan-estado-card">

                            <div class="plan-estado-head">

                                <div>

                                    <small>
                                        Mi suscripción
                                    </small>

                                    <h3>
                                        <?= htmlspecialchars($plan['nombre']) ?>
                                    </h3>

                                </div>

                                <span class="plan-estado-activo">
                                    <i class="bi bi-circle-fill"></i>
                                    Activa
                                </span>

                            </div>


                            <div class="plan-estado-precio">

                                <span>
                                    Próximo vencimiento
                                </span>

                                <strong>
                                    15 de noviembre de 2026
                                </strong>

                            </div>


                            <div class="plan-estado-info">

                                <div>

                                    <small>
                                        Precio mensual
                                    </small>

                                    <strong>
                                        <?= formatoDinero($precio) ?>
                                    </strong>

                                </div>

                                <div>

                                    <small>
                                        Tolerancia
                                    </small>

                                    <strong>
                                        <?= $diasTolerancia ?> días
                                    </strong>

                                </div>

                            </div>


                            <div class="plan-estado-boton">
                                Pagar próxima mensualidad
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- ========================================================
             FAQ
        ========================================================= -->

        <section class="plan-seccion">

            <div class="container">

                <div class="row g-5">

                    <div class="col-lg-4">

                        <span class="plan-eyebrow">
                            Preguntas frecuentes
                        </span>

                        <h2 class="plan-titulo-seccion">
                            Antes de comenzar
                        </h2>

                        <p class="plan-texto-seccion">
                            Algunas respuestas rápidas sobre
                            el funcionamiento del plan.
                        </p>

                    </div>


                    <div class="col-lg-8">

                        <div class="accordion plan-faq" id="faqPlan">

                            <div class="accordion-item">

                                <h3 class="accordion-header">

                                    <button
                                        class="accordion-button"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#faqUno">

                                        ¿Tengo que esperar que aprueben mi cuenta?

                                    </button>

                                </h3>

                                <div
                                    id="faqUno"
                                    class="accordion-collapse collapse show"
                                    data-bs-parent="#faqPlan">

                                    <div class="accordion-body">

                                        No. Una vez que completás tus datos
                                        y el primer pago resulta aprobado,
                                        tu acceso profesional se habilita
                                        automáticamente.

                                    </div>

                                </div>

                            </div>


                            <div class="accordion-item">

                                <h3 class="accordion-header">

                                    <button
                                        class="accordion-button collapsed"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#faqDos">

                                        ¿Qué pasa si se vence mi suscripción?

                                    </button>

                                </h3>

                                <div
                                    id="faqDos"
                                    class="accordion-collapse collapse"
                                    data-bs-parent="#faqPlan">

                                    <div class="accordion-body">

                                        Tenés <?= $diasTolerancia ?> días
                                        de tolerancia para regularizarla.
                                        Durante ese período podés seguir
                                        utilizando VetClinic Pro.

                                    </div>

                                </div>

                            </div>


                            <div class="accordion-item">

                                <h3 class="accordion-header">

                                    <button
                                        class="accordion-button collapsed"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#faqTres">

                                        ¿Pierdo mis datos si la cuenta se suspende?

                                    </button>

                                </h3>

                                <div
                                    id="faqTres"
                                    class="accordion-collapse collapse"
                                    data-bs-parent="#faqPlan">

                                    <div class="accordion-body">

                                        No. Una suspensión por falta de pago
                                        limita temporalmente el acceso, pero
                                        conserva pacientes, turnos, historias
                                        clínicas y demás información.

                                    </div>

                                </div>

                            </div>


                            <div class="accordion-item">

                                <h3 class="accordion-header">

                                    <button
                                        class="accordion-button collapsed"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#faqCuatro">

                                        ¿Puedo cancelar el plan?

                                    </button>

                                </h3>

                                <div
                                    id="faqCuatro"
                                    class="accordion-collapse collapse"
                                    data-bs-parent="#faqPlan">

                                    <div class="accordion-body">

                                        Sí. El plan no tiene permanencia.
                                        Podés solicitar la cancelación y usar
                                        el servicio hasta finalizar el período
                                        ya abonado.

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- ========================================================
             CTA FINAL
        ========================================================= -->

        <section class="plan-cta-seccion">

            <div class="container">

                <div class="plan-cta">

                    <div>

                        <span>
                            VetClinic Pro Profesional
                        </span>

                        <h2>
                            Tu gestión veterinaria,
                            más simple desde hoy.
                        </h2>

                        <p>
                            Creá tu cuenta, completá el primer pago
                            y empezá a organizar tu actividad.
                        </p>

                    </div>


                    <div class="plan-cta-derecha">

                        <div class="plan-cta-precio">

                            <?= formatoDinero($precio) ?>

                            <small>
                                / mes
                            </small>

                        </div>

                        <a
                            href="registro_veterinario.php"
                            class="plan-btn plan-btn-claro">

                            Comenzar ahora

                            <i class="bi bi-arrow-right"></i>

                        </a>

                    </div>

                </div>

            </div>

        </section>

    </main>


    <!-- ============================================================
         FOOTER
    ============================================================= -->

    <footer class="plan-footer">

        <div class="container">

            <div class="plan-footer-inner">

                <a
                    href="../index.php"
                    class="plan-marca">

                    <img
                        src="../img/VetClinic Pro.png"
                        alt="VetClinic Pro">

                    <div>
                        <span class="plan-marca-nombre">
                            VetClinic Pro
                        </span>

                        <span class="plan-marca-subtitulo">
                            Gestión veterinaria
                        </span>
                    </div>

                </a>


                <p>
                    © <?= date('Y') ?> VetClinic Pro ·
                    Plataforma de gestión para profesionales veterinarios.
                </p>

            </div>

        </div>

    </footer>


    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>

</body>

</html>
