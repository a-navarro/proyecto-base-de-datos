<?php
// IA: Archivo base construido con ayuda de ChatGPT para orientar la estructura
// de limpieza de CSV de la Etapa 2. Debe ser revisado, entendido, ajustado y
// declarado en el README según las reglas del curso.
// Prompt usado: "Ayúdame a programar el main.php de mi proyecto".

const DELIMITADOR = ';';

$archivos = [
    'regiones_comunas.csv' => 'limpiarRegionesComunas',
    'personas_socios.csv' => 'limpiarPersonas',
    'personas socios.csv' => 'limpiarPersonas',
    'personas.csv' => 'limpiarPersonas',
    'sucursales_lugares.csv' => 'limpiarSucursalesLugares',
    'sucursales lugares.csv' => 'limpiarSucursalesLugares',
    'reservas_arriendos.csv' => 'limpiarReservasArriendos',
    'reservas arriendos.csv' => 'limpiarReservasArriendos',
    'eventos.csv' => 'limpiarEventos',
    'pagos_membresias.csv' => 'limpiarPagosMembresias',
    'pagos membresias.csv' => 'limpiarPagosMembresias',
    'cargos_administrativos.csv' => 'limpiarCargosAdministrativos',
    'cargos administrativos.csv' => 'limpiarCargosAdministrativos',
];

foreach ($archivos as $archivo => $funcionLimpieza) {
    if (file_exists($archivo)) {
        procesarArchivo($archivo, $funcionLimpieza);
    }
}

echo "Proceso main.php terminado\n";

function procesarArchivo(string $archivo, string $funcionLimpieza): void
{
    $entrada = fopen($archivo, 'r');
    if ($entrada === false) {
        echo "No se pudo abrir $archivo\n";
        return;
    }

    $base = pathinfo($archivo, PATHINFO_FILENAME);
    $salidaOk = fopen($base . 'OK.csv', 'w');
    $salidaErr = fopen($base . 'ERR.csv', 'w');
    $salidaLog = fopen($base . 'LOG.csv', 'w');

    if ($salidaOk === false || $salidaErr === false || $salidaLog === false) {
        fclose($entrada);
        echo "No se pudieron crear archivos de salida para $archivo\n";
        return;
    }

    $encabezado = fgetcsv($entrada, 0, DELIMITADOR);
    if ($encabezado === false) {
        fclose($entrada);
        fclose($salidaOk);
        fclose($salidaErr);
        fclose($salidaLog);
        echo "Archivo vacío: $archivo\n";
        return;
    }

    $encabezado = limpiarEncabezado($encabezado);
    fputcsv($salidaOk, $encabezado, DELIMITADOR);
    fputcsv($salidaErr, $encabezado, DELIMITADOR);
    fputcsv($salidaLog, [
        'linea', 'campo', 'accion', 'valor_original', 'valor_nuevo', 'detalle'
    ], DELIMITADOR);

    $linea = 1;
    while (($datos = fgetcsv($entrada, 0, DELIMITADOR)) !== false) {
        $linea++;

        if (filaVacia($datos)) {
            continue;
        }

        if (count($datos) !== count($encabezado)) {
            fputcsv($salidaErr, $datos, DELIMITADOR);
            fputcsv($salidaLog, [
                $linea,
                'fila completa',
                'eliminacion',
                implode('|', $datos),
                '',
                'Cantidad de columnas distinta al encabezado'
            ], DELIMITADOR);
            continue;
        }

        $fila = array_combine($encabezado, $datos);
        if ($fila === false) {
            continue;
        }

        foreach ($fila as $campo => $valor) {
            $fila[$campo] = limpiarTextoSimple((string) $valor);
        }

        $filaOriginal = $fila;
        $logs = [];
        $descartar = false;

        $funcionLimpieza($fila, $logs, $descartar);

        if (!empty($logs)) {
            escribirFila($salidaErr, $encabezado, $filaOriginal);
            foreach ($logs as $log) {
                fputcsv($salidaLog, [
                    $linea,
                    $log['campo'],
                    $log['accion'],
                    $log['original'],
                    $log['nuevo'],
                    $log['detalle']
                ], DELIMITADOR);
            }
        }

        if (!$descartar) {
            escribirFila($salidaOk, $encabezado, $fila);
        }
    }

    fclose($entrada);
    fclose($salidaOk);
    fclose($salidaErr);
    fclose($salidaLog);

    echo "Procesado: $archivo\n";
}

