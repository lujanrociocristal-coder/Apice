<?php
/* ============================================================================
 *  CREAR EL USUARIO ADMINISTRADOR DE FORMA AUTOMÁTICA
 *
 *  CÓMO SE USA:
 *    1) Abrí en el navegador: https://tu-dominio.com/api/crear-admin.php
 *    2) El script creará el estudio y el usuario administrador con las credenciales solicitadas.
 *    3) MUY IMPORTANTE: cuando termine, BORRÁ este archivo del servidor.
 * ========================================================================== */

require __DIR__ . '/lib/respond.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';

$email   = 'lujanrociocristal@gmail.com';
$nombre  = 'Rocio Cristal Lujan';
$estudio = 'Estudio Luján & Breppe';
$pass    = '290893Ro';

try {
    $pdo = db();
    $ya = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
    $ya->execute([$email]);
    $count = (int)$ya->fetchColumn();

    if ($count > 0) {
        echo "<h3>El usuario ya existe</h3>";
        echo "<p>El usuario <strong>$email</strong> ya está registrado en la base de datos.</p>";
        echo "<p>Por seguridad, borrá este archivo (<code>crear-admin.php</code>) del servidor.</p>";
    } else {
        $pdo->beginTransaction();
        
        // Verificar si el estudio ya existe o crearlo
        $est = $pdo->prepare('SELECT id FROM estudios WHERE nombre = ?');
        $est->execute([$estudio]);
        $eid = $est->fetchColumn();
        
        if (!$eid) {
            $pdo->prepare('INSERT INTO estudios (nombre) VALUES (?)')->execute([$estudio]);
            $eid = (int)$pdo->lastInsertId();
        }
        
        // Insertar el usuario administrador
        $pdo->prepare('INSERT INTO usuarios (estudio_id, nombre, email, password_hash, rol) VALUES (?,?,?,?,?)')
            ->execute([$eid, $nombre, $email, hash_password($pass), 'profesional']);
            
        // Copiar los feriados globales para este estudio
        $pdo->prepare('INSERT INTO feriados (estudio_id, fecha, anual, nombre, tipo)
                       SELECT ?, fecha, anual, nombre, tipo FROM feriados WHERE estudio_id IS NULL')
            ->execute([$eid]);
            
        $pdo->commit();
        echo "<h3>¡Usuario Creado Exitosamente!</h3>";
        echo "<p>Se ha creado el estudio <strong>\"$estudio\"</strong> y el usuario administrador:</p>";
        echo "<ul>";
        echo "<li><strong>Usuario / Email:</strong> $email</li>";
        echo "<li><strong>Rol:</strong> profesional (Acceso completo)</li>";
        echo "</ul>";
        echo "<p><strong>¡MUY IMPORTANTE!</strong> Ahora borrá el archivo <code>crear-admin.php</code> de tu servidor por motivos de seguridad.</p>";
    }
} catch (Exception $e) {
    echo "<h3>Error al crear el usuario</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
