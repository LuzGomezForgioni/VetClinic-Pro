/* VetClinic Pro — Alta y edición de mascotas. Sin dependencias externas. */
document.addEventListener('DOMContentLoaded', () => {
    const especie = document.getElementById('id_especie');
    const raza = document.getElementById('id_raza');
    const buscador = document.getElementById('buscar_raza');

    if (especie && raza) {
        // Copiamos las opciones antes de filtrarlas; no se hacen consultas al servidor.
        const catalogo = [...raza.querySelectorAll('option[data-especie]')].map(option => ({
            id: option.value,
            nombre: option.textContent.trim(),
            especie: option.dataset.especie
        }));
        const razaInicial = raza.value;

        const normalizar = texto => texto.toLocaleLowerCase('es')
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();

        function mostrarRazas(mantenerSeleccion = true) {
            const elegida = especie.value;
            const seleccionActual = mantenerSeleccion ? raza.value : '';
            const busqueda = normalizar(buscador ? buscador.value : '');
            const opciones = catalogo.filter(item => item.especie === elegida &&
                normalizar(item.nombre).includes(busqueda));

            raza.replaceChildren();
            const inicial = new Option(elegida ? 'Sin raza específica' : 'Seleccioná primero una especie', '');
            raza.add(inicial);

            for (const item of opciones) {
                raza.add(new Option(item.nombre, item.id));
            }

            if (opciones.some(item => item.id === seleccionActual)) {
                raza.value = seleccionActual;
            }
            if (!opciones.length && elegida) {
                inicial.textContent = busqueda ? 'No se encontraron razas' : 'Sin razas cargadas';
            }

            raza.disabled = !elegida || !opciones.length;
            if (buscador) {
                buscador.disabled = !elegida;
                buscador.placeholder = elegida ? 'Buscá una raza por nombre...' : 'Seleccioná primero una especie';
            }
        }

        mostrarRazas(false);
        if (razaInicial && [...raza.options].some(option => option.value === razaInicial)) {
            raza.value = razaInicial;
        }

        especie.addEventListener('change', () => {
            if (buscador) buscador.value = '';
            mostrarRazas(false);
        });
        if (buscador) {
            buscador.addEventListener('input', () => mostrarRazas(true));
        }
    }

    const form = document.getElementById('formAlta') || document.getElementById('formEditar');
    if (!form) return;
    const esAlta = form.id === 'formAlta';

    function mostrarError(id, mensaje) {
        const elemento = document.getElementById(id);
        if (!elemento) return;
        elemento.textContent = mensaje;
        elemento.style.display = mensaje ? 'block' : 'none';
    }

    const validarColor = color => {
        if (!color) return true;
        return color.length <= 60 && /\p{L}/u.test(color) &&
            /^[\p{L}\p{M}\s,.\-/()]+$/u.test(color);
    };

    function validar() {
        const nombre = document.getElementById('nombre');
        const especie = document.getElementById('id_especie');
        const sexo = document.getElementById('sexo');
        const fecha = document.getElementById('fecha_nacimiento');
        const peso = document.getElementById('peso_kg');
        const color = document.getElementById('color');

        for (const campo of ['errorNombre', 'errorEspecie', 'errorSexo', 'errorFecha', 'errorPeso', 'errorColor']) {
            mostrarError(campo, '');
        }
        let correcto = true;
        if (nombre && !nombre.value.trim()) {
            mostrarError('errorNombre', 'El nombre de la mascota es obligatorio.');
            correcto = false;
        }
        if (especie && !especie.value) {
            mostrarError('errorEspecie', 'Seleccioná una especie.');
            correcto = false;
        }
        if (sexo && !sexo.value) {
            mostrarError('errorSexo', 'Seleccioná el sexo.');
            correcto = false;
        }
        if (fecha && esAlta && !fecha.value) {
            mostrarError('errorFecha', 'La fecha de nacimiento es obligatoria.');
            correcto = false;
        } else if (fecha && fecha.value) {
            const fechaIngresada = new Date(`${fecha.value}T12:00:00`);
            const hoy = new Date();
            hoy.setHours(23, 59, 59, 999);
            if (Number.isNaN(fechaIngresada.getTime()) || fechaIngresada > hoy) {
                mostrarError('errorFecha', 'Ingresá una fecha de nacimiento válida, no futura.');
                correcto = false;
            }
        }
        if (peso && esAlta && !peso.value.trim()) {
            mostrarError('errorPeso', 'El peso es obligatorio.');
            correcto = false;
        } else if (peso && peso.value.trim()) {
            const valor = Number(peso.value);
            if (!Number.isFinite(valor) || valor <= 0 || valor > 999.99) {
                mostrarError('errorPeso', 'Ingresá un peso mayor a 0 y de hasta 999,99 kg.');
                correcto = false;
            }
        }
        if (color && esAlta && !color.value.trim()) {
            mostrarError('errorColor', 'El color o pelaje es obligatorio.');
            correcto = false;
        } else if (color && !validarColor(color.value.trim())) {
            mostrarError('errorColor', 'El color solo admite letras, espacios y separadores (sin números).');
            correcto = false;
        }
        return correcto;
    }

    form.addEventListener('submit', event => {
        if (!validar()) event.preventDefault();
    });
    // Evita que se escriban o peguen números en el color; el servidor vuelve a validarlo.
    const campoColor = document.getElementById('color');
    if (campoColor) {
        campoColor.addEventListener('input', () => {
            const original = campoColor.value;
            const cursor = campoColor.selectionStart ?? original.length;
            const permitidos = /[^\p{L}\p{M}\s,.\-/()]/gu;
            const limpio = original.replace(permitidos, '');
            if (limpio !== original) {
                const nuevoCursor = original.slice(0, cursor).replace(permitidos, '').length;
                campoColor.value = limpio;
                campoColor.setSelectionRange(nuevoCursor, nuevoCursor);
                mostrarError('errorColor', 'El color solo puede incluir letras, sin números ni símbolos.');
            } else {
                mostrarError('errorColor', '');
            }
        });
    }

    for (const [id, error] of [
        ['nombre', 'errorNombre'], ['id_especie', 'errorEspecie'],
        ['sexo', 'errorSexo'], ['fecha_nacimiento', 'errorFecha'],
        ['peso_kg', 'errorPeso']
    ]) {
        const campo = document.getElementById(id);
        if (campo) campo.addEventListener('input', () => mostrarError(error, ''));
    }
});