function limpiarEncabezado(array $encabezado): array
{
    $limpio = [];
    foreach ($encabezado as $i => $campo) {
        $campo = (string) $campo;
        if ($i === 0) {
            $campo = preg_replace('/^\xEF\xBB\xBF/', '', $campo);
        }
        $limpio[] = trim($campo);
    }
    return $limpio;
}

function escribirFila($archivoSalida, array $encabezado, array $fila): void
{
    $ordenada = [];
    foreach ($encabezado as $campo) {
        $ordenada[] = $fila[$campo] ?? '';
    }
    fputcsv($archivoSalida, $ordenada, DELIMITADOR);
}

function filaVacia(array $datos): bool
{
    foreach ($datos as $dato) {
        if (trim((string) $dato) !== '') {
            return false;
        }
    }
    return true;
}

function limpiarTextoSimple(string $texto): string
{
    $texto = normalizarCodificacion($texto);
    $texto = trim($texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    $texto = str_replace(
        [
            'ñ', 'Ñ', '–',
            'á', 'Á', 'Ã¡',
            'é', 'É', 'Ã©',
            'í', 'Í', 'Ã­',
            'ó', 'Ó', 'Ã³',
            'ú', 'Ú', 'Ãº',
            "'", "’"
        ],
        [
            'n', 'N', 'n',
            'a', 'A', 'a',
            'e', 'E', 'e',
            'i', 'I', 'i',
            'o', 'O', 'o',
            'u', 'U', 'u',
            ' ', ' '
        ],
        $texto
    );
    return $texto ?? '';
}

function normalizarCodificacion(string $texto): string
{
    $texto = str_replace("\xEF\xBB\xBF", '', $texto);

    if ($texto === '') {
        return '';
    }

    if (preg_match('//u', $texto) !== 1) {
        $convertido = iconv('Windows-1252', 'UTF-8//IGNORE', $texto);

        if ($convertido !== false) {
            $texto = $convertido;
        }
    }

    return $texto;
}

// function limpiarTextoSimple(string $texto): string
// {


//     $texto = trim($texto);

//     // tildes y diacriticos
//     $texto = str_replace(
//         [
//             'ñ', 'Ñ', '�',
//             'á', 'Á', 'Ã¡',
//             'é', 'É', 'Ã©',
//             'í', 'Í', 'Ã­',
//             'ó', 'Ó', 'Ã³',
//             'ú', 'Ú', 'Ãº',
//             "'", "’"
//         ],
//         [
//             'n', 'N', 'n',
//             'a', 'A', 'a',
//             'e', 'E', 'e',
//             'i', 'I', 'i',
//             'o', 'O', 'o',
//             'u', 'U', 'u',
//             ' ', ' '
//         ],
//         $texto
//     );

//     $texto = preg_replace('/\s+/', ' ', $texto);
//     return trim($texto);
// }

function campoExiste(array $fila, array $opciones): ?string
{
    foreach ($opciones as $opcion) {
        if (array_key_exists($opcion, $fila)) {
            return $opcion;
        }
    }
    return null;
}

function agregarLog(
    array &$logs,
    string $campo,
    string $accion,
    string $original,
    string $nuevo,
    string $detalle
): void {
    $logs[] = [
        'campo' => $campo,
        'accion' => $accion,
        'original' => $original,
        'nuevo' => $nuevo,
        'detalle' => $detalle,
    ];
}

function cambiarValor(
    array &$fila,
    string $campo,
    string $nuevo,
    array &$logs,
    string $accion,
    string $detalle
): void {
    $original = (string) ($fila[$campo] ?? '');
    if ($original !== $nuevo) {
        agregarLog($logs, $campo, $accion, $original, $nuevo, $detalle);
        $fila[$campo] = $nuevo;
    }
}

function limpiarCampoTexto(
    array &$fila,
    array $opcionesCampo,
    bool $obligatorio,
    array &$logs,
    bool &$descartar,
    bool $esClave = false
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];
    $nuevo = limpiarTextoSimple($original);

    if ($nuevo === '' && $obligatorio) {
        if ($esClave) {
            agregarLog($logs, $campo, 'eliminacion', $original, '', 'Campo obligatorio clave vacío');
            $descartar = true;
            return;
        }
        $nuevo = 'NO_VALIDO';
        cambiarValor($fila, $campo, $nuevo, $logs, 'asignacion', 'Campo obligatorio vacío');
        return;
    }

    cambiarValor($fila, $campo, $nuevo, $logs, 'correccion', 'Se eliminaron espacios sobrantes');
}

