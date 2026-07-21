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

    // ÃPICE NO es de acceso pÃºblico: se oculta el "CreÃ¡ una cuenta" y el botÃ³n
    // de Google. Solo se ingresa con email y contraseÃ±a que crea la administradora.
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

    // Si la persona entrÃ³ con una clave temporal, le pedimos crear una nueva
    // antes de dejarla pasar. Devuelve true si la cambiÃ³, false si cancelÃ³.
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
            await window.APICE.post('/auth/cambiar-clave', { actual: claveActual, nueva: nueva });
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

      // AceptaciÃ³n de tÃ©rminos: obligatoria la PRIMERA vez en este dispositivo.
      // Si ya la aceptÃ³ antes acÃ¡, no se la volvemos a exigir en cada ingreso.
      var acc = document.getElementById('onb_acepta');
      var yaAcepto = false; try { yaAcepto = !!localStorage.getItem('apice_terms_ok'); } catch (e) {}
      if (acc && !acc.checked && !yaAcepto) { alert('Para continuar tenÃ©s que leer y aceptar los TÃ©rminos y la PolÃ­tica de Privacidad (tildÃ¡ la casilla).'); return; }

      var state = (typeof onbState !== 'undefined') ? onbState : {};
      var perfil = state.profile || 'abogado';

      // Clientes y profesionales ingresan con email + contraseÃ±a. El rol lo
      // determina el servidor; si es cliente, la app abre el Portal del cliente.
      var modo = state.mode || 'login';
      var sesion = null;
      try {
        if (modo === 'register') {
          var nombre = em.split('@')[0];
          sesion = await window.APICE.post('/auth/register', { nombre: nombre, email: em, password: pw, estudio: ('Estudio de ' + nombre) });
        } else {
          sesion = await window.APICE.post('/auth/login', { email: em, password: pw });
        }
      } catch (e) {
        alert(e.message === 'NO_SESION' ? 'Email o contrasena incorrectos.' : (e.message || 'No se pudo ingresar.'));
        return;
      }

      // Si la clave era temporal, pedir una nueva antes de entrar.
      if (sesion && Number(sesion.debe_cambiar_clave) === 1) {
        var ok = await pedirNuevaClave(pw);
        if (!ok) return; // cancelÃ³: se queda en la pantalla de ingreso
      }

      // Registrar la aceptacion de terminos/privacidad (queda guardada con fecha).
      try {
        await window.APICE.post('/auth/aceptar', { perfil: perfil, documentos: ['terminos', 'privacidad', 'cookies'], metodo: method });
      } catch (e) { /* no bloquea el ingreso */ }
      try { localStorage.setItem('apice_terms_ok', '1'); } catch (e) {}

      // Marcar el onboarding como aceptado SIN pisar la config real del estudio.
      // IMPORTANTE: leemos la config que YA estÃ¡ en el servidor y solo le
      // agregamos el "onboarding aceptado". AsÃ­ no se pierden el valor del IUS,
      // los feriados ni ningÃºn ajuste que hayas guardado antes.
      try {
        var real = await window.storage.get('gestor_cfg_v9');
        var cfgObj = (real && real.value) ? JSON.parse(real.value) : {};
        cfgObj.onboarding = {
          accepted: true, profile: perfil, metodo: method, consentimiento: 'explicito',
          cuenta: em, fecha: new Date().toISOString(),
          version: (typeof ONB_VERSION !== 'undefined' ? ONB_VERSION : 1)
        };
        await window.storage.set('gestor_cfg_v9', JSON.stringify(cfgObj));
      } catch (e) { /* si falla, igual entra; init cargarÃ¡ la config del servidor */ }

      // Con la sesion ya activa, la app vuelve a cargar los datos del estudio.
      location.reload();
    };

    // Cerrar sesion de verdad (cierra en el servidor y vuelve al ingreso).
    var cerrarOriginal = window.cerrarSesion;
    window.cerrarSesion = async function () {
      try { await window.APICE.post('/auth/logout'); } catch (e) {}
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


/* ===========================================================================
 *  RECUPERAR LA CONTRASENA  (v46)
 *  - Agrega el enlace "Olvide mi contrasena" en la pantalla de ingreso.
 *  - Si se entra con un enlace de recuperacion (?recuperar=CODIGO), muestra
 *    la pantalla para elegir la contrasena nueva.
 * =========================================================================== */
(function () {
  function overlay(html) {
    var ov = document.createElement('div');
    ov.style.cssText = 'position:fixed;inset:0;background:rgba(15,20,30,.6);display:flex;align-items:center;justify-content:center;z-index:100002;padding:16px';
    ov.innerHTML = '<div style="background:#fff;border-radius:14px;max-width:400px;width:100%;padding:26px;font-family:system-ui,sans-serif;color:#1C2433">' + html + '</div>';
    document.body.appendChild(ov);
    return ov;
  }
  var estiloBtn = 'width:100%;background:#1C2433;color:#fff;border:0;padding:13px;border-radius:9px;font-size:15px;font-weight:600;cursor:pointer;margin-top:14px';
  var estiloInp = 'width:100%;padding:12px;border:1px solid #D3D7DE;border-radius:9px;font-size:16px;box-sizing:border-box';

  /* ---- Pedir el enlace ---- */
  window.apiceRecuperar = function () {
    var ov = overlay(
      '<h2 style="font-size:19px;margin:0 0 6px">Recuperar contrase&ntilde;a</h2>'
      + '<p style="font-size:14px;color:#6B7280;margin:0 0 16px">Escrib&iacute; tu correo y te mandamos un enlace para elegir una contrase&ntilde;a nueva.</p>'
      + '<label for="recEmail" style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px">Correo</label>'
      + '<input id="recEmail" type="email" autocomplete="email" style="' + estiloInp + '">'
      + '<div id="recMsg" style="font-size:13px;margin-top:12px;line-height:1.5"></div>'
      + '<button id="recOk" style="' + estiloBtn + '">Enviarme el enlace</button>'
      + '<button id="recCancel" style="width:100%;background:#EEF0F3;border:0;padding:12px;border-radius:9px;font-size:14px;cursor:pointer;margin-top:8px">Volver</button>'
    );
    var inp = ov.querySelector('#recEmail'), msg = ov.querySelector('#recMsg'), btn = ov.querySelector('#recOk');
    setTimeout(function () { try { inp.focus(); } catch (e) {} }, 60);
    ov.querySelector('#recCancel').addEventListener('click', function () { document.body.removeChild(ov); });
    async function pedir() {
      var em = (inp.value || '').trim();
      if (!em) { msg.style.color = '#B42318'; msg.textContent = 'Escrib\u00ed tu correo.'; return; }
      btn.disabled = true; btn.textContent = 'Enviando...';
      try {
        var r = await window.APICE.post('/auth/olvide', { email: em });
        msg.style.color = '#067647';
        msg.textContent = (r && r.mensaje) ? r.mensaje : 'Listo. Revis\u00e1 tu correo.';
        btn.style.display = 'none';
        ov.querySelector('#recCancel').textContent = 'Cerrar';
      } catch (e) {
        msg.style.color = '#B42318';
        msg.textContent = e.message || 'No se pudo enviar. Prob\u00e1 de nuevo en un rato.';
        btn.disabled = false; btn.textContent = 'Enviarme el enlace';
      }
    }
    btn.addEventListener('click', pedir);
    inp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') pedir(); });
  };

  /* ---- Elegir la contrasena nueva (viene del enlace del correo) ---- */
  function pantallaNuevaClave(token) {
    var ov = overlay(
      '<h2 style="font-size:19px;margin:0 0 6px">Eleg&iacute; tu contrase&ntilde;a nueva</h2>'
      + '<p style="font-size:14px;color:#6B7280;margin:0 0 16px">M&iacute;nimo 6 caracteres. Us&aacute; una que no uses en otro lado.</p>'
      + '<label for="nc1" style="display:block;font-size:12px;color:#6B7280;margin-bottom:4px">Contrase&ntilde;a nueva</label>'
      + '<input id="nc1" type="password" autocomplete="new-password" style="' + estiloInp + '">'
      + '<label for="nc2" style="display:block;font-size:12px;color:#6B7280;margin:12px 0 4px">Repetila</label>'
      + '<input id="nc2" type="password" autocomplete="new-password" style="' + estiloInp + '">'
      + '<div id="ncMsg" style="font-size:13px;margin-top:12px;line-height:1.5"></div>'
      + '<button id="ncOk" style="' + estiloBtn + '">Guardar y entrar</button>'
    );
    var a = ov.querySelector('#nc1'), b = ov.querySelector('#nc2'),
        msg = ov.querySelector('#ncMsg'), btn = ov.querySelector('#ncOk');
    setTimeout(function () { try { a.focus(); } catch (e) {} }, 60);
    btn.addEventListener('click', async function () {
      if ((a.value || '').length < 6) { msg.style.color = '#B42318'; msg.textContent = 'Tiene que tener al menos 6 caracteres.'; return; }
      if (a.value !== b.value) { msg.style.color = '#B42318'; msg.textContent = 'Las dos contrase\u00f1as no coinciden.'; return; }
      btn.disabled = true; btn.textContent = 'Guardando...';
      try {
        await window.APICE.post('/auth/restablecer', { token: token, password: a.value });
        msg.style.color = '#067647';
        msg.textContent = 'Listo. Ya pod\u00e9s ingresar con tu contrase\u00f1a nueva.';
        setTimeout(function () { location.href = location.origin + location.pathname; }, 1600);
      } catch (e) {
        msg.style.color = '#B42318';
        msg.textContent = e.message || 'El enlace no sirve o venci\u00f3. Ped\u00ed uno nuevo.';
        btn.disabled = false; btn.textContent = 'Guardar y entrar';
      }
    });
  }

  window.addEventListener('load', function () {
    /* Si viene del correo con el codigo, se muestra la pantalla directamente. */
    try {
      var m = (location.search || '').match(/[?&]recuperar=([A-Za-z0-9]+)/);
      if (m && m[1]) { setTimeout(function () { pantallaNuevaClave(m[1]); }, 400); return; }
    } catch (e) {}

    /* Si no, se agrega el enlace en la pantalla de ingreso. */
    var puesto = false;
    var t = setInterval(function () {
      if (puesto) { clearInterval(t); return; }
      try {
        var btns = Array.prototype.slice.call(document.querySelectorAll('button'));
        var entrar = null;
        for (var i = 0; i < btns.length; i++) {
          var txt = (btns[i].innerText || '').trim().toLowerCase();
          if (txt === 'ingresar' || txt.indexOf('ingresar') === 0) { entrar = btns[i]; break; }
        }
        if (!entrar || document.getElementById('lnkOlvide')) return;
        var a = document.createElement('a');
        a.id = 'lnkOlvide';
        a.href = '#';
        a.textContent = 'Olvid\u00e9 mi contrase\u00f1a';
        a.style.cssText = 'display:block;text-align:center;margin-top:14px;font-size:13.5px;color:#3C6FA6;text-decoration:underline;cursor:pointer';
        a.addEventListener('click', function (ev) { ev.preventDefault(); apiceRecuperar(); });
        entrar.parentNode.insertBefore(a, entrar.nextSibling);
        puesto = true;
      } catch (e) {}
    }, 700);
    setTimeout(function () { clearInterval(t); }, 20000);
  });
})();
