<?php
// IA: Archivo creado con asistencia de IA para completar el punto 2.3 de la Etapa 3 IIC2413.
// Porcentaje estimado de apoyo IA: 80%.
// Tecnología utilizada: ChatGPT.
// Prompt utilizado: "Sigamos con el punto 2.3: Arriendo de canchas".
// El estudiante debe revisar, adaptar y comprender completamente este código.

session_start();
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/utils.php';

const MSG_OK_RESERVA = 'Cancha arrendada correctamente';
const MSG_ERROR_RESERVA = 'No se pudo arrendar la cancha';

function h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function requiereUsuarioAutorizado(): void {
    $rol = $_SESSION['rol_sistema'] ?? '';
    if (!in_array($rol, ['Administrativo', 'Administrador', 'SocioTitular'], true)) {
        header('Location: index.php');
        exit();
    }
}

function esUsuarioAdmin(): bool {
    $rol = $_SESSION['rol_sistema'] ?? '';
    return in_array($rol, ['Administrativo', 'Administrador'], true);
}

function esUsuarioSocioTitular(): bool {
    return ($_SESSION['rol_sistema'] ?? '') === 'SocioTitular';
}

function diaSemanaEspanol(DateTimeInterface $fecha): string {
    $mapa = [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miercoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sabado',
        7 => 'domingo',
    ];
    return $mapa[(int)$fecha->format('N')];
}

function validarFecha(string $fecha): string {
    $fecha = trim($fecha);
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
    if (!$dt || $dt->format('Y-m-d') !== $fecha) {
        throw new RuntimeException('Fecha inválida');
    }
    return $fecha;
}

function alinearSecuencia(PDO $db, string $secuencia, string $tabla, string $columna): void {
    $db->exec("SELECT setval('public.$secuencia', COALESCE((SELECT MAX($columna) FROM public.$tabla), 0) + 1, false)");
}

