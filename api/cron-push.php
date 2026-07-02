<?php
/* ============================================================================
 *  TAREA DIARIA (Cron) — envía notificaciones al celular con la app cerrada.
 *
 *  Lo ejecuta Hostinger una vez por día. Revisa qué estudios tienen audiencias
 *  o citas para HOY o MAÑANA y les manda un push a sus dispositivos. El celular,
 *  al recibirlo, muestra el aviso con el logo de ÁPICE.
 *
 *  Comando en Cron (PHP):  <ruta>/public_html/api/cron-push.php
 * ========================================================================== */

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/push.php';

$total = 0;
try {
  $pdo = db();
  // Asegurar la tabla (por si el cron corre antes de la primera suscripción).
  $pdo->exec("CREATE TABLE IF NOT EXISTS push_subs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT, usuario_id INT UNSIGNED NOT NULL,
    estudio_id INT UNSIGNED NOT NULL, endpoint VARCHAR(500) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id),
    UNIQUE KEY uq_ep (endpoint(255)), KEY idx_est (estudio_id), KEY idx_usr (usuario_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $hoy = date('Y-m-d');
  $man = date('Y-m-d', strtotime('+1 day'));

  // Estudios con audiencias/citas para hoy o mañana.
  $st = $pdo->prepare("SELECT DISTINCT estudio_id FROM audiencias WHERE fecha IN (?, ?)");
  $st->execute([$hoy, $man]);
  $estudios = $st->fetchAll(PDO::FETCH_COLUMN);

  foreach ($estudios as $eid) {
    $s2 = $pdo->prepare('SELECT endpoint FROM push_subs WHERE estudio_id = ?');
    $s2->execute([(int)$eid]);
    foreach ($s2->fetchAll(PDO::FETCH_COLUMN) as $ep) {
      $code = push_send($ep);
      if ($code === 404 || $code === 410) {
        // Suscripción vencida: la borramos.
        $pdo->prepare('DELETE FROM push_subs WHERE endpoint = ?')->execute([$ep]);
      } else {
        $total++;
      }
    }
  }
} catch (Throwable $e) {
  echo 'Error: ' . $e->getMessage() . "\n";
}
echo "Push enviados: $total\n";
