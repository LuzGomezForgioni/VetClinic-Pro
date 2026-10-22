(() => {
    'use strict';
    const enlaces = [...document.querySelectorAll('a[href$="mensajes.php"], a[href*="mensajes.php?"]')];
    const boton = document.getElementById('activarAvisosMensajes');
    const estado = document.getElementById('estadoAvisosMensajes');
    if (!enlaces.length && !boton) return;

    let ultimoVisto = null;
    let consultando = false;
    let habilitado = true;
    const urlApi = '../mensajeria/api.php?accion=estado';

    function cambiarContadores(total) {
        for (const enlace of enlaces) {
            let contador = enlace.querySelector('.contador-mensajes-nav, .contador-mensajes-dinamico, .badge');
            if (total < 1) {
                contador?.remove();
                continue;
            }
            if (!contador) {
                contador = document.createElement('span');
                contador.className = 'contador-mensajes-dinamico';
                enlace.appendChild(contador);
            }
            contador.textContent = total > 99 ? '99+' : String(total);
            contador.setAttribute('aria-label', `${total} mensajes no leídos`);
        }
    }

    function destinoChat(id) {
        const origen = enlaces[0]?.href || window.location.href;
        const destino = new URL(origen, window.location.href);
        destino.search = `?conversacion=${encodeURIComponent(id)}`;
        return destino.href;
    }

    function mostrarAviso(remitente, idConversacion) {
        const destino = destinoChat(idConversacion);
        if (document.visibilityState === 'visible') {
            document.querySelector('.mensajes-notificacion-toast')?.remove();
            const aviso = document.createElement('div');
            aviso.className = 'mensajes-notificacion-toast';
            aviso.setAttribute('role', 'status');
            const cerrar = document.createElement('button');
            cerrar.type = 'button';
            cerrar.setAttribute('aria-label', 'Cerrar aviso');
            cerrar.textContent = '×';
            cerrar.addEventListener('click', () => aviso.remove());
            const titulo = document.createElement('strong');
            titulo.textContent = `Nuevo mensaje de ${remitente}`;
            const detalle = document.createElement('p');
            detalle.textContent = 'Recibiste un mensaje nuevo en VetClinic Pro.';
            const abrir = document.createElement('a');
            abrir.href = destino;
            abrir.textContent = 'Ver conversación';
            aviso.append(cerrar, titulo, detalle, abrir);
            document.body.appendChild(aviso);
        }
        if ('Notification' in window && window.Notification.permission === 'granted') {
            try {
                const n = new Notification('Nuevo mensaje en VetClinic Pro', {
                    body: `Mensaje de ${remitente}. Abrí VetClinic Pro para leerlo.`,
                    tag: `vetclinic-mensaje-${idConversacion}`
                });
                n.onclick = () => { window.focus(); window.location.href = destino; n.close(); };
            } catch (_) {
                // El aviso dentro del sitio sigue funcionando aunque falle la notificación del sistema.
            }
        }
    }

    async function consultar() {
        if (consultando || !habilitado) return;
        consultando = true;
        try {
            const respuesta = await fetch(urlApi, {credentials: 'same-origin', cache: 'no-store'});
            if (respuesta.status === 401 || respuesta.status === 403) {
                habilitado = false;
                return;
            }
            if (!respuesta.ok) throw new Error('No se pudieron consultar mensajes pendientes.');
            const datos = await respuesta.json();
            const total = Number(datos.total);
            const ultimo = Number(datos.ultimo_id);
            if (!Number.isSafeInteger(total) || !Number.isSafeInteger(ultimo)) return;
            cambiarContadores(total);
            if (ultimoVisto !== null && ultimo > ultimoVisto && datos.ultimo?.id_conversacion) {
                mostrarAviso(String(datos.ultimo.contacto || 'tu contacto'), Number(datos.ultimo.id_conversacion));
            }
            if (ultimoVisto === null || ultimo > ultimoVisto) ultimoVisto = ultimo;
        } catch (_) {
            // Los enlaces siguen disponibles aunque el servidor no responda temporalmente.
        } finally {
            consultando = false;
        }
    }

    if (boton) {
        const disponible = 'Notification' in window && window.isSecureContext;
        if (!disponible) {
            boton.disabled = true;
            if (estado) estado.textContent = 'Los avisos de escritorio requieren HTTPS o localhost y un navegador compatible.';
        } else if (window.Notification.permission === 'granted') {
            boton.textContent = 'Avisos del navegador activados';
            boton.disabled = true;
        } else if (window.Notification.permission === 'denied') {
            boton.disabled = true;
            if (estado) estado.textContent = 'Los avisos están bloqueados en el navegador. Podés habilitarlos desde sus permisos.';
        } else {
            boton.addEventListener('click', async () => {
                try {
                    const permiso = await window.Notification.requestPermission();
                    if (permiso === 'granted') {
                        boton.textContent = 'Avisos del navegador activados';
                        boton.disabled = true;
                        if (estado) estado.textContent = 'Recibirás avisos mientras VetClinic Pro esté abierto.';
                    } else if (estado) {
                        estado.textContent = 'Los avisos de escritorio no están habilitados. Seguirán apareciendo avisos dentro del sistema.';
                    }
                } catch (_) {
                    if (estado) estado.textContent = 'No se pudieron activar los avisos de escritorio.';
                }
            });
        }
    }

    consultar();
    const intervalo = window.setInterval(consultar, 15000);
    window.addEventListener('pagehide', () => { habilitado = false; clearInterval(intervalo); });
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') consultar(); });
})();
