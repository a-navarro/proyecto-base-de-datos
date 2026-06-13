<?php
// IA: Archivo creado con asistencia de IA para completar el punto 2.4 de la Etapa 3 IIC2413.
// Porcentaje estimado de apoyo IA: 80%.
// Tecnología utilizada: ChatGPT.
// Prompt utilizado: "Sigamos con el punto 2.4: Consultas similares a E2".
// El estudiante debe revisar, adaptar y comprender completamente este código.

session_start();
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/utils.php';

function h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function requiereAdmin(): void {
    $rol = $_SESSION['rol_sistema'] ?? '';
    if (!in_array($rol, ['Administrativo', 'Administrador'], true)) {
        header('Location: index.php');
        exit();
    }
}

function validarFecha(string $fecha): string {
    $fecha = trim($fecha);
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
    if (!$dt || $dt->format('Y-m-d') !== $fecha) {
        throw new RuntimeException('Fecha inválida');
    }
    return $fecha;
}

function lunesDeSemana(string $fecha): string {
    $fecha = validarFecha($fecha);
    $dt = new DateTimeImmutable($fecha);
    $diasDesdeLunes = ((int)$dt->format('N')) - 1;
    return $dt->modify('-' . $diasDesdeLunes . ' days')->format('Y-m-d');
}

