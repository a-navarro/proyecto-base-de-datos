<?php
// IA: Archivo creado con asistencia de IA para completar el punto 2.1 de la Etapa 3 IIC2413.
// Porcentaje estimado de apoyo IA: 80%.
// Tecnología utilizada: ChatGPT.
// Prompt utilizado: "Ayúdame a terminar el punto 2.1; mi index.php ya autentica usuarios y quiero continuar con las acciones de cada usuario".
// El estudiante debe revisar, adaptar y comprender completamente este código.

session_start();
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/utils.php';

const MSG_OK = 'Usuario registrado correctamente';
const MSG_ERROR = 'Usuario no se puede registrar';

function h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function requiereUsuarioAdministrativo(): void {
    $rol = $_SESSION['rol_sistema'] ?? '';

    if (!in_array($rol, ['Administrativo', 'Administrador'], true)) {
        header('Location: index.php');
        exit();
    }
}

function postValor(string $campo): string {
    return trim((string)($_POST[$campo] ?? ''));
}

function valorONull(string $valor): ?string {
    $valor = trim($valor);
    return $valor === '' ? null : $valor;
}

function alinearSecuencia(PDO $db, string $tabla, string $columna, string $secuencia): void {
    // El dump entregado deja varias secuencias en 1 aunque ya existen datos.
    // Esta llamada evita choques de PRIMARY KEY al insertar con DEFAULT.
    $sql = "
        SELECT setval(
            CAST(:secuencia AS regclass),
            COALESCE((SELECT MAX($columna) FROM $tabla), 1),
            true
        )
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':secuencia' => $secuencia]);
}

