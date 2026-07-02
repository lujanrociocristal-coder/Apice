/* Service worker de ÁPICE.
   - Permite instalar la app en el celular/escritorio (PWA).
   - No guarda copias (estrategia: siempre red), así siempre ves la última versión.
   - Muestra notificaciones con el logo de ÁPICE (avisos urgentes) y las abre al tocarlas. */
self.addEventListener('install', function (e) { self.skipWaiting(); });
self.addEventListener('activate', function (e) { self.clients.claim(); });
self.addEventListener('fetch', function (e) { /* passthrough: el navegador maneja la red normalmente */ });

/* Al tocar una notificación, enfocar o abrir la app. */
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (cl) {
      for (var i = 0; i < cl.length; i++) { if ('focus' in cl[i]) return cl[i].focus(); }
      if (self.clients.openWindow) return self.clients.openWindow('/');
    })
  );
});

/* Notificación enviada por el servidor. Al llegar, consultamos qué mostrar. */
self.addEventListener('push', function (e) {
  e.waitUntil(
    fetch('/api/push/pendiente', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var d = (j && j.data) || {};
        return self.registration.showNotification(d.title || 'ÁPICE', {
          body: d.body || 'Tenés novedades en tu estudio.',
          icon: '/icon-192.png', badge: '/icon-192.png', data: '/'
        });
      })
      .catch(function () {
        return self.registration.showNotification('ÁPICE', {
          body: 'Tenés novedades en tu estudio.', icon: '/icon-192.png', badge: '/icon-192.png'
        });
      })
  );
});
