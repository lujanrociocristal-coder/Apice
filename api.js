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

  // Registrar el service worker para que la app sea instalable en el celular.
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () { /* si falla, la app igual funciona */ });
    });
  }

  // Conectar la pantalla de bienvenida con el login real. Se hace DESPUES de
  // que cargue el codigo de la app (evento load), porque recien ahi existen
  // onbEnter / cerrarSesion para reemplazar.
  window.addEventListener('load', function () {

    // ÁPICE NO es de acceso público: se oculta el "Creá una cuenta" y el botón
    // de Google. Solo se ingresa con email y contraseña que crea la administradora.
    try {
      var estilo = document.createElement('style');
      estilo.textContent = '.onb-switch{display:none!important}.onb-google{display:none!important}.onb-or{display:none!important}';
      document.head.appendChild(estilo);
      if (typeof onbState !== 'undefined') {
        onbState.mode = 'login';
        try { if (localStorage.getItem('apice_terms_ok')) onbState.acepta = true; } catch (e) {}
      }
      // Por las dudas, si algo intenta poner modo "registro", lo forzamos a login.
      window.onbMode = function () { if (typeof onbState !== 'undefined') { onbState.mode = 'login'; } if (typeof renderOnboarding === 'function') renderOnboarding(); };
    } catch (e) {}

    // Si la persona entró con una clave temporal, le pedimos crear una nueva
    // antes de dejarla pasar. Devuelve true si la cambió, false si canceló.
    function pedirNuevaClave(claveActual) {
      return new Promise(function (resolve) {
        var ov = document.createElement('div');
        ov.style.cssText = 'position:fixed;inset:0;background:rgba(15,20,30,.55);display:flex;align-items:center;justify-content:center;z-index:99999;padding:16px';
        ov.innerHTML =
          '<div style="background:#fff;border-radius:14px;max-width:400px;width:100%;padding:26px;font-family:system-ui,sans-serif;color:#1C2433">'
          + '<h2 style="font-size:18px;margin:0 0 6px">Cre&aacute; tu nueva contrase&ntilde;a</h2>'
          + '<p style="font-size:13px;color:#6B7280;margin:0 0 14px">Entraste con una clave temporal. Por seguridad, eleg&iacute; una contrase&ntilde;a nueva para continuar.</p>'
          + '<input id="apNuevaClave" type="password" placeholder="Nueva contrase&ntilde;a (m&iacute;n. 6)" style="width:100%;padding:11px 12px;border:1px solid #D3D7DE;border-radius:9px;font-size:14px;box-sizing:border-box">'
          + '<div id="apNuevaMsg" style="color:#8a2828;font-size:12px;margin-top:8px"></div>'
          + '<button id="apNuevaBtn" style="margin-top:14px;width:100%;background:#1C2433;color:#fff;border:0;padding:12px;border-radius:9px;font-size:15px;font-weight:600;cursor:pointer">Guardar y entrar</button>'
          + '</div>';
        document.body.appendChild(ov);
        var input = ov.querySelector('#apNuevaClave');
        var msgEl = ov.querySelector('#apNuevaMsg');
        input.focus();
        async function guardar() {
          var nueva = input.value;
          if (!nueva || nueva.length < 6) { msgEl.textContent = 'La contrasena debe tener al menos 6 caracteres.'; return; }
          try {
            await apiPost('/auth/cambiar-clave', { actual: claveActual, nueva: nueva });
            document.body.removeChild(ov);
            resolve(true);
          } catch (e) {
            msgEl.textContent = e.message || 'No se pudo cambiar la contrasena.';
          }
        }
        ov.querySelector('#apNuevaBtn').addEventListener('click', guardar);
        input.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') guardar(); });
      });
    }

    // Nuevo comportamiento del boton ingresar/crear cuenta de tu pantalla.
    window.onbEnter = async function (method) {
      var elEmail = document.getElementById('onb_email');
      var elPass = document.getElementById('onb_pass');
      var em = elEmail ? elEmail.value.trim() : '';
      var pw = elPass ? elPass.value : '';
      if (!em || !pw) { alert('Completa tu correo y contrasena para continuar.'); return; }

      // Aceptación de términos: obligatoria la PRIMERA vez en este dispositivo.
      // Si ya la aceptó antes acá, no se la volvemos a exigir en cada ingreso.
      var acc = document.getElementById('onb_acepta');
      var yaAcepto = false; try { yaAcepto = !!localStorage.getItem('apice_terms_ok'); } catch (e) {}
      if (acc && !acc.checked && !yaAcepto) { alert('Para continuar tenés que leer y aceptar los Términos y la Política de Privacidad (tildá la casilla).'); return; }

      var state = (typeof onbState !== 'undefined') ? onbState : {};
      var perfil = state.profile || 'abogado';

      // Clientes y profesionales ingresan con email + contraseña. El rol lo
      // determina el servidor; si es cliente, la app abre el Portal del cliente.
      var modo = state.mode || 'login';
      var sesion = null;
      try {
        if (modo === 'register') {
          var nombre = em.split('@')[0];
          sesion = await apiPost('/auth/register', { nombre: nombre, email: em, password: pw, estudio: ('Estudio de ' + nombre) });
        } else {
          sesion = await apiPost('/auth/login', { email: em, password: pw });
        }
      } catch (e) {
        alert(e.message === 'NO_SESION' ? 'Email o contrasena incorrectos.' : (e.message || 'No se pudo ingresar.'));
        return;
      }

      // Si la clave era temporal, pedir una nueva antes de entrar.
      if (sesion && Number(sesion.debe_cambiar_clave) === 1) {
        var ok = await pedirNuevaClave(pw);
        if (!ok) return; // canceló: se queda en la pantalla de ingreso
      }

      // Registrar la aceptacion de terminos/privacidad (queda guardada con fecha).
      try {
        await apiPost('/auth/aceptar', { perfil: perfil, documentos: ['terminos', 'privacidad', 'cookies'], metodo: method });
      } catch (e) { /* no bloquea el ingreso */ }
      try { localStorage.setItem('apice_terms_ok', '1'); } catch (e) {}

      // Marcar el onboarding como aceptado SIN pisar la config real del estudio.
      // IMPORTANTE: leemos la config que YA está en el servidor y solo le
      // agregamos el "onboarding aceptado". Así no se pierden el valor del IUS,
      // los feriados ni ningún ajuste que hayas guardado antes.
      try {
        var real = await window.storage.get('gestor_cfg_v9');
        var cfgObj = (real && real.value) ? JSON.parse(real.value) : {};
        cfgObj.onboarding = {
          accepted: true, profile: perfil, metodo: method, consentimiento: 'explicito',
          cuenta: em, fecha: new Date().toISOString(),
          version: (typeof ONB_VERSION !== 'undefined' ? ONB_VERSION : 1)
        };
        await window.storage.set('gestor_cfg_v9', JSON.stringify(cfgObj));
      } catch (e) { /* si falla, igual entra; init cargará la config del servidor */ }

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
