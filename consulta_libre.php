<?php
// IA: Este archivo fue creado con asistencia de IA para la Etapa 3 de IIC2413.
// Porcentaje estimado de apoyo IA: 80%.
// Tecnología utilizada: ChatGPT.
// Prompt utilizado: "Vamos con el punto 2.5: Consulta inestructurada".
// El estudiante debe revisar, adaptar y comprender completamente este código.

session_start();
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/utils.php';

function h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function usuarioAutorizado(): bool {
    return isset($_SESSION['rol_sistema'])
        && in_array($_SESSION['rol_sistema'], ['Administrativo', 'Administrador'], true);
}

function quoteIdent(string $identificador): string {
    return '"' . str_replace('"', '""', $identificador) . '"';
}

function normalizarNombreTabla(string $entrada): string {
    $entrada = strtolower(trim($entrada));

    // Se acepta persona o public.persona, pero no expresiones, JOIN ni subconsultas.
    if (strpos($entrada, '.') !== false) {
        $partes = explode('.', $entrada);
        if (count($partes) === 2 && $partes[0] === 'public') {
            $entrada = $partes[1];
        }
    }

    return $entrada;
}

function contienePatronesPeligrosos(string $texto): bool {
    return preg_match('/(;|--|\/\*|\*\/|\b(insert|update|delete|drop|alter|create|truncate|grant|revoke|copy|execute|call)\b)/i', $texto) === 1;
}

function obtenerTablasPermitidas(PDO $db): array {
    $sql = "
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_type IN ('BASE TABLE', 'VIEW')
        ORDER BY table_name
    ";
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function obtenerColumnasTabla(PDO $db, string $tabla): array {
    $sql = "
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = :tabla
        ORDER BY ordinal_position
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':tabla' => $tabla]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function construirSelectSeguro(string $entradaA, array $columnasPermitidas): string {
    $entradaA = trim($entradaA);

    if ($entradaA === '') {
        throw new InvalidArgumentException('Debe ingresar A, es decir, las columnas a consultar.');
    }

    if ($entradaA === '*') {
        return '*';
    }

    if (contienePatronesPeligrosos($entradaA)) {
        throw new InvalidArgumentException('A contiene texto no permitido. Use solo nombres de columnas separados por coma o * .');
    }

    $columnasPermitidasMap = array_flip($columnasPermitidas);
    $partes = array_map('trim', explode(',', $entradaA));
    $salida = [];

    foreach ($partes as $columna) {
        $columna = strtolower($columna);

        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $columna)) {
            throw new InvalidArgumentException('A solo puede contener columnas simples separadas por coma. No use funciones ni expresiones.');
        }

        if (!isset($columnasPermitidasMap[$columna])) {
            throw new InvalidArgumentException('La columna ' . $columna . ' no existe en la tabla seleccionada.');
        }

        $salida[] = quoteIdent($columna);
    }

    return implode(', ', $salida);
}

function dividirValoresIn(string $texto): array {
    $valores = [];
    $actual = '';
    $enComillaSimple = false;
    $enComillaDoble = false;
    $largo = strlen($texto);

    for ($i = 0; $i < $largo; $i++) {
        $caracter = $texto[$i];

        if ($caracter === "'" && !$enComillaDoble) {
            $enComillaSimple = !$enComillaSimple;
            $actual .= $caracter;
            continue;
        }

        if ($caracter === '"' && !$enComillaSimple) {
            $enComillaDoble = !$enComillaDoble;
            $actual .= $caracter;
            continue;
        }

        if ($caracter === ',' && !$enComillaSimple && !$enComillaDoble) {
            $valores[] = trim($actual);
            $actual = '';
            continue;
        }

        $actual .= $caracter;
    }

    if (trim($actual) !== '') {
        $valores[] = trim($actual);
    }

    return $valores;
}

function normalizarValor(string $valor): string {
    $valor = trim($valor);

    if (strlen($valor) >= 2) {
        $primero = $valor[0];
        $ultimo = $valor[strlen($valor) - 1];

        if (($primero === "'" && $ultimo === "'") || ($primero === '"' && $ultimo === '"')) {
            $valor = substr($valor, 1, -1);
            $valor = str_replace("''", "'", $valor);
            $valor = str_replace('""', '"', $valor);
        }
    }

    return $valor;
}

