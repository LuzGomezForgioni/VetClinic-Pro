(() => {
    'use strict';

    const buscar = document.getElementById('buscarConversaciones');
    const contactos = document.querySelectorAll('[data-contacto-nombre]');
    if (buscar) {
        buscar.addEventListener('input', () => {
            const consulta = buscar.value.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            contactos.forEach((item) => {
                const nombre = (item.dataset.contactoNombre || '').toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                item.hidden = !nombre.includes(consulta);
            });
        });
    }

    const selector = document.getElementById('nuevoContacto');
    if (selector) {
        selector.addEventListener('change', () => {
            if (selector.value && /^\d+$/.test(selector.value)) {
                window.location.href = `mensajes.php?contacto=${encodeURIComponent(selector.value)}`;
            }
        });
    }

    const formulario = document.getElementById('formEnviarMensaje');
    const archivoInput = document.getElementById('mensajeAdjunto');
    const indicador = document.getElementById('indicadorAdjunto');
    const quitar = document.getElementById('quitarAdjunto');
    const maxArchivo = 10 * 1024 * 1024;
    if (archivoInput) {
        archivoInput.addEventListener('change', () => {
            const archivo = archivoInput.files?.[0];
            if (archivo && archivo.size > maxArchivo) {
                archivoInput.value = '';
                if (indicador) indicador.textContent = 'El archivo no puede superar 10 MB.';
                if (quitar) quitar.hidden = true;
                return;
            }
            if (indicador) indicador.textContent = archivo ? `${archivo.name} (${Math.ceil(archivo.size / 1024)} KB)` : '';
            if (quitar) quitar.hidden = !archivo;
        });
        quitar?.addEventListener('click', () => {
            archivoInput.value = '';
            if (indicador) indicador.textContent = '';
            quitar.hidden = true;
        });
    }
    if (formulario) {
        formulario.addEventListener('submit', (evento) => {
            const campo = document.getElementById('mensajeContenido');
            if ((!campo || !campo.value.trim()) && !archivoInput?.files?.length) {
                evento.preventDefault();
                campo?.focus();
                campo?.setCustomValidity('Escribí un mensaje o adjuntá un archivo.');
            } else {
                const boton = formulario.querySelector('button[type="submit"]');
                if (boton) boton.disabled = true;
            }
        });
        const campo = document.getElementById('mensajeContenido');
        campo?.addEventListener('input', () => campo.setCustomValidity(''));
    }

    const historial = document.getElementById('mensajesHistorial');
    if (!historial || historial.dataset.historico === '1') return;
    const id = Number(historial.dataset.conversacion);
    if (!Number.isSafeInteger(id) || id <= 0) return;
    let ultimoId = Number(historial.dataset.ultimo) || 0;
    const rol = historial.dataset.rol;
    const token = historial.dataset.csrf;
    let consultando = false;
    let pausa = false;
    const endpoint = '../mensajeria/api.php';

    const alFinal = () => {
        historial.scrollTop = historial.scrollHeight;
    };
    alFinal();

    async function marcarLeidos() {
        if (document.visibilityState !== 'visible') return;
        const parametros = new URLSearchParams({accion: 'leer', conversacion: String(id), csrf_token: token});
        try {
            const respuesta = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: parametros,
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!respuesta.ok) throw new Error('No se pudo confirmar la lectura');
            const datos = await respuesta.json();
            if (!datos.ok) throw new Error('No se pudo confirmar la lectura');
            const activo = document.querySelector('.mensajes-contacto.seleccionado .mensajes-pendientes');
            activo?.remove();
        } catch (e) {
            // Se volverá a intentar en la siguiente consulta; no se marca como leído localmente.
        }
    }

    function colocarMensaje(m) {
        const articulo = document.createElement('article');
        articulo.className = 'mensaje-burbuja ' + (m.remitente_tipo === rol ? 'propio' : 'recibido');
        articulo.dataset.id = String(m.id_mensaje);
        if (m.contenido) {
            const contenido = document.createElement('div');
            contenido.className = 'mensaje-contenido';
            contenido.textContent = m.contenido; // Nunca interpretar HTML de los usuarios.
            articulo.appendChild(contenido);
        }
        if (Number(m.id_adjunto) > 0) {
            const idAdjunto = Number(m.id_adjunto);
            const nombre = String(m.nombre_original || 'Archivo');
            const esImagen = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(m.tipo_mime);
            const url = `../mensajeria/archivo.php?id=${encodeURIComponent(idAdjunto)}`;
            const descargar = `${url}&descargar=1`;
            const contenedor = document.createElement('div');
            contenedor.className = 'mensaje-adjunto';
            if (esImagen) {
                const enlaceImagen = document.createElement('a');
                enlaceImagen.href = descargar;
                enlaceImagen.title = `Descargar ${nombre}`;
                const imagen = document.createElement('img');
                imagen.src = url;
                imagen.alt = `Imagen adjunta: ${nombre}`;
                imagen.className = 'mensaje-imagen';
                imagen.loading = 'lazy';
                enlaceImagen.appendChild(imagen);
                contenedor.appendChild(enlaceImagen);
            }
            const enlace = document.createElement('a');
            enlace.href = descargar;
            enlace.className = 'mensaje-adjunto-enlace';
            enlace.textContent = `${esImagen ? '↓' : '📎'} ${nombre} (${Math.ceil(Number(m.tamano_bytes) / 1024)} KB)`;
            contenedor.appendChild(enlace);
            articulo.appendChild(contenedor);
        }
        const hora = document.createElement('time');
        const fecha = String(m.fecha_envio || '');
        hora.dateTime = fecha.replace(' ', 'T');
        hora.textContent = fecha.slice(8, 10) + '/' + fecha.slice(5, 7) + ' ' + fecha.slice(11, 16);
        articulo.appendChild(hora);
        document.getElementById('mensajesVacio')?.remove();
        historial.appendChild(articulo);
    }

    async function actualizar() {
        if (consultando || pausa || document.visibilityState !== 'visible') return;
        consultando = true;
        try {
            const url = `${endpoint}?conversacion=${id}&desde=${ultimoId}`;
            const respuesta = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
            if (!respuesta.ok) throw new Error('No se pudieron cargar mensajes');
            const datos = await respuesta.json();
            if (!Array.isArray(datos.mensajes)) throw new Error('Respuesta no válida');
            const pegadoAbajo = historial.scrollHeight - historial.scrollTop - historial.clientHeight < 110;
            let hayRecibidos = false;
            for (const m of datos.mensajes) {
                const nuevoId = Number(m.id_mensaje);
                if (!Number.isSafeInteger(nuevoId) || nuevoId <= ultimoId) continue;
                colocarMensaje(m);
                ultimoId = nuevoId;
                if (m.remitente_tipo !== rol) hayRecibidos = true;
            }
            if (pegadoAbajo) alFinal();
            if (hayRecibidos) await marcarLeidos();
        } catch (e) {
            // Mantener los mensajes ya visibles aunque falle temporalmente la conexión.
        } finally {
            consultando = false;
        }
    }

    marcarLeidos();
    const intervalo = window.setInterval(actualizar, 12000);
    window.addEventListener('pagehide', () => { pausa = true; window.clearInterval(intervalo); });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            marcarLeidos();
            actualizar();
        }
    });
})();
