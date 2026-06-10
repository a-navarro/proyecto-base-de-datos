<?php
// IA: Archivo creado con asistencia de IA para completar el punto 2.2 de la Etapa 3 IIC2413.
// Porcentaje estimado de apoyo IA: 80%.
// Tecnología utilizada: ChatGPT.
// Prompt utilizado: "vamos con la siguiente opcion: administrar socios".
// El estudiante debe revisar, adaptar y comprender completamente este código.

session_start();
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/utils.php';

const MSG_OK_TITULAR = 'Socio titular registrado correctamente';
const MSG_OK_DEPENDIENTE = 'Beneficiario/Adicional registrado correctamente';
const MSG_OK_PLAN = 'Plan de pagos 2026 generado correctamente';
const MSG_OK_PAGO = 'Pago de cuota registrado correctamente';
const MSG_ERROR_GENERAL = 'No se pudo completar la operación';

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

function normalizarRun(string $run): string {
    $run = strtoupper(trim($run));
    $run = str_replace(['.', ' '], '', $run);
    return $run;
}

function valorONull($valor) {
    $valor = trim((string)$valor);
    return $valor === '' ? null : $valor;
}

function campoObligatorio(string $nombre, array $origen): string {
    $valor = trim((string)($origen[$nombre] ?? ''));
    if ($valor === '') {
        throw new RuntimeException('Campo obligatorio faltante: ' . $nombre);
    }
    return $valor;
}

function alinearSecuencia(PDO $db, string $secuencia, string $tabla, string $columna): void {
    $db->exec("SELECT setval('public.$secuencia', COALESCE((SELECT MAX($columna) FROM public.$tabla), 0) + 1, false)");
}

function prepararSecuenciasSocios(PDO $db): void {
    alinearSecuencia($db, 'socio_id_socio_seq', 'socio', 'id_socio');
    alinearSecuencia($db, 'relacion_socio_id_relacion_seq', 'relacion_socio', 'id_relacion');
    alinearSecuencia($db, 'usuario_id_usuario_seq', 'usuario', 'id_usuario');
    alinearSecuencia($db, 'pago_cuota_id_pago_cuota_seq', 'pago_cuota', 'id_pago_cuota');
}

function existeValor(PDO $db, string $sql, array $params): bool {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function obtenerTitular(PDO $db, int $idSocio): array {
    $stmt = $db->prepare("\n        SELECT s.id_socio, s.run_persona, s.codigo_sucursal_base, p.nombre_completo\n        FROM socio s\n        INNER JOIN persona p ON p.run = s.run_persona\n        WHERE s.id_socio = :id_socio\n          AND LOWER(s.tipo_socio) = 'socio_titular'\n        LIMIT 1\n    ");
    $stmt->execute([':id_socio' => $idSocio]);
    $titular = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$titular) {
        throw new RuntimeException('Socio titular inválido');
    }
    return $titular;
}

function insertarPersona(PDO $db, array $datos): void {
    $stmt = $db->prepare("\n        INSERT INTO persona (\n            run, nombre_completo, email, telefono_celular, telefono_alternativo,\n            direccion_calle, codigo_comuna, fecha_nacimiento\n        ) VALUES (\n            :run, :nombre_completo, :email, :telefono_celular, :telefono_alternativo,\n            :direccion_calle, :codigo_comuna, :fecha_nacimiento\n        )\n    ");
    $stmt->execute([
        ':run' => $datos['run'],
        ':nombre_completo' => $datos['nombre_completo'],
        ':email' => valorONull($datos['email'] ?? ''),
        ':telefono_celular' => valorONull($datos['telefono_celular'] ?? ''),
        ':telefono_alternativo' => valorONull($datos['telefono_alternativo'] ?? ''),
        ':direccion_calle' => valorONull($datos['direccion_calle'] ?? ''),
        ':codigo_comuna' => valorONull($datos['codigo_comuna'] ?? ''),
        ':fecha_nacimiento' => valorONull($datos['fecha_nacimiento'] ?? ''),
    ]);
}