function limpiarCampoEntero(
    array &$fila,
    array $opcionesCampo,
    bool $obligatorio,
    array &$logs,
    bool &$descartar,
    int $valorDefecto = 0,
    bool $esClave = false
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];
    if ($original === '') {
        if ($obligatorio) {
            if ($esClave) {
                agregarLog($logs, $campo, 'eliminacion', $original, '', 'Entero obligatorio clave vacío');
                $descartar = true;
                return;
            }
            cambiarValor($fila, $campo, (string) $valorDefecto, $logs, 'asignacion', 'Entero obligatorio vacío');
        }
        return;
    }

    $entero = convertirEntero($original);
    if ($entero === null) {
        if ($obligatorio) {
            if ($esClave) {
                agregarLog($logs, $campo, 'eliminacion', $original, '', 'Entero obligatorio irreparable');
                $descartar = true;
                return;
            }
            cambiarValor($fila, $campo, (string) $valorDefecto, $logs, 'asignacion', 'Entero obligatorio inválido');
        } else {
            cambiarValor($fila, $campo, '', $logs, 'asignacion', 'Entero opcional inválido');
        }
        return;
    }

    cambiarValor($fila, $campo, (string) $entero, $logs, 'correccion', 'Entero normalizado');
}

function convertirEntero(string $valor): ?int
{
    $valorOriginal = $valor;
    $valor = strtolower(trim($valor));

    $diccionario = [
        'cero' => 0,
        'mil' => 1000,
        'dos mil' => 2000,
        'tres mil' => 3000,
        'cuatro mil' => 4000,
        'cinco mil' => 5000,
        'seis mil' => 6000,
        'siete mil' => 7000,
        'ocho mil' => 8000,
        'nueve mil' => 9000,
        'diez mil' => 10000,
        'once mil' => 11000,
        'doce mil' => 12000,
        'quince mil' => 15000,
        'veinte mil' => 20000,
    ];

    if (array_key_exists($valor, $diccionario)) {
        return $diccionario[$valor];
    }

    $valor = str_replace(['$', ' ', '.'], '', $valorOriginal);
    $valor = str_replace(',', '.', $valor);

    if (preg_match('/^-?\d+(\.0+)?$/', $valor) !== 1) {
        return null;
    }

    return (int) $valor;
}

function limpiarCampoDecimal(
    array &$fila,
    array $opcionesCampo,
    bool $obligatorio,
    array &$logs,
    bool &$descartar,
    float $valorDefecto = 0.0
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];
    if ($original === '') {
        if ($obligatorio) {
            cambiarValor($fila, $campo, (string) $valorDefecto, $logs, 'asignacion', 'Decimal obligatorio vacío');
        }
        return;
    }

    $normalizado = str_replace(',', '.', $original);
    if (!is_numeric($normalizado)) {
        cambiarValor($fila, $campo, $obligatorio ? (string) $valorDefecto : '', $logs, 'asignacion', 'Decimal inválido');
        return;
    }

    $numero = (float) $normalizado;
    if ($numero < 0) {
        $numero = 0;
    }
    if ($numero > 100) {
        $numero = 100;
    }

    cambiarValor($fila, $campo, formatoDecimal($numero), $logs, 'correccion', 'Decimal normalizado al rango 0-100');
}

function formatoDecimal(float $numero): string
{
    $texto = rtrim(rtrim(number_format($numero, 2, '.', ''), '0'), '.');
    return $texto === '' ? '0' : $texto;
}

function limpiarCampoFecha(
    array &$fila,
    array $opcionesCampo,
    bool $obligatorio,
    array &$logs,
    bool &$descartar,
    string $valorDefecto = '1900-01-01',
    bool $esClave = false
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];
    if ($original === '') {
        if ($obligatorio) {
            if ($esClave) {
                agregarLog($logs, $campo, 'eliminacion', $original, '', 'Fecha obligatoria clave vacía');
                $descartar = true;
                return;
            }
            cambiarValor($fila, $campo, $valorDefecto, $logs, 'asignacion', 'Fecha obligatoria vacía');
        }
        return;
    }

    $fecha = convertirFecha($original);
    if ($fecha === null) {
        if ($obligatorio) {
            if ($esClave) {
                agregarLog($logs, $campo, 'eliminacion', $original, '', 'Fecha obligatoria irreparable');
                $descartar = true;
                return;
            }
            cambiarValor($fila, $campo, $valorDefecto, $logs, 'asignacion', 'Fecha obligatoria inválida');
        } else {
            cambiarValor($fila, $campo, '', $logs, 'asignacion', 'Fecha opcional inválida');
        }
        return;
    }

    cambiarValor($fila, $campo, $fecha, $logs, 'correccion', 'Fecha normalizada a YYYY-MM-DD');
}