function obtenerSucursales(PDO $db): array {
    $stmt = $db->query("SELECT codigo_sucursal, nombre FROM public.sucursal ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function vistasInstaladas(PDO $db): bool {
    $stmt = $db->query("
        SELECT COUNT(*)
        FROM (
            VALUES
                ('public.v_e2_agenda'),
                ('public.v_e2_ingreso_mensual'),
                ('public.v_e2_morosos'),
                ('public.v_e2_finbeneficiario'),
                ('public.v_e2_ingreso_sucursal_2025')
        ) AS v(nombre)
        WHERE to_regclass(v.nombre) IS NOT NULL
    ");
    return ((int)$stmt->fetchColumn()) === 5;
}

function formatearMonto($valor): string {
    if ($valor === null || $valor === '') {
        return '';
    }
    return '$' . number_format((float)$valor, 0, ',', '.');
}

function renderTabla(array $filas, array $columnas, array $moneyCols = []): void {
    if (count($filas) === 0) {
        echo '<p class="empty">No hay resultados para los filtros seleccionados.</p>';
        return;
    }

    echo '<div class="table-wrapper"><table><thead><tr>';
    foreach ($columnas as $campo => $titulo) {
        echo '<th>' . h($titulo) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($filas as $fila) {
        echo '<tr>';
        foreach ($columnas as $campo => $titulo) {
            $valor = $fila[$campo] ?? '';
            if (in_array($campo, $moneyCols, true)) {
                $valor = formatearMonto($valor);
            }
            echo '<td>' . h($valor) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function obtenerConsultaSeleccionada(): string {
    $permitidas = ['agenda', 'ingreso_mensual', 'morosos', 'finbeneficiario', 'ingreso_sucursal'];
    $consulta = $_GET['consulta'] ?? 'agenda';
    return in_array($consulta, $permitidas, true) ? $consulta : 'agenda';
}

function ejecutarAgenda(PDO $db, string $sucursal, string $fecha): array {
    $lunes = lunesDeSemana($fecha);
    $stmt = $db->prepare("
        SELECT
            dia,
            fecha,
            hora,
            lugar,
            tipo_registro,
            detalle,
            estado,
            codigo_referencia
        FROM public.v_e2_agenda
        WHERE nombre_sucursal = :sucursal
          AND fecha >= CAST(:lunes_desde AS date)
          AND fecha < CAST(:lunes_hasta AS date)
        ORDER BY fecha, hora, lugar, detalle
    ");
    $stmt->execute([
        ':sucursal' => $sucursal,
        ':lunes_desde' => $lunes,
        ':lunes_hasta' => (new DateTimeImmutable($lunes))->modify('+7 days')->format('Y-m-d'),
    ]);
    return [$lunes, $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function ejecutarIngresoMensual(PDO $db, string $sucursal, int $anio, int $mes): array {
    $stmt = $db->prepare("
        SELECT
            concepto,
            estado_ingreso,
            monto
        FROM public.v_e2_ingreso_mensual
        WHERE nombre_sucursal = :sucursal
          AND anio = :anio
          AND mes = :mes
        ORDER BY concepto, estado_ingreso
    ");
    $stmt->execute([
        ':sucursal' => $sucursal,
        ':anio' => $anio,
        ':mes' => $mes,
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ejecutarMorosos(PDO $db, string $sucursal): array {
    if ($sucursal === '') {
        $stmt = $db->query("
            SELECT
                nombre_completo,
                run,
                nombre_sucursal,
                monto_atrasado,
                numero_cuotas,
                primera_cuota_atrasada,
                ultima_cuota_atrasada
            FROM public.v_e2_morosos
            ORDER BY numero_cuotas DESC, monto_atrasado DESC, nombre_completo
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $db->prepare("
        SELECT
            nombre_completo,
            run,
            nombre_sucursal,
            monto_atrasado,
            numero_cuotas,
            primera_cuota_atrasada,
            ultima_cuota_atrasada
        FROM public.v_e2_morosos
        WHERE nombre_sucursal = :sucursal
        ORDER BY numero_cuotas DESC, monto_atrasado DESC, nombre_completo
    ");
    $stmt->execute([':sucursal' => $sucursal]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ejecutarFinBeneficiario(PDO $db, int $anio): array {
    $stmt = $db->prepare("
        SELECT
            run_beneficiario,
            nombre_beneficiario,
            email_beneficiario,
            telefono_beneficiario,
            fecha_nacimiento,
            parentesco,
            run_socio_titular,
            nombre_socio_titular,
            email_socio_titular,
            telefono_socio_titular,
            nombre_sucursal
        FROM public.v_e2_finbeneficiario
        WHERE anio_cumple_29 = :anio
        ORDER BY nombre_sucursal, nombre_socio_titular, nombre_beneficiario
    ");
    $stmt->execute([':anio' => $anio]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ejecutarIngresoSucursal(PDO $db): array {
    $stmt = $db->query("
        SELECT
            nombre_sucursal,
            gerente_a_cargo,
            ingresos_totales,
            porcentaje_total_club
        FROM public.v_e2_ingreso_sucursal_2025
        ORDER BY ingresos_totales DESC, nombre_sucursal
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

requiereAdmin();

$consulta = obtenerConsultaSeleccionada();
$error = '';
$sucursales = [];
$resultados = [];
$lunesUsado = '';
$vistasOk = false;

try {
    $db = conectarBD();
    $sucursales = obtenerSucursales($db);
    $vistasOk = vistasInstaladas($db);

    if ($vistasOk) {
        $sucursalDefault = $sucursales[0]['nombre'] ?? 'Santa Cruz';
        foreach ($sucursales as $sucursal) {
            if ($sucursal['nombre'] === 'Santa Cruz') {
                $sucursalDefault = 'Santa Cruz';
                break;
            }
        }

        if ($consulta === 'agenda') {
            $sucursal = trim($_GET['sucursal'] ?? $sucursalDefault);
            $fecha = trim($_GET['fecha'] ?? '2026-04-06');
            [$lunesUsado, $resultados] = ejecutarAgenda($db, $sucursal, $fecha);
        } elseif ($consulta === 'ingreso_mensual') {
            $sucursal = trim($_GET['sucursal'] ?? $sucursalDefault);
            $anio = (int)($_GET['anio'] ?? date('Y'));
            $mes = (int)($_GET['mes'] ?? date('n'));
            $anio = ($anio >= 2000 && $anio <= 2100) ? $anio : (int)date('Y');
            $mes = ($mes >= 1 && $mes <= 12) ? $mes : (int)date('n');
            $resultados = ejecutarIngresoMensual($db, $sucursal, $anio, $mes);
        } elseif ($consulta === 'morosos') {
            $sucursal = trim($_GET['sucursal'] ?? '');
            $resultados = ejecutarMorosos($db, $sucursal);
        } elseif ($consulta === 'finbeneficiario') {
            $anio = (int)($_GET['anio'] ?? 2026);
            $anio = ($anio >= 2000 && $anio <= 2100) ? $anio : 2026;
            $resultados = ejecutarFinBeneficiario($db, $anio);
        } elseif ($consulta === 'ingreso_sucursal') {
            $resultados = ejecutarIngresoSucursal($db);
        }
    }
} catch (Throwable $e) {
    error_log('Error en consultas_e2.php: ' . $e->getMessage());
    $error = 'No se pudo ejecutar la consulta. Revisa que hayas cargado vistas_consultas_e2.sql en la base de datos.';
}

function selected($actual, $esperado): string {
    return ((string)$actual === (string)$esperado) ? 'selected' : '';
}

function activeTab(string $actual, string $esperado): string {
    return $actual === $esperado ? 'active' : '';
}

$sucursalActual = trim($_GET['sucursal'] ?? '');
$anioActual = (int)($_GET['anio'] ?? date('Y'));
$mesActual = (int)($_GET['mes'] ?? date('n'));
$fechaActual = trim($_GET['fecha'] ?? '2026-04-06');
$anioBeneficiario = (int)($_GET['anio'] ?? 2026);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultas E2 — DCColo</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f0f2f5; color: #222; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 28px 16px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 14px; background: #1a3a5c; color: #fff; padding: 16px 22px; border-radius: 10px; }
        .topbar h1 { font-size: 1.35rem; margin: 0; }
        .topbar p { margin: 4px 0 0; font-size: 0.88rem; color: #dce8f5; }
        .btn-back { color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.5); border-radius: 6px; padding: 8px 12px; font-weight: 700; }
        .panel { background: #fff; border: 1px solid #d0dcea; border-radius: 10px; margin-top: 18px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
        .tabs a { text-decoration: none; color: #1a3a5c; background: #f5f8fc; border: 1px solid #d0dcea; border-radius: 999px; padding: 8px 12px; font-size: 0.88rem; font-weight: 700; }
        .tabs a.active { background: #1a3a5c; color: #fff; border-color: #1a3a5c; }
        form.filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; background: #f8fafc; border: 1px solid #e1e8f0; border-radius: 8px; padding: 14px; margin-bottom: 18px; }
        label { display: block; font-size: 0.8rem; color: #444; font-weight: 700; margin-bottom: 5px; }
        select, input { min-width: 180px; padding: 9px 10px; border: 1px solid #c6d3e1; border-radius: 6px; font-size: 0.92rem; }
        button { padding: 10px 14px; border: none; border-radius: 6px; background: #1a3a5c; color: #fff; cursor: pointer; font-weight: 700; }
        button:hover { background: #2e6da4; }
        .hint { margin: 0 0 14px; font-size: 0.88rem; color: #666; }
        .error { background: #fdecea; color: #c0392b; border: 1px solid #e74c3c; border-radius: 6px; padding: 12px; font-weight: 700; }
        .warning { background: #fff8e6; color: #7a5300; border: 1px solid #ffd466; border-radius: 6px; padding: 12px; font-weight: 700; }
        .empty { background: #f8fafc; border: 1px dashed #b5c4d4; border-radius: 6px; padding: 14px; color: #666; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        th, td { border-bottom: 1px solid #e6edf5; padding: 9px 10px; vertical-align: top; text-align: left; }
        th { background: #f5f8fc; color: #1a3a5c; font-weight: 800; white-space: nowrap; }
        tr:hover td { background: #fafcff; }
        .summary { margin-top: 12px; font-weight: 800; color: #1a3a5c; }
        code { background: #eef3f8; padding: 2px 5px; border-radius: 4px; }
        @media (max-width: 720px) { .topbar { flex-direction: column; align-items: flex-start; } .btn-back { width: 100%; text-align: center; } select, input { width: 100%; min-width: 0; } form.filters > div { width: 100%; } button { width: 100%; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div>
            <h1>Consultas similares a E2</h1>
            <p>Usuario: <?= h($_SESSION['email_login'] ?? '') ?> — rol <?= h($_SESSION['rol_sistema'] ?? '') ?></p>
        </div>
        <a class="btn-back" href="index.php">Volver al panel</a>
    </div>

    <div class="panel">
        <div class="tabs">
            <a class="<?= activeTab($consulta, 'agenda') ?>" href="consultas_e2.php?consulta=agenda">Agenda semanal</a>
            <a class="<?= activeTab($consulta, 'ingreso_mensual') ?>" href="consultas_e2.php?consulta=ingreso_mensual">Ingreso mensual</a>
            <a class="<?= activeTab($consulta, 'morosos') ?>" href="consultas_e2.php?consulta=morosos">Socios morosos</a>
            <a class="<?= activeTab($consulta, 'finbeneficiario') ?>" href="consultas_e2.php?consulta=finbeneficiario">Beneficiarios 29 años</a>
            <a class="<?= activeTab($consulta, 'ingreso_sucursal') ?>" href="consultas_e2.php?consulta=ingreso_sucursal">Ingreso por sucursal 2025</a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="error"><?= h($error) ?></div>
        <?php elseif (!$vistasOk): ?>
            <div class="warning">
                Debes cargar primero <code>vistas_consultas_e2.sql</code> en PostgreSQL antes de usar esta pantalla.
            </div>
        <?php else: ?>

            <?php if ($consulta === 'agenda'): ?>
                <p class="hint">Muestra día, fecha, hora, lugar y evento o socio con reserva para la semana seleccionada.</p>
                <form class="filters" method="GET" action="consultas_e2.php">
                    <input type="hidden" name="consulta" value="agenda">
                    <div>
                        <label for="sucursal">Sucursal</label>
                        <select name="sucursal" id="sucursal">
                            <?php foreach ($sucursales as $s): ?>
                                <?php $actual = $sucursalActual !== '' ? $sucursalActual : 'Santa Cruz'; ?>
                                <option value="<?= h($s['nombre']) ?>" <?= selected($actual, $s['nombre']) ?>><?= h($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="fecha">Cualquier día de la semana</label>
                        <input type="date" name="fecha" id="fecha" value="<?= h($fechaActual) ?>">
                    </div>
                    <button type="submit">Consultar</button>
                </form>
                <p class="hint">Semana usada: lunes <strong><?= h($lunesUsado) ?></strong>.</p>
                <?php renderTabla($resultados, [
                    'dia' => 'Día',
                    'fecha' => 'Fecha',
                    'hora' => 'Hora',
                    'lugar' => 'Lugar',
                    'tipo_registro' => 'Tipo',
                    'detalle' => 'Evento / Socio',
                    'estado' => 'Estado',
                    'codigo_referencia' => 'Código',
                ]); ?>

            <?php elseif ($consulta === 'ingreso_mensual'): ?>
                <p class="hint">Agrupa ingresos por membresías, reservas y eventos, separando recibido vs. futuro esperado.</p>
                <form class="filters" method="GET" action="consultas_e2.php">
                    <input type="hidden" name="consulta" value="ingreso_mensual">
                    <div>
                        <label for="sucursal">Sucursal</label>
                        <select name="sucursal" id="sucursal">
                            <?php foreach ($sucursales as $s): ?>
                                <?php $actual = $sucursalActual !== '' ? $sucursalActual : 'Santa Cruz'; ?>
                                <option value="<?= h($s['nombre']) ?>" <?= selected($actual, $s['nombre']) ?>><?= h($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="anio">Año</label>
                        <input type="number" name="anio" id="anio" min="2000" max="2100" value="<?= h($anioActual) ?>">
                    </div>
                    <div>
                        <label for="mes">Mes</label>
                        <input type="number" name="mes" id="mes" min="1" max="12" value="<?= h($mesActual) ?>">
                    </div>
                    <button type="submit">Consultar</button>
                </form>
                <?php renderTabla($resultados, [
                    'concepto' => 'Concepto',
                    'estado_ingreso' => 'Tipo de ingreso',
                    'monto' => 'Monto',
                ], ['monto']); ?>
                <?php if (count($resultados) > 0): ?>
                    <?php $total = array_sum(array_map(fn($r) => (int)$r['monto'], $resultados)); ?>
                    <p class="summary">Total del mes: <?= h(formatearMonto($total)) ?></p>
                <?php endif; ?>

            <?php elseif ($consulta === 'morosos'): ?>
                <p class="hint">Socios titulares con cuotas impagas, incluyendo monto acumulado y número de cuotas.</p>
                <form class="filters" method="GET" action="consultas_e2.php">
                    <input type="hidden" name="consulta" value="morosos">
                    <div>
                        <label for="sucursal">Sucursal</label>
                        <select name="sucursal" id="sucursal">
                            <option value="" <?= selected($sucursalActual, '') ?>>Todas</option>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?= h($s['nombre']) ?>" <?= selected($sucursalActual, $s['nombre']) ?>><?= h($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Consultar</button>
                </form>
                <?php renderTabla($resultados, [
                    'nombre_completo' => 'Socio',
                    'run' => 'RUN',
                    'nombre_sucursal' => 'Sucursal',
                    'monto_atrasado' => 'Monto atrasado',
                    'numero_cuotas' => 'N° cuotas',
                    'primera_cuota_atrasada' => 'Primera cuota',
                    'ultima_cuota_atrasada' => 'Última cuota',
                ], ['monto_atrasado']); ?>

            <?php elseif ($consulta === 'finbeneficiario'): ?>
                <p class="hint">Beneficiarios hijos/hijas que cumplen 29 años en el año indicado y pasan a pagar adicional.</p>
                <form class="filters" method="GET" action="consultas_e2.php">
                    <input type="hidden" name="consulta" value="finbeneficiario">
                    <div>
                        <label for="anio">Año de renovación</label>
                        <input type="number" name="anio" id="anio" min="2000" max="2100" value="<?= h($anioBeneficiario) ?>">
                    </div>
                    <button type="submit">Consultar</button>
                </form>
                <?php renderTabla($resultados, [
                    'run_beneficiario' => 'RUN beneficiario',
                    'nombre_beneficiario' => 'Beneficiario',
                    'email_beneficiario' => 'Correo beneficiario',
                    'telefono_beneficiario' => 'Teléfono beneficiario',
                    'fecha_nacimiento' => 'Nacimiento',
                    'parentesco' => 'Parentesco',
                    'run_socio_titular' => 'RUN titular',
                    'nombre_socio_titular' => 'Socio titular',
                    'email_socio_titular' => 'Correo titular',
                    'telefono_socio_titular' => 'Teléfono titular',
                    'nombre_sucursal' => 'Sucursal',
                ]); ?>

            <?php elseif ($consulta === 'ingreso_sucursal'): ?>
                <p class="hint">Reporte 2025 de ingresos totales por sucursal y porcentaje sobre el total del club.</p>
                <?php renderTabla($resultados, [
                    'nombre_sucursal' => 'Sucursal',
                    'gerente_a_cargo' => 'Gerente a cargo',
                    'ingresos_totales' => 'Ingresos totales',
                    'porcentaje_total_club' => '% total club',
                ], ['ingresos_totales']); ?>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
</body>
</html>