function obtenerCanchas(PDO $db): array {
    $stmt = $db->query("\n        SELECT\n            l.codigo_lugar,\n            l.nombre AS nombre_lugar,\n            l.tipo_lugar,\n            l.capacidad,\n            s.codigo_sucursal,\n            s.nombre AS nombre_sucursal\n        FROM public.lugar l\n        INNER JOIN public.sucursal s\n            ON s.codigo_sucursal = l.codigo_sucursal\n        WHERE LOWER(l.tipo_lugar) IN ('cancha_aire_libre', 'cancha_techada')\n        ORDER BY s.codigo_sucursal, l.nombre\n    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerFechaInicialCancha(PDO $db, string $codigoLugar): string {
    $stmt = $db->prepare("\n        SELECT COALESCE(\n            (\n                SELECT MIN(fecha_inicio)\n                FROM public.precio_lugar\n                WHERE codigo_lugar = :codigo_lugar_futuro\n                  AND LOWER(tipo_precio) = 'hora'\n                  AND fecha_inicio >= CURRENT_DATE\n            ),\n            (\n                SELECT MIN(fecha_inicio)\n                FROM public.precio_lugar\n                WHERE codigo_lugar = :codigo_lugar_any\n                  AND LOWER(tipo_precio) = 'hora'\n            ),\n            CURRENT_DATE\n        )::date AS fecha_inicial\n    ");
    $stmt->execute([
        ':codigo_lugar_futuro' => $codigoLugar,
        ':codigo_lugar_any' => $codigoLugar,
    ]);
    return (string)$stmt->fetchColumn();
}

function obtenerHorariosDisponibles(PDO $db, string $codigoLugar, string $fecha): array {
    $fecha = validarFecha($fecha);
    $diaSemana = diaSemanaEspanol(new DateTimeImmutable($fecha));

    $stmt = $db->prepare("\n        SELECT DISTINCT ON (pl.hora_inicio, pl.hora_termino)\n            pl.id_precio,\n            pl.codigo_lugar,\n            pl.hora_inicio,\n            pl.hora_termino,\n            pl.monto\n        FROM public.precio_lugar pl\n        WHERE pl.codigo_lugar = :codigo_lugar\n          AND LOWER(pl.tipo_precio) = 'hora'\n          AND LOWER(pl.dia_semana) = :dia_semana\n          AND CAST(:fecha_precio AS date) BETWEEN pl.fecha_inicio AND pl.fecha_fin\n          AND NOT EXISTS (\n              SELECT 1\n              FROM public.reserva r\n              WHERE r.codigo_lugar = pl.codigo_lugar\n                AND LOWER(r.estado) IN ('reservada', 'ejecutada')\n                AND (CAST(:fecha_inicio_reserva AS date) + pl.hora_inicio) < r.fecha_fin\n                AND (CAST(:fecha_fin_reserva AS date) + pl.hora_termino) > r.fecha_inicio\n          )\n        ORDER BY pl.hora_inicio, pl.hora_termino, pl.fecha_inicio DESC, pl.id_precio DESC\n    ");
    $stmt->execute([
        ':codigo_lugar' => $codigoLugar,
        ':dia_semana' => $diaSemana,
        ':fecha_precio' => $fecha,
        ':fecha_inicio_reserva' => $fecha,
        ':fecha_fin_reserva' => $fecha,
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function contarHorariosDisponibles(PDO $db, string $codigoLugar, string $fecha): int {
    return count(obtenerHorariosDisponibles($db, $codigoLugar, $fecha));
}

function obtenerCalendarioDisponibilidad(PDO $db, string $codigoLugar, string $fechaDesde, int $dias = 14): array {
    $fechaDesde = validarFecha($fechaDesde);
    $inicio = new DateTimeImmutable($fechaDesde);
    $calendario = [];

    for ($i = 0; $i < $dias; $i++) {
        $fecha = $inicio->modify('+' . $i . ' days');
        $fechaTexto = $fecha->format('Y-m-d');
        $calendario[] = [
            'fecha' => $fechaTexto,
            'dia' => diaSemanaEspanol($fecha),
            'cupos' => contarHorariosDisponibles($db, $codigoLugar, $fechaTexto),
        ];
    }

    return $calendario;
}

function obtenerSociosTitularesSinDeuda(PDO $db): array {
    $stmt = $db->query("\n        SELECT\n            s.id_socio,\n            s.run_persona,\n            p.nombre_completo,\n            p.email,\n            p.telefono_celular,\n            su.nombre AS nombre_sucursal\n        FROM public.socio s\n        INNER JOIN public.persona p\n            ON p.run = s.run_persona\n        LEFT JOIN public.sucursal su\n            ON su.codigo_sucursal = s.codigo_sucursal_base\n        WHERE LOWER(s.tipo_socio) = 'socio_titular'\n          AND NOT EXISTS (\n              SELECT 1\n              FROM public.pago_cuota pc\n              WHERE pc.id_socio = s.id_socio\n                AND (pc.fecha_pago IS NULL OR pc.medio_pago IS NULL)\n          )\n        ORDER BY p.nombre_completo\n    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerSocioTitularSesion(PDO $db): ?array {
    $idSocio = $_SESSION['id_socio'] ?? null;
    $runPersona = $_SESSION['run_persona'] ?? null;

    if ($idSocio !== null && $idSocio !== '') {
        $stmt = $db->prepare("\n            SELECT\n                s.id_socio,\n                s.run_persona,\n                p.nombre_completo,\n                p.email,\n                p.telefono_celular,\n                su.nombre AS nombre_sucursal\n            FROM public.socio s\n            INNER JOIN public.persona p\n                ON p.run = s.run_persona\n            LEFT JOIN public.sucursal su\n                ON su.codigo_sucursal = s.codigo_sucursal_base\n            WHERE s.id_socio = :id_socio\n              AND LOWER(s.tipo_socio) = 'socio_titular'\n            LIMIT 1\n        ");
        $stmt->execute([':id_socio' => (int)$idSocio]);
    } else {
        $stmt = $db->prepare("\n            SELECT\n                s.id_socio,\n                s.run_persona,\n                p.nombre_completo,\n                p.email,\n                p.telefono_celular,\n                su.nombre AS nombre_sucursal\n            FROM public.socio s\n            INNER JOIN public.persona p\n                ON p.run = s.run_persona\n            LEFT JOIN public.sucursal su\n                ON su.codigo_sucursal = s.codigo_sucursal_base\n            WHERE s.run_persona = :run_persona\n              AND LOWER(s.tipo_socio) = 'socio_titular'\n            LIMIT 1\n        ");
        $stmt->execute([':run_persona' => $runPersona]);
    }

    $socio = $stmt->fetch(PDO::FETCH_ASSOC);
    return $socio ?: null;
}

function obtenerRunSocioSeleccionado(PDO $db, int $idSocio): string {
    $stmt = $db->prepare("\n        SELECT s.run_persona\n        FROM public.socio s\n        WHERE s.id_socio = :id_socio\n          AND LOWER(s.tipo_socio) = 'socio_titular'\n          AND NOT EXISTS (\n              SELECT 1\n              FROM public.pago_cuota pc\n              WHERE pc.id_socio = s.id_socio\n                AND (pc.fecha_pago IS NULL OR pc.medio_pago IS NULL)\n          )\n        LIMIT 1\n    ");
    $stmt->execute([':id_socio' => $idSocio]);
    $run = $stmt->fetchColumn();
    if (!$run) {
        throw new RuntimeException('Socio titular inválido o con deuda');
    }
    return (string)$run;
}

function obtenerRunReservante(PDO $db, array $post): string {
    if (esUsuarioSocioTitular()) {
        $socio = obtenerSocioTitularSesion($db);
        if (!$socio) {
            throw new RuntimeException('No se encontró socio titular para la sesión');
        }
        return (string)$socio['run_persona'];
    }

    $idSocio = (int)($post['id_socio_titular'] ?? 0);
    if ($idSocio <= 0) {
        throw new RuntimeException('Debe seleccionar un socio titular');
    }
    return obtenerRunSocioSeleccionado($db, $idSocio);
}

function obtenerSlotReservable(PDO $db, string $codigoLugar, string $fecha, int $idPrecio): array {
    $fecha = validarFecha($fecha);
    $diaSemana = diaSemanaEspanol(new DateTimeImmutable($fecha));

    $stmt = $db->prepare("\n        SELECT\n            pl.id_precio,\n            pl.codigo_lugar,\n            pl.hora_inicio,\n            pl.hora_termino,\n            pl.monto\n        FROM public.precio_lugar pl\n        WHERE pl.id_precio = :id_precio\n          AND pl.codigo_lugar = :codigo_lugar\n          AND LOWER(pl.tipo_precio) = 'hora'\n          AND LOWER(pl.dia_semana) = :dia_semana\n          AND CAST(:fecha_precio AS date) BETWEEN pl.fecha_inicio AND pl.fecha_fin\n          AND NOT EXISTS (\n              SELECT 1\n              FROM public.reserva r\n              WHERE r.codigo_lugar = pl.codigo_lugar\n                AND LOWER(r.estado) IN ('reservada', 'ejecutada')\n                AND (CAST(:fecha_inicio_reserva AS date) + pl.hora_inicio) < r.fecha_fin\n                AND (CAST(:fecha_fin_reserva AS date) + pl.hora_termino) > r.fecha_inicio\n          )\n        LIMIT 1\n    ");
    $stmt->execute([
        ':id_precio' => $idPrecio,
        ':codigo_lugar' => $codigoLugar,
        ':dia_semana' => $diaSemana,
        ':fecha_precio' => $fecha,
        ':fecha_inicio_reserva' => $fecha,
        ':fecha_fin_reserva' => $fecha,
    ]);

    $slot = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$slot) {
        throw new RuntimeException('El horario ya no está disponible');
    }
    return $slot;
}

function generarCodigoReserva(PDO $db): string {
    $stmt = $db->query("\n        SELECT COALESCE(\n            MAX(\n                CASE\n                    WHEN codigo_reserva ~ '^[0-9]+$' THEN codigo_reserva::integer\n                    ELSE NULL\n                END\n            ),\n            0\n        ) + 1 AS siguiente\n        FROM public.reserva\n    ");
    return (string)$stmt->fetchColumn();
}

function registrarArriendoCancha(PDO $db, array $post): string {
    $codigoLugar = trim((string)($post['codigo_lugar'] ?? ''));
    $fecha = validarFecha((string)($post['fecha_reserva'] ?? ''));
    $idPrecio = (int)($post['id_precio'] ?? 0);

    if ($codigoLugar === '' || $idPrecio <= 0) {
        throw new RuntimeException('Datos de reserva incompletos');
    }

    $db->beginTransaction();
    try {
        $db->exec('LOCK TABLE public.reserva IN SHARE ROW EXCLUSIVE MODE');
        alinearSecuencia($db, 'pago_reserva_id_pago_reserva_seq', 'pago_reserva', 'id_pago_reserva');

        $runReservante = obtenerRunReservante($db, $post);
        $slot = obtenerSlotReservable($db, $codigoLugar, $fecha, $idPrecio);
        $codigoReserva = generarCodigoReserva($db);

        $fechaInicio = $fecha . ' ' . $slot['hora_inicio'];
        $fechaFin = $fecha . ' ' . $slot['hora_termino'];

        $stmtReserva = $db->prepare("\n            INSERT INTO public.reserva (\n                codigo_reserva, codigo_lugar, run_reservante, fecha_inicio, fecha_fin, estado\n            ) VALUES (\n                :codigo_reserva, :codigo_lugar, :run_reservante, :fecha_inicio, :fecha_fin, 'reservada'\n            )\n        ");
        $stmtReserva->execute([
            ':codigo_reserva' => $codigoReserva,
            ':codigo_lugar' => $codigoLugar,
            ':run_reservante' => $runReservante,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin,
        ]);

        $stmtPago = $db->prepare("\n            INSERT INTO public.pago_reserva (codigo_reserva, fecha_pago, monto, medio_pago)\n            VALUES (:codigo_reserva, NULL, :monto, NULL)\n        ");
        $stmtPago->execute([
            ':codigo_reserva' => $codigoReserva,
            ':monto' => (int)$slot['monto'],
        ]);

        $db->commit();
        return $codigoReserva;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

requiereUsuarioAutorizado();

$mensaje = '';
$error = '';
$codigoReservaCreada = '';
$db = conectarBD();
$canchas = obtenerCanchas($db);
$sociosSinDeuda = esUsuarioAdmin() ? obtenerSociosTitularesSinDeuda($db) : [];
$socioSesion = esUsuarioSocioTitular() ? obtenerSocioTitularSesion($db) : null;

$codigoLugarSeleccionado = trim((string)($_GET['codigo_lugar'] ?? $_POST['codigo_lugar'] ?? ''));
$fechaSeleccionada = trim((string)($_GET['fecha'] ?? $_POST['fecha_reserva'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reservar') {
    try {
        $codigoReservaCreada = registrarArriendoCancha($db, $_POST);
        $mensaje = MSG_OK_RESERVA . '. Código de reserva: ' . $codigoReservaCreada;
        $codigoLugarSeleccionado = trim((string)($_POST['codigo_lugar'] ?? ''));
        $fechaSeleccionada = trim((string)($_POST['fecha_reserva'] ?? ''));
    } catch (Throwable $e) {
        $error = MSG_ERROR_RESERVA;
    }
}

if ($codigoLugarSeleccionado !== '' && $fechaSeleccionada === '') {
    $fechaSeleccionada = obtenerFechaInicialCancha($db, $codigoLugarSeleccionado);
}

$calendario = [];
$horariosDisponibles = [];
if ($codigoLugarSeleccionado !== '') {
    try {
        if ($fechaSeleccionada === '') {
            $fechaSeleccionada = date('Y-m-d');
        }
        $fechaSeleccionada = validarFecha($fechaSeleccionada);
        $calendario = obtenerCalendarioDisponibilidad($db, $codigoLugarSeleccionado, $fechaSeleccionada, 14);
        $horariosDisponibles = obtenerHorariosDisponibles($db, $codigoLugarSeleccionado, $fechaSeleccionada);
    } catch (Throwable $e) {
        $error = $error ?: 'No se pudo cargar la disponibilidad de la cancha';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>DCColo - Arriendo de Canchas</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eef3f8;
            color: #1f2d3d;
        }
        .wrapper {
            max-width: 1100px;
            margin: 32px auto;
            padding: 0 18px;
        }
        .topbar {
            background: #fff;
            border: 1px solid #d8e0ea;
            border-radius: 10px;
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        h1, h2, h3 { margin-top: 0; }
        .card {
            background: #fff;
            border: 1px solid #d8e0ea;
            border-radius: 10px;
            padding: 22px;
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        select, input {
            width: 100%;
            padding: 10px;
            border: 1px solid #c9d4e2;
            border-radius: 7px;
            margin-bottom: 14px;
            font-size: 0.95rem;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .btn {
            display: inline-block;
            border: none;
            border-radius: 7px;
            padding: 11px 18px;
            background: #2e6da4;
            color: #fff;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-secondary { background: #6c757d; }
        .btn-success { background: #198754; }
        .btn:hover { opacity: 0.92; }
        .msg-ok {
            background: #d9f2e4;
            color: #0f5132;
            border: 1px solid #a6dfbc;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 16px;
        }
        .msg-error {
            background: #fde2e2;
            color: #842029;
            border: 1px solid #f5b5b5;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border-bottom: 1px solid #e1e8f0;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        th { background: #f5f8fc; }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(125px, 1fr));
            gap: 10px;
        }
        .day-card {
            display: block;
            border: 1px solid #d8e0ea;
            border-radius: 8px;
            padding: 12px;
            text-decoration: none;
            color: #1f2d3d;
            background: #f8fbff;
        }
        .day-card.selected {
            border-color: #2e6da4;
            background: #e7f1fb;
        }
        .day-card.no-slots {
            opacity: 0.55;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            background: #d9f2e4;
            color: #0f5132;
            font-size: 0.78rem;
            margin-top: 6px;
        }
        .badge-empty {
            background: #fde2e2;
            color: #842029;
        }
        .muted { color: #6c757d; font-size: 0.9rem; }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        @media (max-width: 760px) {
            .grid-2 { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; align-items: flex-start; gap: 12px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="topbar">
        <div>
            <h1>Arriendo de Canchas</h1>
            <div class="muted">
                Conectado como <strong><?= h($_SESSION['email_login'] ?? '') ?></strong> — <?= h($_SESSION['rol_sistema'] ?? '') ?>
            </div>
        </div>
        <div class="actions">
            <a class="btn btn-secondary" href="index.php">Volver al panel</a>
            <a class="btn btn-secondary" href="index.php?logout=1">Cerrar sesión</a>
        </div>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="msg-ok"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="msg-error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>1. Seleccionar cancha</h2>
        <form method="GET" action="arrendar_cancha.php">
            <div class="grid-2">
                <div>
                    <label for="codigo_lugar">Cancha</label>
                    <select id="codigo_lugar" name="codigo_lugar" required>
                        <option value="">Seleccione una cancha</option>
                        <?php foreach ($canchas as $cancha): ?>
                            <option value="<?= h($cancha['codigo_lugar']) ?>" <?= $codigoLugarSeleccionado === (string)$cancha['codigo_lugar'] ? 'selected' : '' ?>>
                                <?= h($cancha['nombre_lugar']) ?> — <?= h($cancha['nombre_sucursal']) ?> — <?= h($cancha['tipo_lugar']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fecha">Fecha inicial del calendario</label>
                    <input type="date" id="fecha" name="fecha" value="<?= h($fechaSeleccionada) ?>">
                </div>
            </div>
            <button class="btn" type="submit">Ver disponibilidad</button>
        </form>
    </div>

    <?php if ($codigoLugarSeleccionado !== ''): ?>
        <div class="card">
            <h2>2. Calendario de disponibilidad</h2>
            <p class="muted">Se muestran 14 días desde la fecha seleccionada. Una fecha aparece disponible si tiene al menos un horario libre.</p>
            <div class="calendar-grid">
                <?php foreach ($calendario as $dia): ?>
                    <?php
                        $seleccionado = $dia['fecha'] === $fechaSeleccionada;
                        $sinCupos = (int)$dia['cupos'] === 0;
                        $clases = 'day-card' . ($seleccionado ? ' selected' : '') . ($sinCupos ? ' no-slots' : '');
                    ?>
                    <a class="<?= h($clases) ?>" href="arrendar_cancha.php?codigo_lugar=<?= h(urlencode($codigoLugarSeleccionado)) ?>&fecha=<?= h($dia['fecha']) ?>">
                        <strong><?= h(ucfirst($dia['dia'])) ?></strong><br>
                        <?= h($dia['fecha']) ?><br>
                        <span class="badge <?= $sinCupos ? 'badge-empty' : '' ?>">
                            <?= (int)$dia['cupos'] ?> horario(s)
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h2>3. Seleccionar horario y socio titular</h2>

            <?php if (empty($horariosDisponibles)): ?>
                <p class="msg-error">No hay horarios disponibles para esta cancha en la fecha seleccionada.</p>
            <?php else: ?>
                <form method="POST" action="arrendar_cancha.php">
                    <input type="hidden" name="accion" value="reservar">
                    <input type="hidden" name="codigo_lugar" value="<?= h($codigoLugarSeleccionado) ?>">
                    <input type="hidden" name="fecha_reserva" value="<?= h($fechaSeleccionada) ?>">

                    <label for="id_precio">Horario disponible para <?= h($fechaSeleccionada) ?></label>
                    <select id="id_precio" name="id_precio" required>
                        <option value="">Seleccione horario</option>
                        <?php foreach ($horariosDisponibles as $slot): ?>
                            <option value="<?= h($slot['id_precio']) ?>">
                                <?= h(substr((string)$slot['hora_inicio'], 0, 5)) ?> - <?= h(substr((string)$slot['hora_termino'], 0, 5)) ?> — $<?= h(number_format((int)$slot['monto'], 0, ',', '.')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (esUsuarioSocioTitular()): ?>
                        <h3>Datos del socio titular</h3>
                        <?php if ($socioSesion): ?>
                            <table>
                                <tr><th>Nombre</th><td><?= h($socioSesion['nombre_completo']) ?></td></tr>
                                <tr><th>RUN</th><td><?= h($socioSesion['run_persona']) ?></td></tr>
                                <tr><th>Email</th><td><?= h($socioSesion['email']) ?></td></tr>
                                <tr><th>Teléfono</th><td><?= h($socioSesion['telefono_celular']) ?></td></tr>
                                <tr><th>Sucursal base</th><td><?= h($socioSesion['nombre_sucursal']) ?></td></tr>
                            </table>
                        <?php else: ?>
                            <p class="msg-error">No se encontró información de socio titular para la sesión actual.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <label for="id_socio_titular">Socio titular activo sin deudas</label>
                        <select id="id_socio_titular" name="id_socio_titular" required>
                            <option value="">Seleccione socio titular</option>
                            <?php foreach ($sociosSinDeuda as $socio): ?>
                                <option value="<?= h($socio['id_socio']) ?>">
                                    <?= h($socio['nombre_completo']) ?> — <?= h($socio['run_persona']) ?> — <?= h($socio['nombre_sucursal']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($sociosSinDeuda)): ?>
                            <p class="msg-error">No hay socios titulares sin deudas disponibles para seleccionar.</p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <button class="btn btn-success" type="submit">Registrar arriendo</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