function cargarCargos(PDO $db): array {
    $stmt = $db->query('SELECT id_cargo, nombre FROM cargo ORDER BY id_cargo');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cargarSucursales(PDO $db): array {
    $stmt = $db->query('SELECT codigo_sucursal, nombre FROM sucursal ORDER BY codigo_sucursal');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function insertarUsuarioAdministrativo(PDO $db, array $datos): void {
    $tipoUsuario = strtolower($datos['tipo_usuario']);

    if (!in_array($tipoUsuario, ['administrativo', 'administrador'], true)) {
        throw new RuntimeException('Tipo de usuario no permitido.');
    }

    // Campos mínimos necesarios según NOT NULL y FKs usadas.
    $obligatorios = [
        'run_persona',
        'email_login',
        'clave_encriptada',
        'tipo_usuario',
        'nombre_completo',
        'id_cargo',
        'codigo_sucursal',
        'fecha_inicio',
    ];

    foreach ($obligatorios as $campo) {
        if (!isset($datos[$campo]) || trim((string)$datos[$campo]) === '') {
            throw new RuntimeException('Faltan campos obligatorios.');
        }
    }

    $db->beginTransaction();

    try {
        alinearSecuencia($db, 'usuario', 'id_usuario', 'public.usuario_id_usuario_seq');
        alinearSecuencia($db, 'persona_cargo', 'id_persona_cargo', 'public.persona_cargo_id_persona_cargo_seq');

        // Evita registrar un usuario duplicado antes de llegar a la PK/FK.
        $stmtExiste = $db->prepare("
            SELECT 1
            FROM usuario
            WHERE run_persona = :run_persona
               OR LOWER(email_login) = LOWER(:email_login)
            LIMIT 1
        ");
        $stmtExiste->execute([
            ':run_persona' => $datos['run_persona'],
            ':email_login' => $datos['email_login'],
        ]);

        if ($stmtExiste->fetchColumn()) {
            throw new RuntimeException('Usuario ya existe.');
        }

        $stmtPersonaExiste = $db->prepare('SELECT 1 FROM persona WHERE run = :run_persona LIMIT 1');
        $stmtPersonaExiste->execute([':run_persona' => $datos['run_persona']]);

        if ($stmtPersonaExiste->fetchColumn()) {
            throw new RuntimeException('Persona ya existe.');
        }

        $stmtPersona = $db->prepare("
            INSERT INTO persona (
                run,
                nombre_completo,
                email,
                telefono_celular,
                telefono_alternativo,
                direccion_calle,
                codigo_comuna,
                fecha_nacimiento
            ) VALUES (
                :run,
                :nombre_completo,
                :email,
                :telefono_celular,
                :telefono_alternativo,
                :direccion_calle,
                :codigo_comuna,
                :fecha_nacimiento
            )
        ");

        $codigoComuna = valorONull($datos['codigo_comuna'] ?? '');

        $stmtPersona->bindValue(':run', $datos['run_persona']);
        $stmtPersona->bindValue(':nombre_completo', $datos['nombre_completo']);
        $stmtPersona->bindValue(':email', valorONull($datos['email_personal'] ?? ''));
        $stmtPersona->bindValue(':telefono_celular', valorONull($datos['telefono_celular'] ?? ''));
        $stmtPersona->bindValue(':telefono_alternativo', valorONull($datos['telefono_alternativo'] ?? ''));
        $stmtPersona->bindValue(':direccion_calle', valorONull($datos['direccion_calle'] ?? ''));

        if ($codigoComuna === null) {
            $stmtPersona->bindValue(':codigo_comuna', null, PDO::PARAM_NULL);
        } else {
            $stmtPersona->bindValue(':codigo_comuna', (int)$codigoComuna, PDO::PARAM_INT);
        }

        $stmtPersona->bindValue(':fecha_nacimiento', valorONull($datos['fecha_nacimiento'] ?? ''));
        $stmtPersona->execute();

        $stmtUsuario = $db->prepare("
            INSERT INTO usuario (
                run_persona,
                email_login,
                clave_encriptada,
                tipo_usuario
            ) VALUES (
                :run_persona,
                :email_login,
                :clave_encriptada,
                :tipo_usuario
            )
        ");
        $stmtUsuario->execute([
            ':run_persona' => $datos['run_persona'],
            ':email_login' => $datos['email_login'],
            ':clave_encriptada' => $datos['clave_encriptada'],
            ':tipo_usuario' => $tipoUsuario,
        ]);

        $stmtCargo = $db->prepare("
            INSERT INTO persona_cargo (
                run_persona,
                id_cargo,
                codigo_sucursal,
                fecha_inicio,
                fecha_termino
            ) VALUES (
                :run_persona,
                :id_cargo,
                :codigo_sucursal,
                :fecha_inicio,
                :fecha_termino
            )
        ");
        $stmtCargo->execute([
            ':run_persona' => $datos['run_persona'],
            ':id_cargo' => (int)$datos['id_cargo'],
            ':codigo_sucursal' => $datos['codigo_sucursal'],
            ':fecha_inicio' => $datos['fecha_inicio'],
            ':fecha_termino' => valorONull($datos['fecha_termino'] ?? ''),
        ]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

requiereUsuarioAdministrativo();

$mensaje = '';
$tipoMensaje = '';
$cargos = [];
$sucursales = [];

try {
    $db = conectarBD();
    $cargos = cargarCargos($db);
    $sucursales = cargarSucursales($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $datos = [
            'run_persona' => postValor('run_persona'),
            'email_login' => postValor('email_login'),
            'clave_encriptada' => postValor('clave_encriptada'),
            'tipo_usuario' => postValor('tipo_usuario'),
            'id_cargo' => postValor('id_cargo'),
            'codigo_sucursal' => postValor('codigo_sucursal'),
            'fecha_inicio' => postValor('fecha_inicio'),
            'fecha_termino' => postValor('fecha_termino'),
            'nombre_completo' => postValor('nombre_completo'),
            'email_personal' => postValor('email_personal'),
            'telefono_celular' => postValor('telefono_celular'),
            'telefono_alternativo' => postValor('telefono_alternativo'),
            'direccion_calle' => postValor('direccion_calle'),
            'codigo_comuna' => postValor('codigo_comuna'),
            'fecha_nacimiento' => postValor('fecha_nacimiento'),
        ];

        insertarUsuarioAdministrativo($db, $datos);

        $_SESSION['flash_ok'] = MSG_OK;
        header('Location: index.php');
        exit();
    }
} catch (Throwable $e) {
    error_log('Error en registrar_usuario.php: ' . $e->getMessage());
    $mensaje = MSG_ERROR;
    $tipoMensaje = 'error';
}

function old(string $campo): string {
    return h($_POST[$campo] ?? '');
}

function selectedOld(string $campo, string $valor): string {
    return ((string)($_POST[$campo] ?? '') === $valor) ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCColo — Registrar Usuario</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; color: #222; min-height: 100vh; }
        .wrapper { max-width: 980px; margin: 0 auto; padding: 28px 16px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; background: #1a3a5c; color: #fff; padding: 16px 24px; border-radius: 10px 10px 0 0; }
        .topbar h1 { font-size: 1.35rem; }
        .topbar a { color: #fff; text-decoration: none; font-size: 0.9rem; background: #2e6da4; padding: 8px 12px; border-radius: 6px; }
        .panel { background: #fff; border-radius: 0 0 10px 10px; padding: 26px 24px; box-shadow: 0 4px 16px rgba(0,0,0,0.10); }
        .intro { color: #555; font-size: 0.9rem; margin-bottom: 20px; }
        .section { border: 1px solid #d8e0ea; border-radius: 8px; padding: 18px; margin-bottom: 18px; background: #fbfcfe; }
        .section h2 { font-size: 1rem; color: #1a3a5c; margin-bottom: 12px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap: 14px; }
        label { display: block; font-size: 0.82rem; font-weight: 700; color: #444; margin-bottom: 5px; }
        input, select { width: 100%; padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.93rem; background: #fff; }
        input:focus, select:focus { outline: none; border-color: #2e6da4; box-shadow: 0 0 0 3px rgba(46,109,164,0.14); }
        .actions { display: flex; justify-content: flex-end; gap: 10px; align-items: center; margin-top: 10px; }
        .btn { border: none; border-radius: 6px; padding: 11px 18px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.92rem; }
        .btn-primary { background: #1a3a5c; color: #fff; }
        .btn-primary:hover { background: #2e6da4; }
        .btn-secondary { background: #e8edf3; color: #1a3a5c; }
        .msg { padding: 11px 14px; border-radius: 6px; margin-bottom: 16px; font-weight: 700; }
        .msg.error { background: #fdecea; color: #c0392b; border: 1px solid #e74c3c; }
        .required { color: #c0392b; }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } .topbar { flex-direction: column; align-items: flex-start; } .actions { flex-direction: column; } .btn { width: 100%; text-align: center; } }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="topbar">
            <div>
                <h1>Registrar nuevo usuario</h1>
                <p><?= h($_SESSION['nombre_completo'] ?? '') ?> — <?= h($_SESSION['rol_sistema'] ?? '') ?></p>
            </div>
            <a href="index.php">Volver al panel</a>
        </div>

        <div class="panel">
            <p class="intro">
                Complete los datos para crear un usuario Administrativo o Administrador. La inserción se ejecuta como una sola transacción sobre persona, usuario y persona_cargo.
            </p>

            <?php if ($mensaje !== ''): ?>
                <div class="msg <?= h($tipoMensaje) ?>"><?= h($mensaje) ?></div>
            <?php endif; ?>

            <form method="POST" action="registrar_usuario.php">
                <div class="section">
                    <h2>Credenciales de acceso</h2>
                    <div class="grid">
                        <div>
                            <label for="run_persona">RUN persona <span class="required">*</span></label>
                            <input type="text" id="run_persona" name="run_persona" maxlength="12" placeholder="12345678-9" value="<?= old('run_persona') ?>" required>
                        </div>
                        <div>
                            <label for="email_login">Email de inicio de sesión <span class="required">*</span></label>
                            <input type="text" id="email_login" name="email_login" maxlength="150" placeholder="admin@dccolo.cl" value="<?= old('email_login') ?>" required>
                        </div>
                        <div>
                            <label for="clave_encriptada">Contraseña / clave_encriptada <span class="required">*</span></label>
                            <input type="password" id="clave_encriptada" name="clave_encriptada" maxlength="255" required>
                        </div>
                        <div>
                            <label for="tipo_usuario">Tipo de usuario <span class="required">*</span></label>
                            <select id="tipo_usuario" name="tipo_usuario" required>
                                <option value="">Seleccione...</option>
                                <option value="administrativo" <?= selectedOld('tipo_usuario', 'administrativo') ?>>Administrativo</option>
                                <option value="administrador" <?= selectedOld('tipo_usuario', 'administrador') ?>>Administrador</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Información laboral</h2>
                    <div class="grid">
                        <div>
                            <label for="id_cargo">Cargo <span class="required">*</span></label>
                            <select id="id_cargo" name="id_cargo" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($cargos as $cargo): ?>
                                    <?php $id = (string)$cargo['id_cargo']; ?>
                                    <option value="<?= h($id) ?>" <?= selectedOld('id_cargo', $id) ?>>
                                        <?= h($cargo['id_cargo']) ?> — <?= h($cargo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="codigo_sucursal">Sucursal <span class="required">*</span></label>
                            <select id="codigo_sucursal" name="codigo_sucursal" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($sucursales as $sucursal): ?>
                                    <?php $codigo = (string)$sucursal['codigo_sucursal']; ?>
                                    <option value="<?= h($codigo) ?>" <?= selectedOld('codigo_sucursal', $codigo) ?>>
                                        <?= h($sucursal['codigo_sucursal']) ?> — <?= h($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="fecha_inicio">Fecha de inicio <span class="required">*</span></label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= old('fecha_inicio') ?>" required>
                        </div>
                        <div>
                            <label for="fecha_termino">Fecha de término</label>
                            <input type="date" id="fecha_termino" name="fecha_termino" value="<?= old('fecha_termino') ?>">
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Datos personales</h2>
                    <div class="grid">
                        <div>
                            <label for="nombre_completo">Nombre completo <span class="required">*</span></label>
                            <input type="text" id="nombre_completo" name="nombre_completo" maxlength="150" value="<?= old('nombre_completo') ?>" required>
                        </div>
                        <div>
                            <label for="email_personal">Email personal</label>
                            <input type="text" id="email_personal" name="email_personal" maxlength="150" value="<?= old('email_personal') ?>">
                        </div>
                        <div>
                            <label for="telefono_celular">Teléfono celular</label>
                            <input type="text" id="telefono_celular" name="telefono_celular" maxlength="20" value="<?= old('telefono_celular') ?>">
                        </div>
                        <div>
                            <label for="telefono_alternativo">Teléfono alternativo</label>
                            <input type="text" id="telefono_alternativo" name="telefono_alternativo" maxlength="20" value="<?= old('telefono_alternativo') ?>">
                        </div>
                        <div>
                            <label for="fecha_nacimiento">Fecha de nacimiento</label>
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= old('fecha_nacimiento') ?>">
                        </div>
                        <div>
                            <label for="codigo_comuna">Código de comuna</label>
                            <input type="number" id="codigo_comuna" name="codigo_comuna" value="<?= old('codigo_comuna') ?>" placeholder="Ej: 13101">
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label for="direccion_calle">Dirección</label>
                            <input type="text" id="direccion_calle" name="direccion_calle" maxlength="150" value="<?= old('direccion_calle') ?>">
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <a class="btn btn-secondary" href="index.php">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Registrar usuario</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
