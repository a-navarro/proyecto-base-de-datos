<?php
// IA: Archivo creado con asistencia de IA para completar el punto 2.6 de la Etapa 3 IIC2413.
// Porcentaje estimado de apoyo IA: 80%.
// Tecnología utilizada: ChatGPT.
// Prompt utilizado: "Finalicemos con el último punto, 2.6: creación de un evento y lista de invitados".
// El estudiante debe revisar, adaptar y comprender completamente este código.

session_start();
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/utils.php';

const MSG_OK_EVENTO = 'Evento registrado correctamente';
const MSG_ERROR_EVENTO = 'Evento no se puede registrar';

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

function validarEnteroPositivo($valor, string $campo): int {
    if (!is_numeric($valor)) {
        throw new RuntimeException($campo . ' inválido');
    }
    $numero = (int)$valor;
    if ($numero <= 0) {
        throw new RuntimeException($campo . ' debe ser mayor que cero');
    }
    return $numero;
}

function normalizarTexto(string $valor): string {
    return trim(preg_replace('/\s+/', ' ', $valor));
}

function alinearSecuencia(PDO $db, string $secuencia, string $tabla, string $columna): void {
    $db->exec("SELECT setval('public.$secuencia', COALESCE((SELECT MAX($columna) FROM public.$tabla), 0) + 1, false)");
}