function limpiarCampoFechaNacimiento(
    array &$fila,
    array $opcionesCampo,
    array &$logs,
    bool &$descartar
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];

    if ($original === '') {
        return;
    }

    $fecha = convertirFecha($original);

    if ($fecha === null) {
        cambiarValor(
            $fila,
            $campo,
            '',
            $logs,
            'asignacion',
            'Fecha de nacimiento inválida'
        );
        return;
    }

    $fechaNacimiento = DateTime::createFromFormat('Y-m-d', $fecha);
    $hoy = new DateTime();

    if ($fechaNacimiento === false || $fechaNacimiento > $hoy) {
        cambiarValor(
            $fila,
            $campo,
            '',
            $logs,
            'asignacion',
            'Fecha de nacimiento futura imposible'
        );
        return;
    }

    cambiarValor(
        $fila,
        $campo,
        $fecha,
        $logs,
        'correccion',
        'Fecha de nacimiento normalizada a YYYY-MM-DD'
    );
}

function convertirFecha(string $valor): ?string
{
    $valor = trim($valor);
    $valor = str_replace(['/', '.'], '-', $valor);

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $valor, $m) === 1) {
        return validarFecha((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{2}|\d{4})$/', $valor, $m) === 1) {
        $anio = (int) $m[3];
        if ($anio < 100) {
            $anio += ($anio < 50) ? 2000 : 1900;
        }
        return validarFecha($anio, (int) $m[2], (int) $m[1]);
    }

    if (preg_match('/^(\d{4})-(\d{1,2})$/', $valor, $m) === 1) {
        return validarFecha((int) $m[1], (int) $m[2], 1);
    }

    return null;
}

function validarFecha(int $anio, int $mes, int $dia): ?string
{
    if (!checkdate($mes, $dia, $anio)) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
}

function limpiarCampoFechaHora(
    array &$fila,
    array $opcionesCampo,
    bool $obligatorio,
    array &$logs,
    bool &$descartar,
    string $valorDefecto = '1900-01-01 00:00'
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];
    if ($original === '') {
        if ($obligatorio) {
            cambiarValor($fila, $campo, $valorDefecto, $logs, 'asignacion', 'Fecha-hora obligatoria vacía');
        }
        return;
    }

    $fechaHora = convertirFechaHora($original);
    if ($fechaHora === null) {
        if ($obligatorio) {
            cambiarValor($fila, $campo, $valorDefecto, $logs, 'asignacion', 'Fecha-hora obligatoria inválida');
        } else {
            cambiarValor($fila, $campo, '', $logs, 'asignacion', 'Fecha-hora opcional inválida');
        }
        return;
    }

    cambiarValor($fila, $campo, $fechaHora, $logs, 'correccion', 'Fecha-hora normalizada a YYYY-MM-DD HH:MM');
}

function convertirFechaHora(string $valor): ?string
{
    $valor = trim($valor);
    $partes = preg_split('/\s+/', $valor);
    if ($partes === false || count($partes) === 0) {
        return null;
    }

    $fecha = convertirFecha($partes[0]);
    if ($fecha === null) {
        return null;
    }

    $hora = '00:00';
    if (count($partes) >= 2) {
        $hora = convertirHora($partes[1]);
        if ($hora === null) {
            return null;
        }
    }

    return $fecha . ' ' . $hora;
}

function limpiarCampoHora(
    array &$fila,
    array $opcionesCampo,
    bool $obligatorio,
    array &$logs,
    bool &$descartar,
    string $valorDefecto = '00:00'
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];
    if ($original === '') {
        if ($obligatorio) {
            cambiarValor($fila, $campo, $valorDefecto, $logs, 'asignacion', 'Hora obligatoria vacía');
        }
        return;
    }

    $hora = convertirHora($original);
    if ($hora === null) {
        if ($obligatorio) {
            cambiarValor($fila, $campo, $valorDefecto, $logs, 'asignacion', 'Hora obligatoria inválida');
        } else {
            cambiarValor($fila, $campo, '', $logs, 'asignacion', 'Hora opcional inválida');
        }
        return;
    }

    cambiarValor($fila, $campo, $hora, $logs, 'correccion', 'Hora normalizada a HH:MM');
}

function convertirHora(string $valor): ?string
{
    $valor = trim($valor);
    if (preg_match('/^(\d{1,2}):(\d{1,2})(?::\d{1,2})?$/', $valor, $m) !== 1) {
        return null;
    }
    $hora = (int) $m[1];
    $minuto = (int) $m[2];
    if ($hora < 0 || $hora > 23 || $minuto < 0 || $minuto > 59) {
        return null;
    }
    return sprintf('%02d:%02d', $hora, $minuto);
}