function actualizarPersonaExistente(PDO $db, array $datos): void {
    $stmt = $db->prepare("\n        UPDATE persona\n        SET nombre_completo = :nombre_completo,\n            email = :email,\n            telefono_celular = :telefono_celular,\n            telefono_alternativo = :telefono_alternativo,\n            direccion_calle = :direccion_calle,\n            codigo_comuna = :codigo_comuna,\n            fecha_nacimiento = :fecha_nacimiento\n        WHERE run = :run\n    ");
    $stmt->execute([
        ':run' => $datos['run'],
        ':nombre_completo' => $datos['nombre_completo'],
        ':email' => valorONull($datos['email'] ?? ''),
        ':telefono_celular' => valorONull($datos['telefono_celular'] ?? ''),
        ':telefono_alternativo' => valorONull($datos['telefono_alternativo'] ?? ''),
        ':direccion_calle' => valorONull($datos['direccion_calle'] ?? ''),
        ':codigo_comuna' => valorONull($datos['codigo_comuna'] ?? ''),
        ':fecha_nacimiento' => valorONull($datos['fecha_nacimiento'] ?? ''),
    ]);
}

function crearSocio(PDO $db, string $run, string $tipoSocio, string $fechaInicio, ?string $fechaFin, string $codigoSucursal): int {
    $stmt = $db->prepare("\n        INSERT INTO socio (run_persona, tipo_socio, fecha_inicio, fecha_fin, codigo_sucursal_base)\n        VALUES (:run_persona, :tipo_socio, :fecha_inicio, :fecha_fin, :codigo_sucursal_base)\n        RETURNING id_socio\n    ");
    $stmt->execute([
        ':run_persona' => $run,
        ':tipo_socio' => $tipoSocio,
        ':fecha_inicio' => $fechaInicio,
        ':fecha_fin' => $fechaFin,
        ':codigo_sucursal_base' => $codigoSucursal,
    ]);
    return (int)$stmt->fetchColumn();
}

function crearMembresia(PDO $db, int $idSocio, ?int $idTitular, string $fechaInicio, string $fechaFin): void {
    $stmt = $db->prepare("\n        INSERT INTO membresia (id_socio, id_socio_titular, anio, fecha_inicio, fecha_fin)\n        VALUES (:id_socio, :id_socio_titular, 2026, :fecha_inicio, :fecha_fin)\n    ");
    $stmt->execute([
        ':id_socio' => $idSocio,
        ':id_socio_titular' => $idTitular,
        ':fecha_inicio' => $fechaInicio,
        ':fecha_fin' => $fechaFin,
    ]);
}

function generarPlanPagos2026(PDO $db, int $idSocioTitular): void {
    $stmt = $db->prepare('SELECT public.sp_crear_plan_pagos_2026(:id_socio_titular)');
    $stmt->execute([':id_socio_titular' => $idSocioTitular]);
}