function obtenerSucursales(PDO $db): array {
    $stmt = $db->query("\n        SELECT codigo_sucursal, nombre\n        FROM public.sucursal\n        ORDER BY nombre\n    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerLugaresEvento(PDO $db, string $codigoSucursal): array {
    $stmt = $db->prepare("\n        SELECT\n            l.codigo_lugar,\n            l.nombre AS nombre_lugar,\n            l.capacidad,\n            l.tipo_lugar,\n            l.codigo_sucursal,\n            s.nombre AS nombre_sucursal\n        FROM public.lugar l\n        INNER JOIN public.sucursal s\n            ON s.codigo_sucursal = l.codigo_sucursal\n        WHERE l.codigo_sucursal = :codigo_sucursal\n          AND LOWER(l.tipo_lugar) = 'salon_eventos'\n        ORDER BY l.nombre\n    ");
    $stmt->execute([':codigo_sucursal' => $codigoSucursal]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function lugarEstaEnLista(array $lugares, string $codigoLugar): bool {
    foreach ($lugares as $lugar) {
        if ((string)$lugar['codigo_lugar'] === (string)$codigoLugar) {
            return true;
        }
    }
    return false;
}

function obtenerSociosTitulares(PDO $db): array {
    $stmt = $db->query("\n        SELECT\n            s.id_socio,\n            s.run_persona,\n            p.nombre_completo,\n            p.email,\n            su.nombre AS nombre_sucursal\n        FROM public.socio s\n        INNER JOIN public.persona p\n            ON p.run = s.run_persona\n        LEFT JOIN public.sucursal su\n            ON su.codigo_sucursal = s.codigo_sucursal_base\n        WHERE LOWER(s.tipo_socio) = 'socio_titular'\n        ORDER BY p.nombre_completo\n    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerSocioTitularSesion(PDO $db): ?array {
    $idSocio = $_SESSION['id_socio'] ?? null;
    $runPersona = $_SESSION['run_persona'] ?? null;

    if ($idSocio !== null && $idSocio !== '') {
        $stmt = $db->prepare("\n            SELECT\n                s.id_socio,\n                s.run_persona,\n                p.nombre_completo,\n                p.email,\n                su.nombre AS nombre_sucursal\n            FROM public.socio s\n            INNER JOIN public.persona p\n                ON p.run = s.run_persona\n            LEFT JOIN public.sucursal su\n                ON su.codigo_sucursal = s.codigo_sucursal_base\n            WHERE s.id_socio = :id_socio\n              AND LOWER(s.tipo_socio) = 'socio_titular'\n            LIMIT 1\n        ");
        $stmt->execute([':id_socio' => (int)$idSocio]);
    } else {
        $stmt = $db->prepare("\n            SELECT\n                s.id_socio,\n                s.run_persona,\n                p.nombre_completo,\n                p.email,\n                su.nombre AS nombre_sucursal\n            FROM public.socio s\n            INNER JOIN public.persona p\n                ON p.run = s.run_persona\n            LEFT JOIN public.sucursal su\n                ON su.codigo_sucursal = s.codigo_sucursal_base\n            WHERE s.run_persona = :run_persona\n              AND LOWER(s.tipo_socio) = 'socio_titular'\n            LIMIT 1\n        ");
        $stmt->execute([':run_persona' => $runPersona]);
    }

    $socio = $stmt->fetch(PDO::FETCH_ASSOC);
    return $socio ?: null;
}

function obtenerRunSocioPorId(PDO $db, int $idSocio): string {
    $stmt = $db->prepare("\n        SELECT s.run_persona\n        FROM public.socio s\n        WHERE s.id_socio = :id_socio\n          AND LOWER(s.tipo_socio) = 'socio_titular'\n        LIMIT 1\n    ");
    $stmt->execute([':id_socio' => $idSocio]);
    $run = $stmt->fetchColumn();
    if (!$run) {
        throw new RuntimeException('Socio titular inválido');
    }
    return (string)$run;
}

function obtenerNombrePersona(PDO $db, string $run): ?string {
    $stmt = $db->prepare("\n        SELECT nombre_completo\n        FROM public.persona\n        WHERE run = :run\n        LIMIT 1\n    ");
    $stmt->execute([':run' => $run]);
    $nombre = $stmt->fetchColumn();
    return $nombre ? (string)$nombre : null;
}

function runPareceValido(string $run): bool {
    return (bool)preg_match('/^[0-9]{1,2}\.?[0-9]{3}\.?[0-9]{3}-[0-9Kk]$/', trim($run));
}

function obtenerRunContratante(PDO $db, array $post): string {
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
    return obtenerRunSocioPorId($db, $idSocio);
}

function obtenerHorariosEventoDisponibles(PDO $db, string $codigoLugar, string $fecha): array {
    $fecha = validarFecha($fecha);
    $diaSemana = diaSemanaEspanol(new DateTimeImmutable($fecha));

    $stmt = $db->prepare("\n        SELECT DISTINCT ON (pl.hora_inicio, pl.hora_termino)\n            pl.id_precio,\n            pl.codigo_lugar,\n            pl.tipo_precio,\n            pl.hora_inicio,\n            pl.hora_termino,\n            pl.monto\n        FROM public.precio_lugar pl\n        WHERE pl.codigo_lugar = :codigo_lugar\n          AND LOWER(pl.tipo_precio) IN ('tramo', 'dia')\n          AND LOWER(pl.dia_semana) = :dia_semana\n          AND CAST(:fecha_precio AS date) BETWEEN pl.fecha_inicio AND pl.fecha_fin\n          AND NOT EXISTS (\n              SELECT 1\n              FROM public.reserva r\n              WHERE r.codigo_lugar = pl.codigo_lugar\n                AND LOWER(r.estado) IN ('reservada', 'ejecutada')\n                AND (CAST(:fecha_inicio_reserva AS date) + pl.hora_inicio) < r.fecha_fin\n                AND (CAST(:fecha_fin_reserva AS date) + pl.hora_termino) > r.fecha_inicio\n          )\n          AND NOT EXISTS (\n              SELECT 1\n              FROM public.evento e\n              WHERE e.codigo_lugar = pl.codigo_lugar\n                AND e.fecha_evento = CAST(:fecha_evento AS date)\n          )\n        ORDER BY pl.hora_inicio, pl.hora_termino, pl.fecha_inicio DESC, pl.id_precio DESC\n    ");
    $stmt->execute([
        ':codigo_lugar' => $codigoLugar,
        ':dia_semana' => $diaSemana,
        ':fecha_precio' => $fecha,
        ':fecha_inicio_reserva' => $fecha,
        ':fecha_fin_reserva' => $fecha,
        ':fecha_evento' => $fecha,
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerPrimeraFechaConDisponibilidad(PDO $db, string $codigoLugar): string {
    $stmt = $db->prepare("\n        SELECT\n            MIN(fecha_inicio)::date AS fecha_minima,\n            MAX(fecha_fin)::date AS fecha_maxima\n        FROM public.precio_lugar\n        WHERE codigo_lugar = :codigo_lugar\n          AND LOWER(tipo_precio) IN ('tramo', 'dia')\n    ");
    $stmt->execute([':codigo_lugar' => $codigoLugar]);
    $rango = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rango || !$rango['fecha_minima']) {
        return date('Y-m-d');
    }

    $inicio = new DateTimeImmutable((string)$rango['fecha_minima']);
    $fin = new DateTimeImmutable((string)$rango['fecha_maxima']);
    $maxDias = min(900, max(0, (int)$inicio->diff($fin)->format('%a')) + 1);

    for ($i = 0; $i < $maxDias; $i++) {
        $fecha = $inicio->modify('+' . $i . ' days')->format('Y-m-d');
        if (count(obtenerHorariosEventoDisponibles($db, $codigoLugar, $fecha)) > 0) {
            return $fecha;
        }
    }

    return $inicio->format('Y-m-d');
}

function obtenerSlotEvento(PDO $db, string $codigoLugar, string $fecha, int $idPrecio): array {
    $fecha = validarFecha($fecha);
    $diaSemana = diaSemanaEspanol(new DateTimeImmutable($fecha));

    $stmt = $db->prepare("\n        SELECT\n            pl.id_precio,\n            pl.codigo_lugar,\n            pl.tipo_precio,\n            pl.hora_inicio,\n            pl.hora_termino,\n            pl.monto\n        FROM public.precio_lugar pl\n        WHERE pl.id_precio = :id_precio\n          AND pl.codigo_lugar = :codigo_lugar\n          AND LOWER(pl.tipo_precio) IN ('tramo', 'dia')\n          AND LOWER(pl.dia_semana) = :dia_semana\n          AND CAST(:fecha_precio AS date) BETWEEN pl.fecha_inicio AND pl.fecha_fin\n          AND NOT EXISTS (\n              SELECT 1\n              FROM public.reserva r\n              WHERE r.codigo_lugar = pl.codigo_lugar\n                AND LOWER(r.estado) IN ('reservada', 'ejecutada')\n                AND (CAST(:fecha_inicio_reserva AS date) + pl.hora_inicio) < r.fecha_fin\n                AND (CAST(:fecha_fin_reserva AS date) + pl.hora_termino) > r.fecha_inicio\n          )\n          AND NOT EXISTS (\n              SELECT 1\n              FROM public.evento e\n              WHERE e.codigo_lugar = pl.codigo_lugar\n                AND e.fecha_evento = CAST(:fecha_evento AS date)\n          )\n        LIMIT 1\n    ");
    $stmt->execute([
        ':id_precio' => $idPrecio,
        ':codigo_lugar' => $codigoLugar,
        ':dia_semana' => $diaSemana,
        ':fecha_precio' => $fecha,
        ':fecha_inicio_reserva' => $fecha,
        ':fecha_fin_reserva' => $fecha,
        ':fecha_evento' => $fecha,
    ]);

    $slot = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$slot) {
        throw new RuntimeException('El horario seleccionado ya no está disponible');
    }
    return $slot;
}

function validarLugarEnSucursal(PDO $db, string $codigoSucursal, string $codigoLugar): void {
    $stmt = $db->prepare("\n        SELECT 1\n        FROM public.lugar\n        WHERE codigo_lugar = :codigo_lugar\n          AND codigo_sucursal = :codigo_sucursal\n          AND LOWER(tipo_lugar) = 'salon_eventos'\n        LIMIT 1\n    ");
    $stmt->execute([
        ':codigo_lugar' => $codigoLugar,
        ':codigo_sucursal' => $codigoSucursal,
    ]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('Lugar inválido para la sucursal seleccionada');
    }
}

function generarCodigoEvento(PDO $db): string {
    $stmt = $db->query("\n        SELECT COALESCE(\n            MAX(\n                CASE\n                    WHEN codigo_evento ~ '^[0-9]+$' THEN codigo_evento::integer\n                    ELSE NULL\n                END\n            ),\n            0\n        ) + 1 AS siguiente\n        FROM public.evento\n    ");
    return (string)$stmt->fetchColumn();
}

function generarCodigoReservaEvento(PDO $db, string $codigoEvento): string {
    $base = 'EV' . $codigoEvento;
    $codigo = substr($base, 0, 20);

    $stmt = $db->prepare("SELECT 1 FROM public.reserva WHERE codigo_reserva = :codigo LIMIT 1");
    $stmt->execute([':codigo' => $codigo]);
    if (!$stmt->fetchColumn()) {
        return $codigo;
    }

    $sufijo = 1;
    do {
        $codigo = substr('EV' . $codigoEvento . '-' . $sufijo, 0, 20);
        $stmt->execute([':codigo' => $codigo]);
        $existe = $stmt->fetchColumn();
        $sufijo++;
    } while ($existe);

    return $codigo;
}

function parsearInvitados(PDO $db, string $texto): array {
    $lineas = preg_split('/\r\n|\r|\n/', $texto);
    $invitados = [];

    foreach ($lineas as $linea) {
        $linea = normalizarTexto($linea);
        if ($linea === '') {
            continue;
        }

        $run = null;
        $nombre = null;

        if (strpos($linea, ';') !== false) {
            $partes = array_map('trim', explode(';', $linea, 2));
            $runCandidato = $partes[0] ?? '';
            $nombreCandidato = $partes[1] ?? '';

            if ($runCandidato !== '' && runPareceValido($runCandidato)) {
                $run = $runCandidato;
                $nombre = $nombreCandidato !== '' ? normalizarTexto($nombreCandidato) : obtenerNombrePersona($db, $run);
            } else {
                $nombre = normalizarTexto($linea);
            }
        } else {
            if (runPareceValido($linea)) {
                $run = $linea;
                $nombre = obtenerNombrePersona($db, $run);
                if ($nombre === null) {
                    throw new RuntimeException('El invitado con RUN ' . $run . ' no existe en persona. Ingrese también su nombre como RUN;Nombre.');
                }
            } else {
                $nombre = $linea;
            }
        }

        if ($nombre === null || trim($nombre) === '') {
            throw new RuntimeException('Cada invitado debe tener nombre');
        }

        $invitados[] = [
            'run_asistente' => $run,
            'nombre_asistente' => $nombre,
        ];
    }

    if (count($invitados) === 0) {
        throw new RuntimeException('Debe ingresar al menos un invitado');
    }

    return $invitados;
}

function insertarEmpresaYContacto(PDO $db, array $post): array {
    $rutEmpresa = normalizarTexto((string)($post['rut_empresa'] ?? ''));
    $nombreEmpresa = normalizarTexto((string)($post['nombre_empresa'] ?? ''));
    $runContacto = normalizarTexto((string)($post['run_contacto_empresa'] ?? ''));
    $nombreContacto = normalizarTexto((string)($post['nombre_contacto_empresa'] ?? ''));
    $cargoContacto = normalizarTexto((string)($post['cargo_contacto_empresa'] ?? ''));

    if ($rutEmpresa === '' || $nombreEmpresa === '') {
        throw new RuntimeException('Debe ingresar RUT y nombre de la empresa');
    }

    $stmtEmpresa = $db->prepare("\n        INSERT INTO public.empresa (rut_empresa, nombre)\n        VALUES (:rut_empresa, :nombre)\n        ON CONFLICT (rut_empresa) DO UPDATE\n        SET nombre = EXCLUDED.nombre\n    ");
    $stmtEmpresa->execute([
        ':rut_empresa' => $rutEmpresa,
        ':nombre' => $nombreEmpresa,
    ]);

    if ($runContacto !== '' || $nombreContacto !== '' || $cargoContacto !== '') {
        if ($nombreContacto === '') {
            throw new RuntimeException('Si ingresa contacto de empresa, debe incluir nombre');
        }

        $stmtContacto = $db->prepare("\n            INSERT INTO public.contacto_empresa (rut_empresa, run_persona, nombre, cargo)\n            VALUES (:rut_empresa, :run_persona, :nombre, :cargo)\n        ");
        $stmtContacto->execute([
            ':rut_empresa' => $rutEmpresa,
            ':run_persona' => $runContacto !== '' ? $runContacto : null,
            ':nombre' => $nombreContacto,
            ':cargo' => $cargoContacto !== '' ? $cargoContacto : null,
        ]);
    }

    return [
        'tipo_cliente' => 'empresa-institucion',
        'identificador_cliente' => $rutEmpresa,
        'run_reservante' => $runContacto !== '' ? $runContacto : $rutEmpresa,
    ];
}

function registrarEvento(PDO $db, array $post): string {
    $nombreEvento = normalizarTexto((string)($post['nombre_evento'] ?? ''));
    $tipoCliente = (string)($post['tipo_cliente'] ?? '');
    $codigoSucursal = normalizarTexto((string)($post['codigo_sucursal'] ?? ''));
    $codigoLugar = normalizarTexto((string)($post['codigo_lugar'] ?? ''));
    $fechaEvento = validarFecha((string)($post['fecha_evento'] ?? ''));
    $idPrecio = (int)($post['id_precio'] ?? 0);
    $montoTotal = validarEnteroPositivo($post['monto_total_evento'] ?? null, 'Valor del evento');
    $montoPrimeraCuota = validarEnteroPositivo($post['monto_primera_cuota'] ?? null, 'Primera cuota');
    $fechaPago = validarFecha((string)($post['fecha_pago'] ?? date('Y-m-d')));
    $invitadosTexto = (string)($post['invitados_texto'] ?? '');

    if ($nombreEvento === '' || $codigoSucursal === '' || $codigoLugar === '' || $idPrecio <= 0) {
        throw new RuntimeException('Faltan datos obligatorios del evento');
    }

    if ($montoPrimeraCuota > $montoTotal) {
        throw new RuntimeException('La primera cuota no puede superar el valor total del evento');
    }

    $db->beginTransaction();
    try {
        $db->exec('LOCK TABLE public.evento IN SHARE ROW EXCLUSIVE MODE');
        $db->exec('LOCK TABLE public.reserva IN SHARE ROW EXCLUSIVE MODE');
        alinearSecuencia($db, 'pago_evento_id_pago_evento_seq', 'pago_evento', 'id_pago_evento');
        alinearSecuencia($db, 'asistente_evento_id_asistente_seq', 'asistente_evento', 'id_asistente');
        alinearSecuencia($db, 'contacto_empresa_id_contacto_seq', 'contacto_empresa', 'id_contacto');

        validarLugarEnSucursal($db, $codigoSucursal, $codigoLugar);
        $slot = obtenerSlotEvento($db, $codigoLugar, $fechaEvento, $idPrecio);
        $invitados = parsearInvitados($db, $invitadosTexto);

        if ($tipoCliente === 'socio') {
            $runCliente = obtenerRunContratante($db, $post);
            $cliente = [
                'tipo_cliente' => 'socio',
                'identificador_cliente' => $runCliente,
                'run_reservante' => $runCliente,
            ];
        } elseif ($tipoCliente === 'empresa') {
            $cliente = insertarEmpresaYContacto($db, $post);
        } else {
            throw new RuntimeException('Tipo de cliente inválido');
        }

        $codigoEvento = generarCodigoEvento($db);
        $codigoReserva = generarCodigoReservaEvento($db, $codigoEvento);
        $fechaInicio = $fechaEvento . ' ' . $slot['hora_inicio'];
        $fechaFin = $fechaEvento . ' ' . $slot['hora_termino'];

        $stmtEvento = $db->prepare("\n            INSERT INTO public.evento (\n                codigo_evento, nombre, fecha_evento, codigo_lugar, codigo_sucursal, tipo_cliente, identificador_cliente\n            ) VALUES (\n                :codigo_evento, :nombre, :fecha_evento, :codigo_lugar, :codigo_sucursal, :tipo_cliente, :identificador_cliente\n            )\n        ");
        $stmtEvento->execute([
            ':codigo_evento' => $codigoEvento,
            ':nombre' => $nombreEvento,
            ':fecha_evento' => $fechaEvento,
            ':codigo_lugar' => $codigoLugar,
            ':codigo_sucursal' => $codigoSucursal,
            ':tipo_cliente' => $cliente['tipo_cliente'],
            ':identificador_cliente' => $cliente['identificador_cliente'],
        ]);

        // La tabla evento del dump no guarda hora de inicio/fin. Se registra una reserva asociada
        // para bloquear el horario y dejar persistida la disponibilidad usada por el evento.
        $stmtReserva = $db->prepare("\n            INSERT INTO public.reserva (codigo_reserva, codigo_lugar, run_reservante, fecha_inicio, fecha_fin, estado)\n            VALUES (:codigo_reserva, :codigo_lugar, :run_reservante, :fecha_inicio, :fecha_fin, 'reservada')\n        ");
        $stmtReserva->execute([
            ':codigo_reserva' => $codigoReserva,
            ':codigo_lugar' => $codigoLugar,
            ':run_reservante' => $cliente['run_reservante'],
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin,
        ]);

        $stmtPago = $db->prepare("\n            INSERT INTO public.pago_evento (codigo_evento, fecha_pago, monto, tipo_pago)\n            VALUES (:codigo_evento, :fecha_pago, :monto, 'reserva')\n        ");
        $stmtPago->execute([
            ':codigo_evento' => $codigoEvento,
            ':fecha_pago' => $fechaPago,
            ':monto' => $montoPrimeraCuota,
        ]);

        $stmtInvitado = $db->prepare("\n            INSERT INTO public.asistente_evento (codigo_evento, run_asistente, nombre_asistente)\n            VALUES (:codigo_evento, :run_asistente, :nombre_asistente)\n        ");
        foreach ($invitados as $invitado) {
            $stmtInvitado->execute([
                ':codigo_evento' => $codigoEvento,
                ':run_asistente' => $invitado['run_asistente'],
                ':nombre_asistente' => $invitado['nombre_asistente'],
            ]);
        }

        $db->commit();
        return $codigoEvento;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

requiereUsuarioAutorizado();

$mensajeOk = '';
$mensajeError = '';
$debugError = '';
$sucursales = [];
$lugares = [];
$sociosTitulares = [];
$socioSesion = null;
$horariosDisponibles = [];

try {
    $db = conectarBD();
    $sucursales = obtenerSucursales($db);
    $sociosTitulares = esUsuarioAdmin() ? obtenerSociosTitulares($db) : [];
    $socioSesion = esUsuarioSocioTitular() ? obtenerSocioTitularSesion($db) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registrar_evento') {
        $codigoCreado = registrarEvento($db, $_POST);
        $mensajeOk = MSG_OK_EVENTO . '. Código evento: ' . $codigoCreado;
    }

    $codigoSucursalSeleccionada = $_GET['codigo_sucursal'] ?? ($_POST['codigo_sucursal'] ?? ($sucursales[0]['codigo_sucursal'] ?? ''));
    $lugares = $codigoSucursalSeleccionada !== '' ? obtenerLugaresEvento($db, $codigoSucursalSeleccionada) : [];

    $codigoLugarSeleccionado = $_GET['codigo_lugar'] ?? ($_POST['codigo_lugar'] ?? ($lugares[0]['codigo_lugar'] ?? ''));
    if ($codigoLugarSeleccionado !== '' && !lugarEstaEnLista($lugares, $codigoLugarSeleccionado)) {
        $codigoLugarSeleccionado = $lugares[0]['codigo_lugar'] ?? '';
    }

    $fechaSeleccionada = $_GET['fecha_evento'] ?? ($_POST['fecha_evento'] ?? '');
    if ($fechaSeleccionada === '' && $codigoLugarSeleccionado !== '') {
        $fechaSeleccionada = obtenerPrimeraFechaConDisponibilidad($db, $codigoLugarSeleccionado);
    }

    if ($codigoLugarSeleccionado !== '' && $fechaSeleccionada !== '') {
        $horariosDisponibles = obtenerHorariosEventoDisponibles($db, $codigoLugarSeleccionado, $fechaSeleccionada);
    }
} catch (Throwable $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mensajeError = MSG_ERROR_EVENTO;
    } else {
        $mensajeError = 'No se pudo cargar la pantalla de eventos';
    }
    $debugError = $e->getMessage();
}

$codigoSucursalSeleccionada = $codigoSucursalSeleccionada ?? '';
$codigoLugarSeleccionado = $codigoLugarSeleccionado ?? '';
$fechaSeleccionada = $fechaSeleccionada ?? date('Y-m-d');
$tipoClienteSeleccionado = $_POST['tipo_cliente'] ?? (esUsuarioSocioTitular() ? 'socio' : 'socio');
$idPrecioSeleccionado = $_POST['id_precio'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCColo — Crear evento</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; color: #222; min-height: 100vh; }
        .wrapper { max-width: 1180px; margin: 0 auto; padding: 24px; }
        .topbar { background: #fff; padding: 18px 22px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; justify-content: space-between; gap: 12px; align-items: center; }
        h1 { color: #1a3a5c; font-size: 1.55rem; }
        h2 { color: #1a3a5c; font-size: 1.15rem; margin-bottom: 14px; }
        h3 { color: #1a3a5c; font-size: 1rem; margin-bottom: 10px; }
        .btn-back { color: #2e6da4; text-decoration: none; font-weight: 700; }
        .panel { background: #fff; border-radius: 10px; padding: 22px; margin-bottom: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        label { display: block; font-size: 0.84rem; font-weight: 700; color: #444; margin-bottom: 6px; }
        input, select, textarea { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem; background: #fff; }
        textarea { min-height: 135px; resize: vertical; font-family: Arial, sans-serif; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #2e6da4; box-shadow: 0 0 0 3px rgba(46,109,164,0.15); }
        .btn { border: none; border-radius: 6px; padding: 11px 16px; font-size: 0.95rem; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: #1a3a5c; color: #fff; }
        .btn-secondary { background: #e8eef5; color: #1a3a5c; }
        .btn-row { display: flex; gap: 10px; align-items: center; margin-top: 16px; flex-wrap: wrap; }
        .success-msg, .error-msg, .warn-msg { padding: 12px 14px; border-radius: 6px; margin-bottom: 16px; font-weight: 700; }
        .success-msg { background: #d4edda; color: #155724; }
        .error-msg { background: #f8d7da; color: #721c24; }
        .warn-msg { background: #fff3cd; color: #856404; font-weight: 600; }
        .muted { color: #666; font-size: 0.88rem; line-height: 1.45; }
        .subpanel { border: 1px solid #dde4ec; border-radius: 8px; padding: 16px; margin-top: 16px; background: #fbfcfe; }
        .horarios { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .slot { border: 1px solid #ccd6e0; border-radius: 8px; padding: 11px; cursor: pointer; background: #fff; display: block; }
        .slot input { width: auto; margin-right: 8px; }
        .slot strong { color: #1a3a5c; }
        .user-info { color: #555; font-size: 0.9rem; margin-top: 4px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #e8eef5; color: #1a3a5c; font-size: 0.78rem; font-weight: 700; margin-left: 6px; }
        .hidden { display: none; }
        @media (max-width: 800px) { .grid, .grid-3, .horarios { grid-template-columns: 1fr; } .topbar { flex-direction: column; align-items: flex-start; } }
    </style>
    <script>
        function toggleCliente() {
            const tipo = document.querySelector('input[name="tipo_cliente"]:checked')?.value || 'socio';
            const bloqueSocio = document.getElementById('bloque-socio');
            const bloqueEmpresa = document.getElementById('bloque-empresa');
            if (bloqueSocio) bloqueSocio.classList.toggle('hidden', tipo !== 'socio');
            if (bloqueEmpresa) bloqueEmpresa.classList.toggle('hidden', tipo !== 'empresa');
        }
        document.addEventListener('DOMContentLoaded', toggleCliente);
    </script>
</head>
<body>
<div class="wrapper">
    <div class="topbar">
        <div>
            <h1>Crear evento</h1>
            <div class="user-info">
                <?= h($_SESSION['nombre_completo'] ?? '') ?>
                <span class="badge"><?= h($_SESSION['rol_sistema'] ?? '') ?></span>
            </div>
        </div>
        <a class="btn-back" href="index.php">← Volver al panel</a>
    </div>

    <?php if ($mensajeOk !== ''): ?>
        <div class="success-msg"><?= h($mensajeOk) ?></div>
    <?php endif; ?>

    <?php if ($mensajeError !== ''): ?>
        <div class="error-msg"><?= h($mensajeError) ?></div>
        <?php if ($debugError !== ''): ?>
            <div class="warn-msg">Detalle técnico para depurar: <?= h($debugError) ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="panel">
        <h2>1. Seleccionar sucursal, lugar, fecha y horario disponible</h2>
        <form method="GET" action="crear_evento.php">
            <div class="grid-3">
                <div>
                    <label for="codigo_sucursal">Sucursal</label>
                    <select id="codigo_sucursal" name="codigo_sucursal" onchange="this.form.submit()" required>
                        <?php foreach ($sucursales as $sucursal): ?>
                            <option value="<?= h($sucursal['codigo_sucursal']) ?>" <?= (string)$codigoSucursalSeleccionada === (string)$sucursal['codigo_sucursal'] ? 'selected' : '' ?>>
                                <?= h($sucursal['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="codigo_lugar">Lugar</label>
                    <select id="codigo_lugar" name="codigo_lugar" onchange="this.form.submit()" required>
                        <?php foreach ($lugares as $lugar): ?>
                            <option value="<?= h($lugar['codigo_lugar']) ?>" <?= (string)$codigoLugarSeleccionado === (string)$lugar['codigo_lugar'] ? 'selected' : '' ?>>
                                <?= h($lugar['nombre_lugar']) ?> — capacidad <?= h($lugar['capacidad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="fecha_evento_get">Fecha del evento</label>
                    <input type="date" id="fecha_evento_get" name="fecha_evento" value="<?= h($fechaSeleccionada) ?>" required>
                </div>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-secondary">Ver horarios disponibles</button>
            </div>
        </form>
    </div>

    <form method="POST" action="crear_evento.php?codigo_sucursal=<?= h(urlencode($codigoSucursalSeleccionada)) ?>&codigo_lugar=<?= h(urlencode($codigoLugarSeleccionado)) ?>&fecha_evento=<?= h(urlencode($fechaSeleccionada)) ?>">
        <input type="hidden" name="accion" value="registrar_evento">
        <input type="hidden" name="codigo_sucursal" value="<?= h($codigoSucursalSeleccionada) ?>">
        <input type="hidden" name="codigo_lugar" value="<?= h($codigoLugarSeleccionado) ?>">
        <input type="hidden" name="fecha_evento" value="<?= h($fechaSeleccionada) ?>">

        <div class="panel">
            <h2>2. Datos del evento</h2>
            <div class="grid">
                <div>
                    <label for="nombre_evento">Nombre del evento</label>
                    <input type="text" id="nombre_evento" name="nombre_evento" value="<?= h($_POST['nombre_evento'] ?? '') ?>" placeholder="Ej: Congreso médico" required>
                </div>
                <div>
                    <label for="monto_total_evento">Valor total del evento</label>
                    <input type="number" id="monto_total_evento" name="monto_total_evento" min="1" value="<?= h($_POST['monto_total_evento'] ?? '') ?>" placeholder="Ej: 5000000" required>
                </div>
            </div>

            <div class="subpanel">
                <h3>Horario seleccionado</h3>
                <?php if (count($horariosDisponibles) === 0): ?>
                    <p class="warn-msg">No hay horarios disponibles para ese lugar y fecha. Cambia la fecha o el lugar.</p>
                <?php else: ?>
                    <div class="horarios">
                        <?php foreach ($horariosDisponibles as $slot): ?>
                            <label class="slot">
                                <input type="radio" name="id_precio" value="<?= h($slot['id_precio']) ?>" <?= (string)$idPrecioSeleccionado === (string)$slot['id_precio'] ? 'checked' : '' ?> required>
                                <strong><?= h(substr((string)$slot['hora_inicio'], 0, 5)) ?>–<?= h(substr((string)$slot['hora_termino'], 0, 5)) ?></strong><br>
                                <span><?= h($slot['tipo_precio']) ?> — referencia $<?= h(number_format((int)$slot['monto'], 0, ',', '.')) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <h2>3. Contratante</h2>
            <?php if (esUsuarioSocioTitular()): ?>
                <input type="hidden" name="tipo_cliente" value="socio">
                <div class="subpanel">
                    <strong>Socio titular conectado</strong><br>
                    <?= $socioSesion ? h($socioSesion['nombre_completo']) . ' — ' . h($socioSesion['run_persona']) : 'No se encontró socio titular asociado a la sesión' ?>
                </div>
            <?php else: ?>
                <div class="btn-row" style="margin-top: 0; margin-bottom: 14px;">
                    <label style="display:inline-flex; align-items:center; gap:7px; margin:0;">
                        <input type="radio" name="tipo_cliente" value="socio" onchange="toggleCliente()" <?= $tipoClienteSeleccionado === 'socio' ? 'checked' : '' ?>> Socio
                    </label>
                    <label style="display:inline-flex; align-items:center; gap:7px; margin:0;">
                        <input type="radio" name="tipo_cliente" value="empresa" onchange="toggleCliente()" <?= $tipoClienteSeleccionado === 'empresa' ? 'checked' : '' ?>> Empresa
                    </label>
                </div>

                <div id="bloque-socio" class="subpanel">
                    <label for="id_socio_titular">Socio titular contratante</label>
                    <select id="id_socio_titular" name="id_socio_titular">
                        <option value="">Seleccione socio</option>
                        <?php foreach ($sociosTitulares as $socio): ?>
                            <option value="<?= h($socio['id_socio']) ?>" <?= (string)($_POST['id_socio_titular'] ?? '') === (string)$socio['id_socio'] ? 'selected' : '' ?>>
                                <?= h($socio['nombre_completo']) ?> — <?= h($socio['run_persona']) ?> — <?= h($socio['nombre_sucursal']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="bloque-empresa" class="subpanel hidden">
                    <div class="grid">
                        <div>
                            <label for="rut_empresa">RUT empresa</label>
                            <input type="text" id="rut_empresa" name="rut_empresa" value="<?= h($_POST['rut_empresa'] ?? '') ?>" placeholder="76.000.000-0">
                        </div>
                        <div>
                            <label for="nombre_empresa">Nombre empresa</label>
                            <input type="text" id="nombre_empresa" name="nombre_empresa" value="<?= h($_POST['nombre_empresa'] ?? '') ?>" placeholder="Empresa SpA">
                        </div>
                        <div>
                            <label for="run_contacto_empresa">RUN contacto empresa</label>
                            <input type="text" id="run_contacto_empresa" name="run_contacto_empresa" value="<?= h($_POST['run_contacto_empresa'] ?? '') ?>" placeholder="12345678-9">
                        </div>
                        <div>
                            <label for="nombre_contacto_empresa">Nombre contacto empresa</label>
                            <input type="text" id="nombre_contacto_empresa" name="nombre_contacto_empresa" value="<?= h($_POST['nombre_contacto_empresa'] ?? '') ?>" placeholder="Nombre Apellido">
                        </div>
                        <div>
                            <label for="cargo_contacto_empresa">Cargo contacto</label>
                            <input type="text" id="cargo_contacto_empresa" name="cargo_contacto_empresa" value="<?= h($_POST['cargo_contacto_empresa'] ?? '') ?>" placeholder="Gerente / Coordinador">
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>4. Pago de primera cuota</h2>
            <div class="grid">
                <div>
                    <label for="monto_primera_cuota">Monto primera cuota</label>
                    <input type="number" id="monto_primera_cuota" name="monto_primera_cuota" min="1" value="<?= h($_POST['monto_primera_cuota'] ?? '') ?>" placeholder="Ej: 1000000" required>
                </div>
                <div>
                    <label for="fecha_pago">Fecha de pago</label>
                    <input type="date" id="fecha_pago" name="fecha_pago" value="<?= h($_POST['fecha_pago'] ?? date('Y-m-d')) ?>" required>
                </div>
            </div>
            <p class="muted" style="margin-top: 10px;">En el dump, la tabla pago_evento no tiene medio de pago ni campo de valor total. Por eso se registra la primera cuota como pago_evento.tipo_pago = 'reserva'.</p>
        </div>

        <div class="panel">
            <h2>5. Lista de invitados</h2>
            <label for="invitados_texto">Invitados</label>
            <textarea id="invitados_texto" name="invitados_texto" placeholder="Un invitado por línea. Formatos permitidos:&#10;12345678-9;Nombre Apellido&#10;Nombre Apellido" required><?= h($_POST['invitados_texto'] ?? '') ?></textarea>
            <p class="muted" style="margin-top: 8px;">Si escribes solo un RUN, debe existir previamente en persona para completar el nombre. Si no existe, usa el formato RUN;Nombre.</p>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary" <?= count($horariosDisponibles) === 0 ? 'disabled' : '' ?>>Registrar evento</button>
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </div>
    </form>
</div>
</body>
</html>
