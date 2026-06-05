<?php
// IA: Código de apoyo generado con ChatGPT para implementar login PHP + PostgreSQL.
// Uso estimado: 70% estructura base, 30% debe ser adaptado y verificado por el estudiante.
// Prompt: "creame un index php que maneje el login de usuarios mediante consultas a la base de datos segun el enunciado"

session_start();
date_default_timezone_set('America/Santiago');

require_once 'utils.php';

$error = "";

/**
 * Registra cada intento de acceso en un archivo de log.
 * Formato exigido:
 * <dia-hora> ACCESO <usuario> Exitoso/Fallido
 */
function registrarLogAcceso($usuario, $estado) {
    $fecha = date('Y-m-d H:i:s');
    $linea = "$fecha ACCESO $usuario $estado" . PHP_EOL;

    file_put_contents(__DIR__ . '/dccolo.log', $linea, FILE_APPEND | LOCK_EX);
}

/**
 * Cierra sesión.
 */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

/**
 * Procesa login.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email_login'] ?? '');
    $clave = trim($_POST['clave'] ?? '');

    if ($email === '' || $clave === '') {
        $error = "Usuario o Clave errónea";
        registrarLogAcceso($email !== '' ? $email : 'SIN_USUARIO', 'Fallido');
    } else {
        try {
            $db = conectarBD();

            /*
             * Validación según enunciado:
             * - Se usa Usuario + Persona.
             * - Administrativo y Administrador pueden entrar.
             * - Socio solo entra si además es socio_titular en tabla socio.
             *
             * En el dump, usuario.tipo_usuario aparece como:
             * - administrativo
             * - socio
             *
             * Y socio.tipo_socio aparece como:
             * - socio_titular
             * - beneficiario
             * - adicional
             */
            $sql = "
                SELECT
                    u.id_usuario,
                    u.email_login,
                    u.tipo_usuario,
                    p.run,
                    p.nombre_completo,
                    s.id_socio,
                    s.tipo_socio
                FROM usuario u
                INNER JOIN persona p
                    ON p.run = u.run_persona
                LEFT JOIN socio s
                    ON s.run_persona = p.run
                    AND s.tipo_socio = 'socio_titular'
                    AND s.fecha_fin IS NULL
                WHERE
                    u.email_login = :email
                    AND u.clave_encriptada = :clave
                    AND (
                        LOWER(u.tipo_usuario) IN ('administrativo', 'administrador')
                        OR (
                            LOWER(u.tipo_usuario) IN ('socio', 'socio_titular', 'socio titular')
                            AND s.id_socio IS NOT NULL
                        )
                    )
                LIMIT 1
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':email' => $email,
                ':clave' => $clave
            ]);

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                registrarLogAcceso($email, 'Exitoso');

                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['email_login'] = $usuario['email_login'];
                $_SESSION['run'] = $usuario['run'];
                $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
                $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
                $_SESSION['tipo_socio'] = $usuario['tipo_socio'];

                header("Location: index.php");
                exit();
            } else {
                registrarLogAcceso($email, 'Fallido');
                $error = "Usuario o Clave errónea";
            }

        } catch (Exception $e) {
            registrarLogAcceso($email, 'Fallido');
            $error = "Usuario o Clave errónea";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>DCColo - Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .contenedor {
            width: 420px;
            margin: 80px auto;
            background: white;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        h1 {
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
        }

        button {
            margin-top: 20px;
            width: 100%;
            padding: 10px;
            background: #003366;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background: #0055aa;
        }

        .error {
            color: red;
            text-align: center;
            margin-top: 15px;
            font-weight: bold;
        }

        .menu {
            width: 600px;
            margin: 80px auto;
            background: white;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .menu a {
            display: block;
            margin: 12px 0;
        }
    </style>
</head>
<body>

<?php if (isset($_SESSION['id_usuario'])): ?>

    <div class="menu">
        <h1>Página principal DCColo</h1>

        <p>
            Bienvenido,
            <strong><?= htmlspecialchars($_SESSION['nombre_completo']) ?></strong>
        </p>

        <p>
            Perfil:
            <strong><?= htmlspecialchars($_SESSION['tipo_usuario']) ?></strong>
            <?php if (!empty($_SESSION['tipo_socio'])): ?>
                / <?= htmlspecialchars($_SESSION['tipo_socio']) ?>
            <?php endif; ?>
        </p>

        <hr>

        <h3>Menú principal</h3>

        <?php if ($_SESSION['tipo_usuario'] === 'administrativo' || $_SESSION['tipo_usuario'] === 'administrador'): ?>
            <a href="registrar_usuario.php">Registrar nuevo usuario</a>
            <a href="socios.php">Administrar socios</a>
            <a href="consultas.php">Consultas E2</a>
            <a href="consulta_libre.php">Consulta inestructurada</a>
        <?php endif; ?>

        <a href="arriendo_canchas.php">Arrendar cancha</a>
        <a href="eventos.php">Crear evento</a>

        <hr>

        <a href="index.php?logout=1">Cerrar sesión</a>
    </div>

<?php else: ?>

    <div class="contenedor">
        <h1>Ingreso DCColo</h1>

        <form method="POST" action="index.php">
            <label for="email_login">Usuario</label>
            <input
                type="email"
                id="email_login"
                name="email_login"
                required
                placeholder="correo@ejemplo.cl"
            >

            <label for="clave">Clave</label>
            <input
                type="password"
                id="clave"
                name="clave"
                required
                placeholder="Ingrese su clave"
            >

            <button type="submit">Ingresar</button>
        </form>

        <?php if ($error !== ''): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

</body>
</html>