function limpiarCampoRun(
    array &$fila,
    array $opcionesCampo,
    bool $obligatorio,
    array &$logs,
    bool &$descartar,
    bool $esClave = false
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];
    if ($original === '') {
        if ($obligatorio) {
            if ($esClave) {
                agregarLog($logs, $campo, 'eliminacion', $original, '', 'RUN obligatorio clave vacío');
                $descartar = true;
                return;
            }
            cambiarValor($fila, $campo, '10000000-8', $logs, 'asignacion', 'RUN obligatorio vacío');
        }
        return;
    }

    $run = normalizarRun($original);
    if ($run === null || !validarRun($run)) {
        if ($obligatorio) {
            if ($esClave) {
                agregarLog($logs, $campo, 'eliminacion', $original, '', 'RUN obligatorio inválido');
                $descartar = true;
                return;
            }
            cambiarValor($fila, $campo, '10000000-8', $logs, 'asignacion', 'RUN obligatorio inválido');
        } else {
            cambiarValor($fila, $campo, '', $logs, 'asignacion', 'RUN opcional inválido');
        }
        return;
    }

    cambiarValor($fila, $campo, $run, $logs, 'correccion', 'RUN normalizado y validado');
}

function normalizarRun(string $run): ?string
{
    $run = strtoupper(trim($run));
    $run = str_replace(['.', ' ', '–', '—'], ['', '', '-', '-'], $run);

    if (strpos($run, '-') === false && strlen($run) >= 2) {
        $run = substr($run, 0, -1) . '-' . substr($run, -1);
    }

    if (preg_match('/^(\d{1,9})-([0-9K])$/', $run, $m) !== 1) {
        return null;
    }

    return ltrim($m[1], '0') . '-' . $m[2];
}

function validarRun(string $run): bool
{
    if (preg_match('/^(\d{1,9})-([0-9K])$/', strtoupper($run), $m) !== 1) {
        return false;
    }

    $numero = $m[1];
    $dv = $m[2];
    $suma = 0;
    $multiplicador = 2;

    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += ((int) $numero[$i]) * $multiplicador;
        $multiplicador++;
        if ($multiplicador > 7) {
            $multiplicador = 2;
        }
    }

    $resto = $suma % 11;
    $digito = 11 - $resto;
    if ($digito === 11) {
        $esperado = '0';
    } elseif ($digito === 10) {
        $esperado = 'K';
    } else {
        $esperado = (string) $digito;
    }

    return $dv === $esperado;
}

function limpiarCampoEmail(
    array &$fila,
    array $opcionesCampo,
    bool $obligatorio,
    array &$logs,
    bool &$descartar
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];
    if ($original === '') {
        if ($obligatorio) {
            cambiarValor($fila, $campo, 'sin_correo@no.valido', $logs, 'asignacion', 'Email obligatorio vacío');
        }
        return;
    }

    $nuevo = strtolower(trim($original));
    while (strpos($nuevo, '..') !== false) {
        $nuevo = str_replace('..', '.', $nuevo);
    }

    if (!filter_var($nuevo, FILTER_VALIDATE_EMAIL)) {
        if ($obligatorio) {
            cambiarValor($fila, $campo, 'sin_correo@no.valido', $logs, 'asignacion', 'Email obligatorio inválido');
        } else {
            cambiarValor($fila, $campo, '', $logs, 'asignacion', 'Email opcional inválido');
        }
        return;
    }

    cambiarValor($fila, $campo, $nuevo, $logs, 'correccion', 'Email normalizado');
}

function limpiarCampoTelefono(
    array &$fila,
    array $opcionesCampo,
    bool $obligatorio,
    array &$logs,
    bool &$descartar
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];
    if ($original === '') {
        if ($obligatorio) {
            cambiarValor($fila, $campo, '100000000', $logs, 'asignacion', 'Teléfono obligatorio vacío');
        }
        return;
    }

    $telefono = preg_replace('/\D/', '', $original) ?? '';
    if (strlen($telefono) === 8) {
        $telefono = '9' . $telefono;
    }

    if (strlen($telefono) !== 9) {
        if ($obligatorio) {
            cambiarValor($fila, $campo, '100000000', $logs, 'asignacion', 'Teléfono obligatorio inválido');
        } else {
            cambiarValor($fila, $campo, '', $logs, 'asignacion', 'Teléfono opcional inválido');
        }
        return;
    }

    cambiarValor($fila, $campo, $telefono, $logs, 'correccion', 'Teléfono normalizado a 9 dígitos');
}

