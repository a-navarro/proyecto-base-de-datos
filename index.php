<?php
// IA: Este archivo fue ajustado con asistencia de IA para la Etapa 3 de IIC2413.
// Porcentaje estimado de apoyo IA: 80%.
// Tecnología utilizada: ChatGPT.
// Prompt utilizado: "ajusta este index.php para que funcione con la base de datos del dump entregado".
// El estudiante debe revisar, adaptar y comprender completamente este código.
//
// Esquema usado desde dumpE3_servidor.sql:
// - usuario(id_usuario, run_persona, email_login, clave_encriptada, tipo_usuario)
// - persona(run, nombre_completo, email, telefono_celular, ...)
// - socio(id_socio, run_persona, tipo_socio, fecha_inicio, fecha_fin, codigo_sucursal_base)
//
// Regla del enunciado:
// - Pueden ingresar: Administrativos, Administradores y Socios Titulares.
// - En el dump, usuario.tipo_usuario contiene principalmente: 'administrativo' y 'socio'.
// - Los socios titulares se reconocen en socio.tipo_socio = 'socio_titular'.

session_start();
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/utils.php';

const LOG_FILE = __DIR__ . '/dccolo.log';
const LOGIN_ERROR_MESSAGE = 'Usuario o Clave errónea';

function h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Registra cada intento de ingreso.
 * Formato exigido: <dia-hora> ACCESO <usuario> Exitoso/Fallido
 */
function registrarLogAcceso(string $usuarioLogin, string $estado): void {
    $timestamp = date('Y-m-d H:i:s');
    $usuarioSeguro = $usuarioLogin !== '' ? $usuarioLogin : '(vacío)';
    $linea = $timestamp . ' ACCESO ' . $usuarioSeguro . ' ' . $estado . PHP_EOL;
    file_put_contents(LOG_FILE, $linea, FILE_APPEND | LOCK_EX);
}

/**
 * Determina el rol autorizado para mostrar en la sesión.
 */
function obtenerRolSistema(array $fila): ?string {
    $tipoUsuario = strtolower(trim((string)($fila['tipo_usuario'] ?? '')));
    $tipoSocio = strtolower(trim((string)($fila['tipo_socio'] ?? '')));

    if ($tipoUsuario === 'administrador') {
        return 'Administrador';
    }

    if ($tipoUsuario === 'administrativo') {
        return 'Administrativo';
    }

    if ($tipoUsuario === 'socio' && $tipoSocio === 'socio_titular') {
        return 'SocioTitular';
    }

    return null;
}

// Logout.
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit();
}

$errorLogin = '';

