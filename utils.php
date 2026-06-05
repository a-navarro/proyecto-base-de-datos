<?php
function conectarBD() {
    $host = 'stonebraker.ing.uc.cl'; // Cambiar al servidor stonebraker.ing.uc.cl si se quiere usar el servidor remoto (default: 'localhost')
    $dbname = 'antonio.navarro.e3'; // usuariouc.e3
    $usuario = 'antonio.navarro.e3'; // usuariouc.e3
    $clave = '25663259'; // Número de alumno

    try {
        $db = new PDO("pgsql:host=$host;dbname=$dbname", $usuario, $clave);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        echo "Error de conexión: " . $e->getMessage();
        exit();
    }
}
?>
