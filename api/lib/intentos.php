<?php
/* ============================================================================
 *  LIMITE DE INTENTOS DE INGRESO  (v46)
 *
 *  Para que sirve: evitar que alguien pruebe contrasenas una tras otra hasta
 *  acertar (ataque de fuerza bruta).
 *
 *  Como funciona, a proposito:
 *   - Se cuentan los intentos FALLIDOS de los ultimos 15 minutos.
 *   - A partir del 5to fallo, se pide esperar. NO es un bloqueo permanente:
 *     se libera solo con el paso del tiempo. Esto es clave, porque un bloqueo
 *     definitivo permitiria que un tercero deje afuera a una persona a
 *     proposito fallando adrede.
 *   - Al ingresar bien, el contador se borra.
 *   - Si algo falla en este control, NO se bloquea el ingreso: preferimos
 *     que la app siga funcionando antes que dejar a alguien afuera.
 * ========================================================================== */

function intentos_max()     { return 5; }   // fallos permitidos
function intentos_ventana() { return 15; }  // minutos

function intentos_ip() {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  return substr((string)$ip, 0, 45);
}

function intentos_tabla($pdo) {
  $pdo->exec("CREATE TABLE IF NOT EXISTS intentos_login (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_fecha (email, creado_en),
    KEY idx_ip_fecha (ip, creado_en)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/* Devuelve los MINUTOS que faltan para poder reintentar, o 0 si puede pasar. */
function intentos_espera($email) {
  try {
    $pdo = db();
    intentos_tabla($pdo);
    $st = $pdo->prepare('SELECT COUNT(*) AS n, MAX(creado_en) AS ultimo
                         FROM intentos_login
                         WHERE email = ? AND ip = ? AND creado_en > (NOW() - INTERVAL ? MINUTE)');
    $st->execute([$email, intentos_ip(), intentos_ventana()]);
    $r = $st->fetch();
    if (!$r || (int)$r['n'] < intentos_max()) return 0;
    $pasados = (time() - strtotime($r['ultimo'])) / 60;
    $faltan  = (int)ceil(intentos_ventana() - $pasados);
    return $faltan > 0 ? $faltan : 0;
  } catch (Throwable $e) {
    return 0; // ante cualquier problema, no bloqueamos a nadie
  }
}

function intentos_registrar_fallo($email) {
  try {
    $pdo = db();
    intentos_tabla($pdo);
    $pdo->prepare('INSERT INTO intentos_login (email, ip) VALUES (?, ?)')
        ->execute([$email, intentos_ip()]);
    // Limpieza de registros viejos (no crece indefinidamente).
    $pdo->exec('DELETE FROM intentos_login WHERE creado_en < (NOW() - INTERVAL 1 DAY)');
  } catch (Throwable $e) { /* no bloquea el ingreso */ }
}

function intentos_limpiar($email) {
  try {
    db()->prepare('DELETE FROM intentos_login WHERE email = ? AND ip = ?')
        ->execute([$email, intentos_ip()]);
  } catch (Throwable $e) { /* silencioso */ }
}
