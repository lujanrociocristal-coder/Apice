/* ============================================================================
 *  APICE - PUENTE CON EL SERVIDOR (api.js)
 *
 *  Que hace, en simple:
 *   1) Hace que la app GUARDE y LEA sus datos en el servidor (no en el
 *      navegador), compartidos por todo el estudio. Para eso reemplaza el
 *      "almacen" que la app ya usaba (window.storage).
 *   2) Conecta tu pantalla de bienvenida (Soy profesional / terminos) con el
 *      LOGIN REAL del servidor (email + contrasena).
 *
 *  No cambia tus pantallas ni tu logica: solo la forma de guardar y el ingreso.
 *  Este archivo se carga ANTES del codigo de tu app.
 * ========================================================================== */
(function () {
  'use strict';

  // Direccion base de la API (mismo dominio, carpeta /api).
  var BASE = '/api';

  // Llama al servidor y devuelve los datos ya leidos.
  async function apiFetch(path, method, body) {
    var opt = {
      method: method || 'GET',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin'
    };
    if (body !== undefined) opt.body = JSON.stringify(body);
    var res = await fetch(BASE + path, opt);
    var json = null;
    try { json = await res.json(); } catch (e) { json = null; }
    if (res.status === 401) { var err = new Error('NO_SESION'); err.code = 401; throw err; }
    if (!res.ok || (json && json.ok === false)) {
      throw new Error((json && json.error) ? json.error : ('Error ' + res.status));
    }
    return json ? json.data : null;
  }
  function apiGet(p)     { return apiFetch(p, 'GET'); }
  function apiPost(p, b) { return apiFetch(p, 'POST', b || {}); }
  function apiPut(p, b)  { return apiFetch(p, 'PUT', b || {}); }
  function apiDelete(p)  { return apiFetch(p, 'DELETE'); }

  window.APICE = { get: apiGet, post: apiPost, put: apiPut, del: apiDelete, base: BASE };

  // window.storage: el almacen que la app ya sabe usar, pero contra el servidor.
  // La app llama get/set/delete con una clave; cada clave es un bloque de datos
  // COMPARTIDO por el estudio.
  window.storage = {
    get: async function (clave) {
      try {
        return await apiGet('/estado/' + encodeURIComponent(clave));
      } catch (e) {
        return null; // sin sesion o error: la app mostrara el ingreso y no se rompe
      }
    },
    set: async function (clave, data) {
      return await apiPut('/estado/' + encodeURIComponent(clave), { value: data });
    },
    delete: async function (clave) {
      try {
        return await apiDelete('/estado/' + encodeURIComponent(clave));
      } catch (e) {
        return null;
      }
    }
  };

  // Conectar la pantalla de bienvenida con el login real. Se hace DESPUES de
  // que cargue el codigo de la app (evento load), porque recien ahi existen
  // onbEnter / cerrarSesion para reemplazar.
  window.addEventListener('load', function () {

    // Nuevo comportamiento del boton ingresar/crear cuenta de tu pantalla.
    window.onbEnter = async function (method) {
      var elEmail = document.getElementById('onb_email');
      var elPass = document.getElementById('onb_pass');
      var em = elEmail ? elEmail.value.trim() : '';
      var pw = elPass ? elPass.value : '';
      if (!em || !pw) { alert('Completa tu correo y contrasena para continuar.'); return; }

      var state = (typeof onbState !== 'undefined') ? onbState : {};
      var perfil = state.profile || 'abogado';

      // En esta etapa el portal de clientes todavia no se habilita (secreto
      // profesional: requiere el aislamiento fino por cliente de la etapa
      // relacional). Solo ingresan profesionales.
      if (perfil === 'cliente') {
        alert('El portal para clientes se habilita en la proxima etapa. Por ahora el ingreso es para profesionales del estudio.');
        return;
      }

      var modo = state.mode || 'login';
      try {
        if (modo === 'register') {
          var nombre = em.split('@')[0];
          await apiPost('/auth/register', { nombre: nombre, email: em, password: pw, estudio: ('Estudio de ' + nombre) });
        } else {
          await apiPost('/auth/login', { email: em, password: pw });
        }
      } catch (e) {
        alert(e.message === 'NO_SESION' ? 'Email o contrasena incorrectos.' : (e.message || 'No se pudo ingresar.'));
        return;
      }

      // Registrar la aceptacion de terminos/privacidad (queda guardada con fecha).
      try {
        await apiPost('/auth/aceptar', { perfil: perfil, documentos: ['terminos', 'privacidad', 'cookies'], metodo: method });
      } catch (e) { /* no bloquea el ingreso */ }

      // Marcar el onboarding como aceptado y recargar limpio.
      window.config = window.config || {};
      config.onboarding = {
        accepted: true, profile: perfil, metodo: method, consentimiento: 'explicito',
        cuenta: em, fecha: new Date().toISOString(),
        version: (typeof ONB_VERSION !== 'undefined' ? ONB_VERSION : 1)
      };
      try { await saveConfig(); } catch (e) {}

      // Con la sesion ya activa, la app vuelve a cargar los datos del estudio.
      location.reload();
    };

    // Cerrar sesion de verdad (cierra en el servidor y vuelve al ingreso).
    var cerrarOriginal = window.cerrarSesion;
    window.cerrarSesion = async function () {
      try { await apiPost('/auth/logout'); } catch (e) {}
      if (typeof cerrarOriginal === 'function') { try { cerrarOriginal(); } catch (e) {} }
      location.reload();
    };

    // Ayuda opcional para empezar de cero: borra TODOS los datos del estudio en
    // el servidor (util para limpiar los casos de ejemplo). Se usa escribiendo
    // en la consola del navegador:  apiceLimpiarTodo()
    window.apiceLimpiarTodo = async function () {
      if (!confirm('Borrar TODOS los datos de este estudio en el servidor? No se puede deshacer.')) return;
      var claves = ['gestor_causas_v6', 'gestor_cfg_v9', 'gestor_aud_v1', 'gestor_cli_v1', 'gestor_dir_v1'];
      for (var i = 0; i < claves.length; i++) {
        try { await window.storage.delete(claves[i]); } catch (e) {}
      }
      alert('Listo. Se limpiaron los datos. La app se va a recargar.');
      location.reload();
    };
  });
})();
