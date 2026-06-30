/* Service worker mínimo de ÁPICE.
   Solo habilita que la app se pueda "instalar" en el celular/escritorio.
   No guarda copias (estrategia: siempre red), así siempre ves la última versión. */
self.addEventListener('install', function (e) { self.skipWaiting(); });
self.addEventListener('activate', function (e) { self.clients.claim(); });
self.addEventListener('fetch', function (e) { /* passthrough: el navegador maneja la red normalmente */ });