function limpiarCampoEnum(
    array &$fila,
    array $opcionesCampo,
    array $permitidos,
    array $mapa,
    bool $obligatorio,
    array &$logs,
    bool &$descartar,
    string $valorDefecto = 'NO_VALIDO'
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];
    $normalizado = strtolower(trim($original));
    $normalizado = str_replace('_', ' ', $normalizado);
    $normalizado = $mapa[$normalizado] ?? $normalizado;

    if ($normalizado === '') {
        if ($obligatorio) {
            cambiarValor($fila, $campo, $valorDefecto, $logs, 'asignacion', 'Enum obligatorio vacío');
        }
        return;
    }

    if (!in_array($normalizado, $permitidos, true)) {
        if ($obligatorio) {
            cambiarValor($fila, $campo, $valorDefecto, $logs, 'asignacion', 'Enum obligatorio inválido');
        } else {
            cambiarValor($fila, $campo, '', $logs, 'asignacion', 'Enum opcional inválido');
        }
        return;
    }

    cambiarValor($fila, $campo, $normalizado, $logs, 'correccion', 'Enum normalizado');
}

function limpiarCampoSiNo(
    array &$fila,
    array $opcionesCampo,
    bool $obligatorio,
    array &$logs,
    bool &$descartar
): void {
    $campo = campoExiste($fila, $opcionesCampo);
    if ($campo === null) {
        return;
    }

    $original = (string) $fila[$campo];
    $valor = strtoupper(trim($original));

    $mapa = [
        'SÍ' => 'SI',
        'SI' => 'SI',
        'S' => 'SI',
        'YES' => 'SI',
        'Y' => 'SI',
        'NO' => 'NO',
        'N' => 'NO',
    ];

    if ($valor === '') {
        if ($obligatorio) {
            cambiarValor($fila, $campo, 'NO', $logs, 'asignacion', 'Campo SI/NO obligatorio vacío');
        }
        return;
    }

    $nuevo = $mapa[$valor] ?? null;
    if ($nuevo === null) {
        cambiarValor($fila, $campo, 'NO', $logs, 'asignacion', 'Campo SI/NO inválido');
        return;
    }

    cambiarValor($fila, $campo, $nuevo, $logs, 'correccion', 'Campo SI/NO normalizado');
}

function limpiarRegionesComunas(array &$fila, array &$logs, bool &$descartar): void
{
    limpiarCampoEntero($fila, ['Código Comuna', 'codigo_comuna', 'codigo comuna'], true, $logs, $descartar, 0, true);
    limpiarCampoTexto($fila, ['Nombre Comuna', 'nombre_comuna', 'nombre comuna'], true, $logs, $descartar);
    limpiarCampoEntero($fila, ['Código Región', 'codigo_region', 'codigo region'], true, $logs, $descartar, 0, true);
    limpiarCampoTexto($fila, ['Nombre Región', 'nombre_region', 'nombre region'], true, $logs, $descartar);
}

function limpiarPersonas(array &$fila, array &$logs, bool &$descartar): void
{
    limpiarCampoRun($fila, ['run_persona', 'run persona'], true, $logs, $descartar, true);
    limpiarCampoTexto($fila, ['nombre_completo', 'nombre completo'], true, $logs, $descartar);
    limpiarCampoEmail($fila, ['email', 'correo'], false, $logs, $descartar);
    limpiarCampoTelefono($fila, ['telefono_celular', 'telefono celular'], false, $logs, $descartar);
    limpiarCampoTelefono($fila, ['telefono_alternativo', 'telefono alternativo'], false, $logs, $descartar);
    limpiarCampoTexto($fila, ['direccion_calle', 'direccion calle'], false, $logs, $descartar);
    limpiarCampoTexto($fila, ['comuna_nombre', 'comuna nombre'], true, $logs, $descartar);
    limpiarCampoEntero($fila, ['region_codigo', 'region codigo'], true, $logs, $descartar, 0);
    limpiarCampoTexto($fila, ['region_nombre', 'region nombre'], true, $logs, $descartar);
    limpiarCampoEnum($fila, ['tipo_persona', 'tipo persona'], ['socio titular', 'beneficiario', 'adicional', 'invitado', 'administrativo'], [], true, $logs, $descartar);
    limpiarCampoRun($fila, ['run_socio_titular', 'run socio titular'], false, $logs, $descartar);
    limpiarCampoEnum($fila, ['parentesco'], ['conyuge', 'hijo/a', 'hijo', 'hija'], ['cónyuge' => 'conyuge'], false, $logs, $descartar);
    limpiarCampoFechaNacimiento($fila, ['fecha_nacimiento', 'fecha nacimiento'], $logs, $descartar);
    limpiarCampoFecha($fila, ['fecha_inicio_membresia', 'fecha inicio membresia'], false, $logs, $descartar);
    limpiarCampoFecha($fila, ['fecha_fin_membresia', 'fecha fin membresia'], false, $logs, $descartar);
    limpiarCampoSiNo($fila, ['es_usuario_sistema', 'es usuario sistema'], true, $logs, $descartar);
    limpiarCampoEnum($fila, ['tipo_usuario', 'tipo usuario'], ['admin', 'administrativo', 'socio', 'nulo'], ['' => 'nulo'], false, $logs, $descartar);
    limpiarCampoTexto($fila, ['clave_en_texto_plano', 'clave en texto plano'], false, $logs, $descartar);
    limpiarCampoTexto($fila, ['sucursal_base_nombre', 'sucursal base nombre'], true, $logs, $descartar);
}