// Procesamiento del formulario de login.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['usuario'] ?? '');
    $claveInput = trim($_POST['clave'] ?? '');

    if ($loginInput === '' || $claveInput === '') {
        $errorLogin = LOGIN_ERROR_MESSAGE;
        registrarLogAcceso($loginInput, 'Fallido');
    } else {
        try {
            $db = conectarBD();

            /**
             * Consulta adaptada al dump real.
             *
             * Administrativo/Administrador:
             *   usuario.tipo_usuario IN ('administrativo', 'administrador')
             *
             * Socio Titular:
             *   usuario.tipo_usuario = 'socio'
             *   y existe fila en socio con tipo_socio = 'socio_titular'
             *
             * Nota: no se filtra por fecha_fin, porque en el dump los socios pueden tener
             * fechas 2025 y el requisito de login solo pide que sea Socio Titular.
             */
            $sql = "
                SELECT
                    u.id_usuario,
                    u.run_persona,
                    u.email_login,
                    u.tipo_usuario,
                    p.nombre_completo,
                    p.email AS email_persona,
                    s.id_socio,
                    s.tipo_socio
                FROM usuario u
                INNER JOIN persona p
                    ON p.run = u.run_persona
                LEFT JOIN socio s
                    ON s.run_persona = u.run_persona
                    AND LOWER(s.tipo_socio) = 'socio_titular'
                WHERE
                    u.email_login = :login
                    AND u.clave_encriptada = :clave
                    AND (
                        LOWER(u.tipo_usuario) IN ('administrativo', 'administrador')
                        OR (
                            LOWER(u.tipo_usuario) = 'socio'
                            AND s.id_socio IS NOT NULL
                        )
                    )
                LIMIT 1
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':login' => $loginInput,
                ':clave' => $claveInput,
            ]);

            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                $rolSistema = obtenerRolSistema($fila);

                if ($rolSistema !== null) {
                    session_regenerate_id(true);

                    $_SESSION['id_usuario'] = $fila['id_usuario'];
                    $_SESSION['run_persona'] = $fila['run_persona'];
                    $_SESSION['email_login'] = $fila['email_login'];
                    $_SESSION['nombre_completo'] = $fila['nombre_completo'];
                    $_SESSION['tipo_usuario_bd'] = $fila['tipo_usuario'];
                    $_SESSION['id_socio'] = $fila['id_socio'] ?? null;
                    $_SESSION['tipo_socio'] = $fila['tipo_socio'] ?? null;
                    $_SESSION['rol_sistema'] = $rolSistema;

                    registrarLogAcceso($loginInput, 'Exitoso');

                    header('Location: index.php');
                    exit();
                }
            }

            $errorLogin = LOGIN_ERROR_MESSAGE;
            registrarLogAcceso($loginInput, 'Fallido');
        } catch (PDOException $e) {
            // No mostrar detalles internos de BD al usuario.
            error_log('Error de login en index.php: ' . $e->getMessage());
            $errorLogin = LOGIN_ERROR_MESSAGE;
            registrarLogAcceso($loginInput, 'Fallido');
        }
    }
}