function construirWhereSeguro(string $entradaC, array $columnasPermitidas, array &$params): string {
    $entradaC = trim($entradaC);

    if ($entradaC === '') {
        return 'TRUE';
    }

    if (contienePatronesPeligrosos($entradaC)) {
        throw new InvalidArgumentException('C contiene texto no permitido. Use condiciones simples, sin ;, comentarios ni comandos SQL.');
    }

    $columnasPermitidasMap = array_flip($columnasPermitidas);
    $tokens = preg_split('/\s+(AND|OR)\s+/i', $entradaC, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

    if (!$tokens) {
        throw new InvalidArgumentException('C no tiene una condición válida.');
    }

    $salida = [];
    $esperaCondicion = true;
    $contadorParam = 1;

    foreach ($tokens as $token) {
        $token = trim($token);

        if ($esperaCondicion === false) {
            if (!preg_match('/^(AND|OR)$/i', $token)) {
                throw new InvalidArgumentException('C tiene conectores inválidos. Use solo AND u OR entre condiciones.');
            }
            $salida[] = strtoupper($token);
            $esperaCondicion = true;
            continue;
        }

        // columna IS NULL / columna IS NOT NULL
        if (preg_match('/^([a-z_][a-z0-9_]*)\s+IS\s+(NOT\s+)?NULL$/i', $token, $m)) {
            $columna = strtolower($m[1]);
            if (!isset($columnasPermitidasMap[$columna])) {
                throw new InvalidArgumentException('La columna ' . $columna . ' no existe en la tabla seleccionada.');
            }
            $salida[] = quoteIdent($columna) . ' IS ' . (trim($m[2] ?? '') !== '' ? 'NOT ' : '') . 'NULL';
            $esperaCondicion = false;
            continue;
        }

        // columna IN (valor1, valor2, ...)
        if (preg_match('/^([a-z_][a-z0-9_]*)\s+IN\s*\((.*)\)$/i', $token, $m)) {
            $columna = strtolower($m[1]);
            if (!isset($columnasPermitidasMap[$columna])) {
                throw new InvalidArgumentException('La columna ' . $columna . ' no existe en la tabla seleccionada.');
            }

            $valores = dividirValoresIn($m[2]);
            if (count($valores) === 0) {
                throw new InvalidArgumentException('IN debe incluir al menos un valor.');
            }

            $placeholders = [];
            foreach ($valores as $valor) {
                $nombreParam = ':p' . $contadorParam++;
                $params[$nombreParam] = normalizarValor($valor);
                $placeholders[] = $nombreParam;
            }

            $salida[] = quoteIdent($columna) . ' IN (' . implode(', ', $placeholders) . ')';
            $esperaCondicion = false;
            continue;
        }

        // columna operador valor
        if (preg_match('/^([a-z_][a-z0-9_]*)\s*(<=|>=|<>|!=|=|<|>|ILIKE|LIKE)\s*(.+)$/i', $token, $m)) {
            $columna = strtolower($m[1]);
            $operador = strtoupper($m[2]);
            $valor = trim($m[3]);

            if (!isset($columnasPermitidasMap[$columna])) {
                throw new InvalidArgumentException('La columna ' . $columna . ' no existe en la tabla seleccionada.');
            }

            if ($valor === '') {
                throw new InvalidArgumentException('Falta el valor de comparación para ' . $columna . '.');
            }

            $nombreParam = ':p' . $contadorParam++;
            $params[$nombreParam] = normalizarValor($valor);
            $salida[] = quoteIdent($columna) . ' ' . $operador . ' ' . $nombreParam;
            $esperaCondicion = false;
            continue;
        }

        throw new InvalidArgumentException('C contiene una condición no soportada: ' . $token);
    }

    if ($esperaCondicion === true) {
        throw new InvalidArgumentException('C no puede terminar en AND u OR.');
    }

    return implode(' ', $salida);
}

if (!usuarioAutorizado()) {
    header('Location: index.php');
    exit();
}

$db = null;
$tablasPermitidas = [];
$resultados = [];
$columnasResultado = [];
$error = '';
$sqlGenerado = '';
$a = trim($_POST['a'] ?? 'nombre_completo, email');
$b = trim($_POST['b'] ?? 'persona');
$c = trim($_POST['c'] ?? "nombre_completo ILIKE '%Juan%'");

try {
    $db = conectarBD();
    $tablasPermitidas = obtenerTablasPermitidas($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tabla = normalizarNombreTabla($b);

        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $tabla)) {
            throw new InvalidArgumentException('B debe ser el nombre simple de una tabla o vista del esquema public.');
        }

        if (!in_array($tabla, $tablasPermitidas, true)) {
            throw new InvalidArgumentException('La tabla o vista indicada en B no existe o no está permitida.');
        }

        $columnasTabla = obtenerColumnasTabla($db, $tabla);

        if (count($columnasTabla) === 0) {
            throw new InvalidArgumentException('La tabla seleccionada no tiene columnas consultables.');
        }

        $params = [];
        $selectSeguro = construirSelectSeguro($a, $columnasTabla);
        $whereSeguro = construirWhereSeguro($c, $columnasTabla, $params);

        $sqlGenerado = 'SELECT ' . $selectSeguro . ' FROM public.' . quoteIdent($tabla) . ' WHERE ' . $whereSeguro . ' LIMIT 100';

        $stmt = $db->prepare($sqlGenerado);
        foreach ($params as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor);
        }
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($resultados) > 0) {
            $columnasResultado = array_keys($resultados[0]);
        } elseif ($selectSeguro === '*') {
            $columnasResultado = $columnasTabla;
        } else {
            $columnasResultado = array_map(function ($col) {
                return trim($col, '"');
            }, explode(', ', $selectSeguro));
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DCColo — Consulta inestructurada</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; color: #222; min-height: 100vh; }
        .main-wrapper { max-width: 1100px; margin: 0 auto; padding: 32px 16px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; background: #1a3a5c; color: #fff; padding: 16px 24px; border-radius: 10px 10px 0 0; }
        .topbar h1 { font-size: 1.25rem; }
        .topbar a { color: #fff; text-decoration: none; font-weight: 700; }
        .panel { background: #fff; border-radius: 0 0 10px 10px; padding: 24px; box-shadow: 0 4px 16px rgba(0,0,0,0.10); }
        .intro { color: #555; font-size: 0.92rem; margin-bottom: 18px; line-height: 1.45; }
        .grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
        label { display: block; font-size: 0.84rem; font-weight: 700; color: #444; margin-bottom: 5px; }
        input[type="text"], select { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem; }
        input:focus, select:focus { outline: none; border-color: #2e6da4; box-shadow: 0 0 0 3px rgba(46,109,164,0.15); }
        .btn { display: inline-block; padding: 10px 16px; background: #1a3a5c; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; text-decoration: none; font-size: 0.9rem; }
        .btn:hover { background: #2e6da4; }
        .btn-secondary { background: #607d8b; }
        .btn-secondary:hover { background: #455a64; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        .error-msg { background: #fdecea; color: #c0392b; border: 1px solid #e74c3c; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.9rem; font-weight: 700; }
        .ok-msg { background: #eafaf1; color: #1e8449; border: 1px solid #2ecc71; border-radius: 6px; padding: 10px 14px; margin-top: 18px; font-size: 0.9rem; }
        .help { background: #f5f8fc; border: 1px solid #d0dcea; border-radius: 8px; padding: 14px; margin-top: 18px; color: #445; font-size: 0.88rem; line-height: 1.5; }
        .sql-box { background: #1f2933; color: #e6edf3; padding: 12px; border-radius: 6px; overflow-x: auto; font-family: monospace; font-size: 0.84rem; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 0.86rem; }
        th, td { border: 1px solid #d8e0ea; padding: 8px 10px; text-align: left; vertical-align: top; }
        th { background: #e8edf3; color: #1a3a5c; }
        tr:nth-child(even) td { background: #f7f9fb; }
        .small { font-size: 0.82rem; color: #666; margin-top: 8px; }
        @media (min-width: 780px) { .grid { grid-template-columns: 1fr 1fr; } .campo-wide { grid-column: span 2; } }
    </style>
</head>
<body>
<div class="main-wrapper">
    <div class="topbar">
        <div>
            <h1>Consulta inestructurada</h1>
            <div style="font-size:0.86rem;color:#d3e4f5;">SELECT A FROM B WHERE C</div>
        </div>
        <a href="index.php">Volver al panel</a>
    </div>

    <div class="panel">
        <p class="intro">
            Ingrese los tres componentes de la consulta. Para evitar inyección SQL, esta pantalla no ejecuta texto SQL libre:
            valida que B sea una tabla o vista existente, valida que A contenga columnas reales y transforma los valores de C
            en parámetros de PDO.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error-msg"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="consulta_libre.php">
            <div class="grid">
                <div class="campo-wide">
                    <label for="a">A — columnas a mostrar</label>
                    <input type="text" id="a" name="a" value="<?= h($a) ?>" placeholder="Ej: nombre_completo, email o *" required>
                </div>

                <div>
                    <label for="b">B — tabla o vista</label>
                    <input type="text" id="b" name="b" value="<?= h($b) ?>" placeholder="Ej: persona" list="tablas" required>
                    <datalist id="tablas">
                        <?php foreach ($tablasPermitidas as $tablaDisponible): ?>
                            <option value="<?= h($tablaDisponible) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div>
                    <label for="c">C — condición WHERE</label>
                    <input type="text" id="c" name="c" value="<?= h($c) ?>" placeholder="Ej: codigo_comuna = 13101 o nombre_completo ILIKE '%Juan%'">
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn">Ejecutar consulta</button>
                <a href="consulta_libre.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="help">
            <strong>Formato soportado:</strong><br>
            A: <code>*</code> o columnas separadas por coma. Ejemplo: <code>run, nombre_completo, email</code>.<br>
            B: una tabla o vista del esquema <code>public</code>. Ejemplo: <code>persona</code>, <code>socio</code>, <code>reserva</code>.<br>
            C: condiciones simples con <code>=</code>, <code>!=</code>, <code>&lt;</code>, <code>&lt;=</code>, <code>&gt;</code>, <code>&gt;=</code>, <code>LIKE</code>, <code>ILIKE</code>, <code>IN (...)</code>, <code>IS NULL</code> o <code>IS NOT NULL</code>, combinadas con <code>AND</code>/<code>OR</code>.
        </div>

        <?php if ($sqlGenerado !== ''): ?>
            <div class="ok-msg">
                Consulta ejecutada correctamente. Se muestran máximo 100 filas.
            </div>
            <div class="sql-box"><?= h($sqlGenerado) ?></div>

            <?php if (count($resultados) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columnasResultado as $columna): ?>
                                <th><?= h($columna) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $fila): ?>
                            <tr>
                                <?php foreach ($columnasResultado as $columna): ?>
                                    <td><?= h($fila[$columna] ?? '') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="small">La consulta no retornó filas.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
