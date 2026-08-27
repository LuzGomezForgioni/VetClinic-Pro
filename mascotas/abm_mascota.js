/* ============================================================
   VetClinic Pro — abm_mascota.js
   Archivo: mascotas/abm_mascota.js

   Funciones:
   1. Filtrar razas según la especie seleccionada
   2. Validar el formulario
   3. Limpiar errores mientras se completan los campos
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    /* ========================================================
       1. FILTRO DE RAZAS SEGÚN ESPECIE
       ======================================================== */

    var selectEspecie = document.getElementById('id_especie');
    var selectRaza = document.getElementById('id_raza');

    if (selectEspecie && selectRaza) {

        /*
         * Guardamos las razas que PHP ya cargó en el HTML.
         *
         * Cada <option> tiene:
         * value = id_raza
         * data-especie = id_especie
         */
        var todasLasRazas = Array.from(
            selectRaza.querySelectorAll('option[data-especie]')
        ).map(function (opcion) {

            return {
                id: opcion.value,
                nombre: opcion.textContent.trim(),
                especie: opcion.dataset.especie
            };

        });


        function filtrarRazas() {

            // El value del select siempre llega como texto
            var especieElegida = selectEspecie.value;

            // Guardamos la raza que estaba seleccionada
            // para que la edición pueda mantenerla
            var razaAnterior = selectRaza.value;

            // Limpiamos completamente el select
            selectRaza.innerHTML = '';


            // Opción inicial
            var opcionInicial = document.createElement('option');
            opcionInicial.value = '';


            // Si todavía no seleccionó una especie
            if (especieElegida === '') {

                opcionInicial.textContent =
                    'Seleccioná primero una especie...';

                selectRaza.appendChild(opcionInicial);

                return;
            }


            opcionInicial.textContent = 'Seleccioná una raza...';

            selectRaza.appendChild(opcionInicial);


            /*
             * Buscamos solamente las razas cuyo
             * id_especie coincide con la especie seleccionada.
             */
            var razasFiltradas = todasLasRazas.filter(function (raza) {

                return String(raza.especie) === String(especieElegida);

            });


            // Agregamos las razas encontradas
            razasFiltradas.forEach(function (raza) {

                var opcion = document.createElement('option');

                opcion.value = raza.id;
                opcion.textContent = raza.nombre;


                // Esto sirve para editar una mascota
                // y mantener seleccionada su raza
                if (raza.id === razaAnterior) {
                    opcion.selected = true;
                }

                selectRaza.appendChild(opcion);

            });


            // Si la especie no tiene razas cargadas
            if (razasFiltradas.length === 0) {

                opcionInicial.textContent =
                    'No hay razas disponibles';

            }

        }


        // Ejecutar cuando se abre la página
        // También sirve para edición
        filtrarRazas();


        // Ejecutar cada vez que cambia la especie
        selectEspecie.addEventListener('change', function () {

            filtrarRazas();

        });

    }


    /* ========================================================
       2. VALIDACIÓN DEL FORMULARIO
       ======================================================== */

    var form =
        document.getElementById('formAlta') ||
        document.getElementById('formEditar');

    if (!form) return;


    // Mostrar mensaje de error
    function mostrarError(idDiv, mensaje) {

        var div = document.getElementById(idDiv);

        if (div) {

            div.textContent = mensaje;

            div.style.display =
                mensaje ? 'block' : 'none';

        }

    }


    // Limpiar todos los errores
    function limpiarErrores() {

        [
            'errorNombre',
            'errorEspecie',
            'errorSexo',
            'errorFecha',
            'errorPeso'

        ].forEach(function (id) {

            mostrarError(id, '');

        });

    }


    /* ========================================================
       VALIDAR AL ENVIAR
       ======================================================== */

    form.addEventListener('submit', function (e) {

        limpiarErrores();

        var valido = true;


        // -------------------------
        // Nombre
        // -------------------------

        var nombre =
            document.getElementById('nombre');

        if (
            nombre &&
            nombre.value.trim() === ''
        ) {

            mostrarError(
                'errorNombre',
                'El nombre de la mascota es obligatorio.'
            );

            valido = false;

        }


        // -------------------------
        // Especie
        // -------------------------

        var especie =
            document.getElementById('id_especie');

        if (
            especie &&
            especie.value === ''
        ) {

            mostrarError(
                'errorEspecie',
                'Seleccioná una especie.'
            );

            valido = false;

        }


        // -------------------------
        // Sexo
        // -------------------------

        var sexo =
            document.getElementById('sexo');

        if (
            sexo &&
            sexo.value === ''
        ) {

            mostrarError(
                'errorSexo',
                'Seleccioná el sexo.'
            );

            valido = false;

        }


        // -------------------------
        // Fecha de nacimiento
        // -------------------------

        var fecha =
            document.getElementById('fecha_nacimiento');

        if (
            fecha &&
            fecha.value !== ''
        ) {

            var hoy = new Date();

            var fechaElegida =
                new Date(fecha.value);

            hoy.setHours(0, 0, 0, 0);

            if (fechaElegida > hoy) {

                mostrarError(
                    'errorFecha',
                    'La fecha de nacimiento no puede ser en el futuro.'
                );

                valido = false;

            }

        }


        // -------------------------
        // Peso
        // -------------------------

        var peso =
            document.getElementById('peso_kg');

        if (
            peso &&
            peso.value !== ''
        ) {

            var valorPeso =
                parseFloat(peso.value);

            if (
                isNaN(valorPeso) ||
                valorPeso <= 0
            ) {

                mostrarError(
                    'errorPeso',
                    'El peso debe ser un número mayor a cero.'
                );

                valido = false;

            }

        }


        // Si hay errores,
        // no enviamos el formulario
        if (!valido) {

            e.preventDefault();

        }

    });


    /* ========================================================
       3. LIMPIAR ERRORES EN TIEMPO REAL
       ======================================================== */


    // Nombre
    var campoNombre =
        document.getElementById('nombre');

    if (campoNombre) {

        campoNombre.addEventListener(
            'input',
            function () {

                if (
                    this.value.trim() !== ''
                ) {

                    mostrarError(
                        'errorNombre',
                        ''
                    );

                }

            }
        );

    }


    // Peso
    var campoPeso =
        document.getElementById('peso_kg');

    if (campoPeso) {

        campoPeso.addEventListener(
            'input',
            function () {

                var valor =
                    parseFloat(this.value);

                if (
                    !isNaN(valor) &&
                    valor > 0
                ) {

                    mostrarError(
                        'errorPeso',
                        ''
                    );

                }

            }
        );

    }

});