function limpiarSucursalesLugares(array &$fila, array &$logs, bool &$descartar): void
{
    limpiarCampoTexto($fila, ['sucursal_nombre', 'sucursal nombre'], true, $logs, $descartar, true);
    limpiarCampoTexto($fila, ['direccion_sucursal', 'direccion sucursal'], true, $logs, $descartar);
    limpiarCampoTexto($fila, ['comuna_nombre', 'comuna nombre'], true, $logs, $descartar);
    limpiarCampoTexto($fila, ['lugar_nombre', 'lugar nombre'], true, $logs, $descartar, true);
    limpiarCampoTexto($fila, ['tipo_lugar', 'tipo lugar'], true, $logs, $descartar);
    limpiarCampoEntero($fila, ['capacidad_personas', 'capacidad personas'], true, $logs, $descartar, 0);
    limpiarCampoEntero($fila, ['precio'], true, $logs, $descartar, 0);
    limpiarCampoDecimal($fila, ['descuento_socio_evento', 'descuento socio evento'], false, $logs, $descartar);
    limpiarCampoEnum($fila, ['tipo_precio', 'tipo precio'], ['hora', 'dia'], ['mesa hora' => 'hora', 'mesa_hora' => 'hora', 'día' => 'dia'], false, $logs, $descartar);
    limpiarCampoTexto($fila, ['dia_semana', 'dia semana'], false, $logs, $descartar);
    limpiarCampoHora($fila, ['hora_inicio', 'hora inicio'], false, $logs, $descartar);
    limpiarCampoHora($fila, ['hora_termino', 'hora termino'], false, $logs, $descartar);
    limpiarCampoFecha($fila, ['fecha_inicio_vigencia', 'fecha inicio vigencia'], false, $logs, $descartar);
    limpiarCampoFecha($fila, ['fecha_fin_vigencia', 'fecha fin vigencia'], false, $logs, $descartar);
}

function limpiarReservasArriendos(array &$fila, array &$logs, bool &$descartar): void
{
    limpiarCampoTexto($fila, ['codigo_reserva', 'codigo reserva'], true, $logs, $descartar, true);
    limpiarCampoFecha($fila, ['fecha_reserva', 'fecha reserva'], true, $logs, $descartar);
    limpiarCampoFechaHora($fila, ['fecha_inicio', 'fecha inicio'], true, $logs, $descartar);
    limpiarCampoFechaHora($fila, ['fecha_fin', 'fecha fin'], true, $logs, $descartar);
    limpiarCampoEnum($fila, ['estado_reserva', 'estado reserva'], ['reservada', 'ejecutada', 'cancelada'], [], true, $logs, $descartar);
    limpiarCampoRun($fila, ['run_reservante', 'run reservante'], true, $logs, $descartar);
    limpiarCampoTexto($fila, ['nombre_reservante', 'nombre reservante'], true, $logs, $descartar);
    limpiarCampoSiNo($fila, ['es_socio', 'es socio'], true, $logs, $descartar);
    limpiarCampoTexto($fila, ['lugar_nombre', 'lugar nombre'], true, $logs, $descartar);
    limpiarCampoTexto($fila, ['sucursal_nombre', 'sucursal nombre'], true, $logs, $descartar);
    limpiarCampoEntero($fila, ['monto_total', 'monto total'], true, $logs, $descartar, 0);
    limpiarCampoEntero($fila, ['monto_pagado', 'monto pagado'], false, $logs, $descartar, 0);
    limpiarCampoTexto($fila, ['medio_pago', 'medio pago'], false, $logs, $descartar);
    limpiarCampoFecha($fila, ['fecha_pago', 'fecha pago'], false, $logs, $descartar);
}