$haySesion = isset($_SESSION['id_usuario'], $_SESSION['rol_sistema']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCColo — Sistema de Gestión</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            color: #222;
            min-height: 100vh;
        }

        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a3a5c 0%, #2e6da4 100%);
            padding: 16px;
        }

        .login-card {
            background: #fff;
            border-radius: 12px;
            padding: 40px 36px;
            width: 100%;
            max-width: 390px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
        }

        .login-card h1 {
            font-size: 1.6rem;
            color: #1a3a5c;
            margin-bottom: 4px;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: #666;
            font-size: 0.88rem;
            margin-bottom: 28px;
        }

        label {
            display: block;
            font-size: 0.84rem;
            font-weight: 700;
            color: #444;
            margin-bottom: 5px;
            margin-top: 16px;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        input:focus {
            outline: none;
            border-color: #2e6da4;
            box-shadow: 0 0 0 3px rgba(46,109,164,0.15);
        }

        .btn-login {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 24px;
            background: #1a3a5c;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-login:hover { background: #2e6da4; }

        .error-msg {
            background: #fdecea;
            color: #c0392b;
            border: 1px solid #e74c3c;
            border-radius: 6px;
            padding: 10px 14px;
            margin-top: 16px;
            font-size: 0.88rem;
            text-align: center;
            font-weight: 700;
        }

        .main-wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 32px 16px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            background: #1a3a5c;
            color: #fff;
            padding: 16px 24px;
            border-radius: 10px 10px 0 0;
        }

        .topbar h1 { font-size: 1.35rem; }
        .user-info { font-size: 0.9rem; color: #d3e4f5; margin-top: 4px; }
        .user-info strong { color: #fff; }

        .btn-logout {
            background: #e74c3c;
            color: #fff;
            border: none;
            padding: 9px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.86rem;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-logout:hover { background: #c0392b; }

        .menu-panel {
            background: #fff;
            border-radius: 0 0 10px 10px;
            padding: 28px 24px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.10);
        }

        .menu-panel h2 {
            font-size: 1.05rem;
            color: #1a3a5c;
            margin-bottom: 18px;
            border-bottom: 2px solid #e8edf3;
            padding-bottom: 8px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 14px;
        }

        .menu-item {
            display: block;
            background: #f5f8fc;
            border: 1px solid #d0dcea;
            border-radius: 8px;
            padding: 18px 16px;
            text-decoration: none;
            color: #1a3a5c;
            font-weight: 700;
            font-size: 0.94rem;
        }

        .menu-item:hover {
            background: #ddeaf8;
            border-color: #2e6da4;
        }

        .icon {
            font-size: 1.45rem;
            display: block;
            margin-bottom: 8px;
        }

        .badge-rol {
            display: inline-block;
            background: #2e6da4;
            color: #fff;
            border-radius: 20px;
            padding: 3px 12px;
            font-size: 0.78rem;
            margin-left: 8px;
            font-weight: 700;
        }

        .nota {
            margin-top: 18px;
            color: #666;
            font-size: 0.86rem;
        }

        @media (max-width: 650px) {
            .topbar { flex-direction: column; align-items: flex-start; }
            .btn-logout { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

<?php if ($haySesion): ?>
    <?php
        $rolSistema = $_SESSION['rol_sistema'];
        $esAdmin = in_array($rolSistema, ['Administrativo', 'Administrador'], true);
        $esSocioTitular = $rolSistema === 'SocioTitular';
    ?>

    <div class="main-wrapper">
        <div class="topbar">
            <div>
                <h1>DCColo</h1>
                <div class="user-info">
                    Conectado como
                    <strong><?= h($_SESSION['email_login']) ?></strong> —
                    <?= h($_SESSION['nombre_completo']) ?>
                    <span class="badge-rol"><?= h($rolSistema) ?></span>
                </div>
            </div>
            <a href="index.php?logout=1" class="btn-logout">Cerrar sesión</a>
        </div>

        <div class="menu-panel">
            <?php if ($esAdmin): ?>
                <h2>Panel Administrativo</h2>
                <div class="menu-grid">
                    <a href="registrar_usuario.php" class="menu-item">
                        <span class="icon">👤</span>
                        Registrar nuevo usuario
                    </a>
                    <a href="administrar_socios.php" class="menu-item">
                        <span class="icon">👥</span>
                        Administrar socios
                    </a>
                    <a href="consultas_e2.php" class="menu-item">
                        <span class="icon">🔎</span>
                        Consultas E2
                    </a>
                    <a href="consulta_libre.php" class="menu-item">
                        <span class="icon">📄</span>
                        Consulta inestructurada
                    </a>
                    <a href="arrendar_cancha.php" class="menu-item">
                        <span class="icon">🏟️</span>
                        Arrendar cancha
                    </a>
                    <a href="crear_evento.php" class="menu-item">
                        <span class="icon">🎉</span>
                        Crear evento
                    </a>
                </div>

            <?php elseif ($esSocioTitular): ?>
                <h2>Portal del Socio Titular</h2>
                <div class="menu-grid">
                    <a href="arrendar_cancha.php" class="menu-item">
                        <span class="icon">🏟️</span>
                        Arrendar cancha
                    </a>
                    <a href="crear_evento.php" class="menu-item">
                        <span class="icon">🎉</span>
                        Crear evento
                    </a>
                </div>

            <?php else: ?>
                <p class="error-msg">Tipo de usuario no autorizado.</p>
            <?php endif; ?>

            <p class="nota">
                Este menú solo enlaza a las páginas esperadas. Cada página posterior debe verificar la sesión antes de mostrar contenido.
            </p>
        </div>
    </div>

<?php else: ?>
    <div class="login-wrapper">
        <div class="login-card">
            <h1>DCColo</h1>
            <p class="subtitle">Sistema de Gestión del Club</p>

            <form method="POST" action="index.php">
                <label for="usuario">Usuario</label>
                <input
                    type="text"
                    id="usuario"
                    name="usuario"
                    placeholder="email_login"
                    value="<?= h($_POST['usuario'] ?? '') ?>"
                    autocomplete="username"
                    required
                >

                <label for="clave">Clave</label>
                <input
                    type="password"
                    id="clave"
                    name="clave"
                    placeholder="clave_encriptada"
                    autocomplete="current-password"
                    required
                >

                <button type="submit" class="btn-login">Ingresar</button>

                <?php if ($errorLogin !== ''): ?>
                    <div class="error-msg"><?= h($errorLogin) ?></div>
                <?php endif; ?>
            </form>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