function registrarTitular(PDO $db, array $post): int {
    $run = normalizarRun(campoObligatorio('run_persona', $post));
    $nombre = campoObligatorio('nombre_completo', $post);
    $emailLogin = campoObligatorio('email_login', $post);
    $clave = campoObligatorio('clave_encriptada', $post);
    $codigoSucursal = campoObligatorio('codigo_sucursal_base', $post);
    $fechaInicio = campoObligatorio('fecha_inicio', $post);
    $fechaFin = campoObligatorio('fecha_fin', $post);

    if (strlen($run) > 12) {
        throw new RuntimeException('RUN demasiado largo');
    }

    $db->beginTransaction();
    try {
        prepararSecuenciasSocios($db);

        if (existeValor($db, 'SELECT 1 FROM socio WHERE run_persona = :run LIMIT 1', [':run' => $run])) {
            throw new RuntimeException('La persona ya es socio');
        }
        if (existeValor($db, 'SELECT 1 FROM usuario WHERE email_login = :email_login LIMIT 1', [':email_login' => $emailLogin])) {
            throw new RuntimeException('Email login ya existe');
        }

        $datosPersona = [
            'run' => $run,
            'nombre_completo' => $nombre,
            'email' => $post['email'] ?? '',
            'telefono_celular' => $post['telefono_celular'] ?? '',
            'telefono_alternativo' => $post['telefono_alternativo'] ?? '',
            'direccion_calle' => $post['direccion_calle'] ?? '',
            'codigo_comuna' => $post['codigo_comuna'] ?? '',
            'fecha_nacimiento' => $post['fecha_nacimiento'] ?? '',
        ];

        if (existeValor($db, 'SELECT 1 FROM persona WHERE run = :run LIMIT 1', [':run' => $run])) {
            actualizarPersonaExistente($db, $datosPersona);
        } else {
            insertarPersona($db, $datosPersona);
        }

        $idSocio = crearSocio($db, $run, 'socio_titular', $fechaInicio, valorONull($fechaFin), $codigoSucursal);
        crearMembresia($db, $idSocio, null, $fechaInicio, $fechaFin);

        $stmtUsuario = $db->prepare("\n            INSERT INTO usuario (run_persona, email_login, clave_encriptada, tipo_usuario)\n            VALUES (:run_persona, :email_login, :clave_encriptada, 'socio')\n        ");
        $stmtUsuario->execute([
            ':run_persona' => $run,
            ':email_login' => $emailLogin,
            ':clave_encriptada' => $clave,
        ]);

        generarPlanPagos2026($db, $idSocio);

        $db->commit();
        return $idSocio;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function registrarDependiente(PDO $db, array $post): int {
    $idTitular = (int)campoObligatorio('id_socio_titular', $post);
    $tipoSocio = strtolower(campoObligatorio('tipo_socio_dependiente', $post));
    if (!in_array($tipoSocio, ['beneficiario', 'adicional'], true)) {
        throw new RuntimeException('Tipo dependiente inválido');
    }

    $run = normalizarRun(campoObligatorio('run_dependiente', $post));
    $nombre = campoObligatorio('nombre_dependiente', $post);
    $parentesco = strtolower(campoObligatorio('parentesco', $post));
    $fechaInicio = campoObligatorio('fecha_inicio_dependiente', $post);
    $fechaFin = campoObligatorio('fecha_fin_dependiente', $post);

    if (strlen($run) > 12) {
        throw new RuntimeException('RUN demasiado largo');
    }

    $db->beginTransaction();
    try {
        prepararSecuenciasSocios($db);
        $titular = obtenerTitular($db, $idTitular);

        if (existeValor($db, 'SELECT 1 FROM socio WHERE run_persona = :run LIMIT 1', [':run' => $run])) {
            throw new RuntimeException('La persona ya tiene una membresía de socio');
        }

        $datosPersona = [
            'run' => $run,
            'nombre_completo' => $nombre,
            'email' => $post['email_dependiente'] ?? '',
            'telefono_celular' => $post['telefono_celular_dependiente'] ?? '',
            'telefono_alternativo' => $post['telefono_alternativo_dependiente'] ?? '',
            'direccion_calle' => $post['direccion_calle_dependiente'] ?? '',
            'codigo_comuna' => $post['codigo_comuna_dependiente'] ?? '',
            'fecha_nacimiento' => $post['fecha_nacimiento_dependiente'] ?? '',
        ];

        if (existeValor($db, 'SELECT 1 FROM persona WHERE run = :run LIMIT 1', [':run' => $run])) {
            actualizarPersonaExistente($db, $datosPersona);
        } else {
            insertarPersona($db, $datosPersona);
        }

        $idDependiente = crearSocio(
            $db,
            $run,
            $tipoSocio,
            $fechaInicio,
            valorONull($fechaFin),
            $titular['codigo_sucursal_base']
        );

        crearMembresia($db, $idDependiente, $idTitular, $fechaInicio, $fechaFin);

        $stmtRelacion = $db->prepare("\n            INSERT INTO relacion_socio (id_socio_titular, id_socio_dependiente, parentesco)\n            VALUES (:id_socio_titular, :id_socio_dependiente, :parentesco)\n        ");
        $stmtRelacion->execute([
            ':id_socio_titular' => $idTitular,
            ':id_socio_dependiente' => $idDependiente,
            ':parentesco' => $parentesco,
        ]);
        // El trigger tr_recalcular_plan_2026_relacion_socio recalcula el plan aquí.

        $db->commit();
        return $idDependiente;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function registrarPagoCuota(PDO $db, int $idSocioTitular, string $medioPago, ?string $fechaPago): array {
    $fechaPago = $fechaPago ?: date('Y-m-d');

    $db->beginTransaction();
    try {
        obtenerTitular($db, $idSocioTitular);

        $stmt = $db->prepare("\n            SELECT id_pago_cuota, cuota_numero, monto_pagado, monto_base, monto_adicional\n            FROM pago_cuota\n            WHERE id_socio = :id_socio\n              AND fecha_pago IS NULL\n            ORDER BY id_pago_cuota ASC\n            LIMIT 1\n            FOR UPDATE\n        ");
        $stmt->execute([':id_socio' => $idSocioTitular]);
        $cuota = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cuota) {
            throw new RuntimeException('No hay cuotas impagas disponibles');
        }

        $stmtUpdate = $db->prepare("\n            UPDATE pago_cuota\n            SET fecha_pago = :fecha_pago,\n                medio_pago = :medio_pago\n            WHERE id_pago_cuota = :id_pago_cuota\n        ");
        $stmtUpdate->execute([
            ':fecha_pago' => $fechaPago,
            ':medio_pago' => $medioPago,
            ':id_pago_cuota' => $cuota['id_pago_cuota'],
        ]);

        $db->commit();
        return $cuota;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function obtenerSucursales(PDO $db): array {
    return $db->query("SELECT codigo_sucursal, nombre, precio_socio, precio_adicional FROM sucursal ORDER BY codigo_sucursal")->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerComunas(PDO $db): array {
    return $db->query("SELECT codigo_comuna, nombre FROM comuna ORDER BY nombre LIMIT 400")->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerSociosTitulares(PDO $db): array {
    $sql = "\n        WITH dependientes AS (\n            SELECT\n                r.id_socio_titular,\n                COUNT(*) AS total_dependientes,\n                SUM(CASE WHEN LOWER(sd.tipo_socio) = 'beneficiario' THEN 1 ELSE 0 END) AS beneficiarios,\n                SUM(CASE WHEN LOWER(sd.tipo_socio) = 'adicional' THEN 1 ELSE 0 END) AS adicionales\n            FROM relacion_socio r\n            INNER JOIN socio sd ON sd.id_socio = r.id_socio_dependiente\n            GROUP BY r.id_socio_titular\n        ), cuotas AS (\n            SELECT\n                id_socio,\n                COUNT(*) FILTER (WHERE fecha_pago IS NULL) AS cuotas_impagas\n            FROM pago_cuota\n            GROUP BY id_socio\n        )\n        SELECT\n            s.id_socio,\n            s.run_persona,\n            p.nombre_completo,\n            sc.nombre AS sucursal,\n            COALESCE(d.total_dependientes, 0) AS total_dependientes,\n            COALESCE(d.beneficiarios, 0) AS beneficiarios,\n            COALESCE(d.adicionales, 0) AS adicionales,\n            COALESCE(c.cuotas_impagas, 0) AS cuotas_impagas\n        FROM socio s\n        INNER JOIN persona p ON p.run = s.run_persona\n        LEFT JOIN sucursal sc ON sc.codigo_sucursal = s.codigo_sucursal_base\n        LEFT JOIN dependientes d ON d.id_socio_titular = s.id_socio\n        LEFT JOIN cuotas c ON c.id_socio = s.id_socio\n        WHERE LOWER(s.tipo_socio) = 'socio_titular'\n        ORDER BY p.nombre_completo\n        LIMIT 1000\n    ";
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerPlanSocio(PDO $db, int $idSocio): array {
    $stmt = $db->prepare("\n        SELECT id_pago_cuota, cuota_numero, fecha_pago, monto_pagado, medio_pago, monto_base, monto_adicional\n        FROM pago_cuota\n        WHERE id_socio = :id_socio\n        ORDER BY id_pago_cuota DESC\n        LIMIT 24\n    ");
    $stmt->execute([':id_socio' => $idSocio]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerDependientes(PDO $db, int $idSocio): array {
    $stmt = $db->prepare("\n        SELECT\n            sd.id_socio,\n            sd.tipo_socio,\n            rd.parentesco,\n            p.run,\n            p.nombre_completo,\n            p.email,\n            p.telefono_celular\n        FROM relacion_socio rd\n        INNER JOIN socio sd ON sd.id_socio = rd.id_socio_dependiente\n        INNER JOIN persona p ON p.run = sd.run_persona\n        WHERE rd.id_socio_titular = :id_socio\n        ORDER BY sd.tipo_socio, p.nombre_completo\n    ");
    $stmt->execute([':id_socio' => $idSocio]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

requiereUsuarioAdministrativo();

$db = conectarBD();
$mensajeOk = '';
$mensajeError = '';
$debugError = '';
$idSocioSeleccionado = isset($_GET['id_socio']) ? (int)$_GET['id_socio'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'registrar_titular') {
            $idSocioSeleccionado = registrarTitular($db, $_POST);
            $mensajeOk = MSG_OK_TITULAR;
        } elseif ($accion === 'registrar_dependiente') {
            $idSocioSeleccionado = (int)$_POST['id_socio_titular'];
            registrarDependiente($db, $_POST);
            $mensajeOk = MSG_OK_DEPENDIENTE;
        } elseif ($accion === 'generar_plan') {
            $idSocioSeleccionado = (int)campoObligatorio('id_socio_titular_plan', $_POST);
            generarPlanPagos2026($db, $idSocioSeleccionado);
            $mensajeOk = MSG_OK_PLAN;
        } elseif ($accion === 'registrar_pago') {
            $idSocioSeleccionado = (int)campoObligatorio('id_socio_titular_pago', $_POST);
            $medioPago = campoObligatorio('medio_pago', $_POST);
            $cuota = registrarPagoCuota($db, $idSocioSeleccionado, $medioPago, valorONull($_POST['fecha_pago'] ?? ''));
            $mensajeOk = MSG_OK_PAGO . ' — cuota ' . $cuota['cuota_numero'] . ', monto $' . number_format((int)$cuota['monto_pagado'], 0, ',', '.');
        }
    } catch (Throwable $e) {
        error_log('Error administrar_socios.php: ' . $e->getMessage());
        $mensajeError = MSG_ERROR_GENERAL;
        $debugError = $e->getMessage();
    }
}

$sucursales = obtenerSucursales($db);
$comunas = obtenerComunas($db);
$sociosTitulares = obtenerSociosTitulares($db);
$planSeleccionado = $idSocioSeleccionado > 0 ? obtenerPlanSocio($db, $idSocioSeleccionado) : [];
$dependientesSeleccionados = $idSocioSeleccionado > 0 ? obtenerDependientes($db, $idSocioSeleccionado) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCColo — Administrar socios</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; color: #222; margin: 0; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 28px 16px 48px; }
        .topbar { background: #1a3a5c; color: #fff; padding: 16px 22px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .topbar h1 { margin: 0; font-size: 1.35rem; }
        .topbar a { color: #fff; text-decoration: none; font-weight: bold; }
        .panel { background: #fff; padding: 22px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 16px rgba(0,0,0,0.10); }
        .section { border: 1px solid #d8e1ec; border-radius: 10px; padding: 18px; margin-bottom: 20px; background: #fbfcfe; }
        .section h2 { color: #1a3a5c; margin: 0 0 14px; font-size: 1.05rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px 16px; }
        label { display: block; font-size: 0.82rem; font-weight: bold; color: #333; margin-bottom: 5px; }
        input, select { width: 100%; padding: 9px 10px; border: 1px solid #c7d0dc; border-radius: 6px; font-size: 0.92rem; background: #fff; }
        .full { grid-column: 1 / -1; }
        .btn { display: inline-block; border: 0; border-radius: 6px; padding: 10px 14px; font-weight: bold; cursor: pointer; text-decoration: none; margin-top: 14px; }
        .btn-primary { background: #1a3a5c; color: #fff; }
        .btn-secondary { background: #2e6da4; color: #fff; }
        .btn-danger { background: #c0392b; color: #fff; }
        .msg-ok { background: #eaf7ed; color: #1e7e34; border: 1px solid #28a745; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-weight: bold; }
        .msg-error { background: #fdecea; color: #c0392b; border: 1px solid #e74c3c; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-weight: bold; }
        .hint { font-size: 0.84rem; color: #666; margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.86rem; background: #fff; }
        th, td { border-bottom: 1px solid #e4e9f0; padding: 8px 7px; text-align: left; vertical-align: top; }
        th { background: #edf3fa; color: #1a3a5c; }
        .table-wrap { overflow-x: auto; }
        .pill { display: inline-block; border-radius: 999px; padding: 2px 8px; background: #edf3fa; color: #1a3a5c; font-size: 0.78rem; font-weight: bold; }
        details { margin-top: 8px; }
        summary { cursor: pointer; color: #1a3a5c; font-weight: bold; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div>
            <h1>Administrar socios</h1>
            <div>Usuario: <?= h($_SESSION['email_login'] ?? '') ?> — Rol: <?= h($_SESSION['rol_sistema'] ?? '') ?></div>
        </div>
        <div><a href="index.php">← Volver al panel</a></div>
    </div>

    <div class="panel">
        <?php if ($mensajeOk !== ''): ?>
            <div class="msg-ok"><?= h($mensajeOk) ?></div>
        <?php endif; ?>

        <?php if ($mensajeError !== ''): ?>
            <div class="msg-error">
                <?= h($mensajeError) ?>
                <?php if ($debugError !== ''): ?>
                    <details><summary>Detalle técnico</summary><?= h($debugError) ?></details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>1. Registrar nuevo Socio Titular</h2>
            <form method="POST" action="administrar_socios.php">
                <input type="hidden" name="accion" value="registrar_titular">
                <div class="grid">
                    <div>
                        <label>RUN *</label>
                        <input name="run_persona" maxlength="12" placeholder="12345678-9" required>
                    </div>
                    <div>
                        <label>Nombre completo *</label>
                        <input name="nombre_completo" maxlength="150" required>
                    </div>
                    <div>
                        <label>Email personal</label>
                        <input type="email" name="email" maxlength="150">
                    </div>
                    <div>
                        <label>Email login *</label>
                        <input type="email" name="email_login" maxlength="150" required>
                    </div>
                    <div>
                        <label>Clave *</label>
                        <input name="clave_encriptada" maxlength="255" required>
                    </div>
                    <div>
                        <label>Sucursal base *</label>
                        <select name="codigo_sucursal_base" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?= h($s['codigo_sucursal']) ?>">
                                    <?= h($s['codigo_sucursal']) ?> — <?= h($s['nombre']) ?>
                                    ($<?= h(number_format((int)$s['precio_socio'], 0, ',', '.')) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Fecha inicio membresía *</label>
                        <input type="date" name="fecha_inicio" value="2026-01-01" required>
                    </div>
                    <div>
                        <label>Fecha fin membresía *</label>
                        <input type="date" name="fecha_fin" value="2026-12-31" required>
                    </div>
                    <div>
                        <label>Teléfono celular</label>
                        <input name="telefono_celular" maxlength="20">
                    </div>
                    <div>
                        <label>Teléfono alternativo</label>
                        <input name="telefono_alternativo" maxlength="20">
                    </div>
                    <div>
                        <label>Fecha nacimiento</label>
                        <input type="date" name="fecha_nacimiento">
                    </div>
                    <div>
                        <label>Comuna</label>
                        <select name="codigo_comuna">
                            <option value="">Sin comuna</option>
                            <?php foreach ($comunas as $c): ?>
                                <option value="<?= h($c['codigo_comuna']) ?>"><?= h($c['nombre']) ?> (<?= h($c['codigo_comuna']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="full">
                        <label>Dirección</label>
                        <input name="direccion_calle" maxlength="150">
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Registrar Socio Titular</button>
                <p class="hint">Inserta en persona, socio, membresía y usuario dentro de una transacción. Luego llama al SP para crear el plan mensual 2026.</p>
            </form>
        </div>

        <div class="section">
            <h2>2. Incorporar Beneficiario o Adicional a un Socio Titular</h2>
            <form method="POST" action="administrar_socios.php">
                <input type="hidden" name="accion" value="registrar_dependiente">
                <div class="grid">
                    <div class="full">
                        <label>Socio titular *</label>
                        <select name="id_socio_titular" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($sociosTitulares as $s): ?>
                                <option value="<?= h($s['id_socio']) ?>" <?= ((int)$s['id_socio'] === $idSocioSeleccionado ? 'selected' : '') ?>>
                                    #<?= h($s['id_socio']) ?> — <?= h($s['nombre_completo']) ?> — <?= h($s['run_persona']) ?> — <?= h($s['sucursal']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Tipo *</label>
                        <select name="tipo_socio_dependiente" required>
                            <option value="beneficiario">Beneficiario</option>
                            <option value="adicional">Adicional</option>
                        </select>
                    </div>
                    <div>
                        <label>Parentesco *</label>
                        <select name="parentesco" required>
                            <option value="hijo">hijo</option>
                            <option value="hija">hija</option>
                            <option value="conyuge">cónyuge</option>
                            <option value="padre">padre</option>
                            <option value="madre">madre</option>
                            <option value="abuelo">abuelo</option>
                            <option value="abuela">abuela</option>
                            <option value="primo">primo</option>
                            <option value="prima">prima</option>
                        </select>
                    </div>
                    <div>
                        <label>RUN dependiente *</label>
                        <input name="run_dependiente" maxlength="12" placeholder="12345678-9" required>
                    </div>
                    <div>
                        <label>Nombre dependiente *</label>
                        <input name="nombre_dependiente" maxlength="150" required>
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email_dependiente" maxlength="150">
                    </div>
                    <div>
                        <label>Teléfono celular</label>
                        <input name="telefono_celular_dependiente" maxlength="20">
                    </div>
                    <div>
                        <label>Teléfono alternativo</label>
                        <input name="telefono_alternativo_dependiente" maxlength="20">
                    </div>
                    <div>
                        <label>Fecha nacimiento</label>
                        <input type="date" name="fecha_nacimiento_dependiente">
                    </div>
                    <div>
                        <label>Fecha inicio *</label>
                        <input type="date" name="fecha_inicio_dependiente" value="2026-01-01" required>
                    </div>
                    <div>
                        <label>Fecha fin *</label>
                        <input type="date" name="fecha_fin_dependiente" value="2026-12-31" required>
                    </div>
                    <div>
                        <label>Comuna</label>
                        <select name="codigo_comuna_dependiente">
                            <option value="">Sin comuna</option>
                            <?php foreach ($comunas as $c): ?>
                                <option value="<?= h($c['codigo_comuna']) ?>"><?= h($c['nombre']) ?> (<?= h($c['codigo_comuna']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="full">
                        <label>Dirección</label>
                        <input name="direccion_calle_dependiente" maxlength="150">
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Registrar Beneficiario/Adicional</button>
                <p class="hint">Inserta persona, socio, membresía y relación socio. El trigger debe recalcular el plan 2026 automáticamente.</p>
            </form>
        </div>

        <div class="section">
            <h2>3. Generar plan de pagos 2026 y registrar pago</h2>
            <div class="grid">
                <form method="POST" action="administrar_socios.php" class="section" style="margin:0;">
                    <input type="hidden" name="accion" value="generar_plan">
                    <label>Socio titular *</label>
                    <select name="id_socio_titular_plan" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($sociosTitulares as $s): ?>
                            <option value="<?= h($s['id_socio']) ?>" <?= ((int)$s['id_socio'] === $idSocioSeleccionado ? 'selected' : '') ?>>
                                #<?= h($s['id_socio']) ?> — <?= h($s['nombre_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-secondary" type="submit">Generar/Recalcular plan 2026</button>
                    <p class="hint">Este botón llama explícitamente a <code>sp_crear_plan_pagos_2026</code>.</p>
                </form>

                <form method="POST" action="administrar_socios.php" class="section" style="margin:0;">
                    <input type="hidden" name="accion" value="registrar_pago">
                    <label>Socio titular *</label>
                    <select name="id_socio_titular_pago" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($sociosTitulares as $s): ?>
                            <option value="<?= h($s['id_socio']) ?>" <?= ((int)$s['id_socio'] === $idSocioSeleccionado ? 'selected' : '') ?>>
                                #<?= h($s['id_socio']) ?> — <?= h($s['nombre_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label>Medio de pago *</label>
                    <select name="medio_pago" required>
                        <option value="transferencia">transferencia</option>
                        <option value="tarjeta">tarjeta</option>
                        <option value="efectivo">efectivo</option>
                    </select>
                    <label>Fecha de pago</label>
                    <input type="date" name="fecha_pago" value="<?= h(date('Y-m-d')) ?>">
                    <button class="btn btn-secondary" type="submit">Pagar cuota impaga más antigua</button>
                </form>
            </div>
        </div>

        <div class="section">
            <h2>4. Socios titulares existentes</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>RUN</th>
                        <th>Nombre</th>
                        <th>Sucursal</th>
                        <th>Benef.</th>
                        <th>Adic.</th>
                        <th>Cuotas impagas</th>
                        <th>Ver</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sociosTitulares as $s): ?>
                        <tr>
                            <td><?= h($s['id_socio']) ?></td>
                            <td><?= h($s['run_persona']) ?></td>
                            <td><?= h($s['nombre_completo']) ?></td>
                            <td><?= h($s['sucursal']) ?></td>
                            <td><?= h($s['beneficiarios']) ?></td>
                            <td><?= h($s['adicionales']) ?></td>
                            <td><span class="pill"><?= h($s['cuotas_impagas']) ?></span></td>
                            <td><a href="administrar_socios.php?id_socio=<?= h($s['id_socio']) ?>">detalle</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($idSocioSeleccionado > 0): ?>
            <div class="section">
                <h2>5. Detalle del socio seleccionado #<?= h($idSocioSeleccionado) ?></h2>
                <h3>Dependientes</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr><th>ID socio</th><th>Tipo</th><th>Parentesco</th><th>RUN</th><th>Nombre</th><th>Email</th><th>Teléfono</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!$dependientesSeleccionados): ?>
                            <tr><td colspan="7">Sin dependientes registrados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($dependientesSeleccionados as $d): ?>
                            <tr>
                                <td><?= h($d['id_socio']) ?></td>
                                <td><?= h($d['tipo_socio']) ?></td>
                                <td><?= h($d['parentesco']) ?></td>
                                <td><?= h($d['run']) ?></td>
                                <td><?= h($d['nombre_completo']) ?></td>
                                <td><?= h($d['email']) ?></td>
                                <td><?= h($d['telefono_celular']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3>Últimas 24 cuotas registradas</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr><th>ID cuota</th><th>N° cuota</th><th>Fecha pago</th><th>Monto total</th><th>Base</th><th>Adicional</th><th>Medio</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!$planSeleccionado): ?>
                            <tr><td colspan="7">Sin cuotas registradas.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($planSeleccionado as $p): ?>
                            <tr>
                                <td><?= h($p['id_pago_cuota']) ?></td>
                                <td><?= h($p['cuota_numero']) ?></td>
                                <td><?= h($p['fecha_pago'] ?: 'Impaga') ?></td>
                                <td>$<?= h(number_format((int)$p['monto_pagado'], 0, ',', '.')) ?></td>
                                <td>$<?= h(number_format((int)$p['monto_base'], 0, ',', '.')) ?></td>
                                <td>$<?= h(number_format((int)$p['monto_adicional'], 0, ',', '.')) ?></td>
                                <td><?= h($p['medio_pago'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