function limpiarEventos(array &$fila, array &$logs, bool &$descartar): void
{
    limpiarCampoTexto($fila, ['evento_id', 'codigo_evento', 'codigo evento'], true, $logs, $descartar, true);
    limpiarCampoTexto($fila, ['nombre_evento', 'nombre evento'], true, $logs, $descartar);
    limpiarCampoFecha($fila, ['fecha_contratación', 'fecha_contratacion', 'fecha contratación'], false, $logs, $descartar);
    limpiarCampoFecha($fila, ['fecha_evento', 'fecha evento'], true, $logs, $descartar);
    limpiarCampoTexto($fila, ['lugar_nombre', 'lugar nombre'], true, $logs, $descartar);
    limpiarCampoTexto($fila, ['sucursal_nombre', 'sucursal nombre'], true, $logs, $descartar);
    limpiarCampoEnum($fila, ['tipo_cliente', 'tipo cliente'], ['socio', 'persona', 'empresa'], ['empresa institucion' => 'empresa', 'empresa-institucion' => 'empresa'], true, $logs, $descartar);
    limpiarCampoRun($fila, ['run_cliente', 'rut_cliente', 'rut cliente'], true, $logs, $descartar);
    limpiarCampoTexto($fila, ['nombre_cliente', 'nombre cliente'], true, $logs, $descartar);
    limpiarCampoRun($fila, ['run_contacto_empresa', 'rut_contacto_empresa', 'run contacto empresa'], false, $logs, $descartar);
    limpiarCampoTexto($fila, ['nombre_contacto_empresa', 'nombre contacto empresa'], false, $logs, $descartar);
    limpiarCampoTexto($fila, ['cargo_contacto'], false, $logs, $descartar);
    limpiarCampoTexto($fila, ['lista_asistentes', 'asistentes_texto', 'asistentes texto'], false, $logs, $descartar);
    limpiarCampoEntero($fila, ['monto_total_evento', 'monto total evento'], true, $logs, $descartar, 0);
    limpiarCampoEntero($fila, ['monto_pagado_reserva', 'monto pagado reserva'], false, $logs, $descartar, 0);
    limpiarCampoEntero($fila, ['monto_pagado_ejecucion', 'monto pagado ejecucion'], false, $logs, $descartar, 0);
}

function limpiarPagosMembresias(array &$fila, array &$logs, bool &$descartar): void
{
    limpiarCampoTexto($fila, ['pago_id', 'codigo_pago', 'codigo pago'], false, $logs, $descartar);
    limpiarCampoRun($fila, ['run_socio_titular', 'run socio titular'], true, $logs, $descartar);
    limpiarCampoTexto($fila, ['nombre_socio_titular', 'nombre_socio', 'nombre socio'], true, $logs, $descartar);
    limpiarCampoEntero($fila, ['anio_membresia', 'anio membresia'], true, $logs, $descartar, 2025);
    limpiarCampoEntero($fila, ['mes_cuota', 'mes cuota'], true, $logs, $descartar, 1);
    limpiarCampoFecha($fila, ['fecha_vencimiento', 'fecha vencimiento'], true, $logs, $descartar);
    limpiarCampoEntero($fila, ['monto_membresia', 'monto membresia'], true, $logs, $descartar, 0);
    limpiarCampoEntero($fila, ['monto_adicionales', 'monto adicionales'], false, $logs, $descartar, 0);
    limpiarCampoEntero($fila, ['monto_total', 'monto total'], true, $logs, $descartar, 0);
    limpiarCampoEnum($fila, ['estado_pago', 'estado pago'], ['pagado', 'atrasado'], [], true, $logs, $descartar);
    limpiarCampoFecha($fila, ['fecha_pago', 'fecha pago'], false, $logs, $descartar);
    limpiarCampoTexto($fila, ['medio_pago', 'medio pago'], false, $logs, $descartar);
}

function limpiarCargosAdministrativos(array &$fila, array &$logs, bool &$descartar): void
{
    limpiarCampoRun($fila, ['run_persona', 'run persona'], true, $logs, $descartar);
    limpiarCampoTexto($fila, ['sucursal_nombre', 'sucursal nombre'], true, $logs, $descartar);
    limpiarCampoTexto($fila, ['nombre_cargo', 'nombre cargo'], true, $logs, $descartar);
    limpiarCampoFecha($fila, ['fecha_inicio_cargo', 'fecha inicio cargo'], true, $logs, $descartar);
    limpiarCampoFecha($fila, ['fecha_termino_cargo', 'fecha termino cargo'], false, $logs, $descartar);
}
