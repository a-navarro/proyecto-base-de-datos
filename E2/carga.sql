\set ON_ERROR_STOP on

-- carga.sql corregido según issue:
-- NO crea el esquema DCColo.
-- Asume que DCColo.sql ya fue ejecutado antes.
-- Este archivo solo carga la instancia desde los archivos XXXOK.csv.

SET search_path TO public;

CREATE OR REPLACE FUNCTION pg_temp.limpio(valor text)
RETURNS text AS $$
    SELECT NULLIF(btrim(valor), '');
$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION pg_temp.to_int(valor text)
RETURNS integer AS $$
DECLARE
    v text;
BEGIN
    v := pg_temp.limpio(valor);
    IF v IS NULL THEN
        RETURN NULL;
    END IF;
    IF v ~ '^-?[0-9]+$' THEN
        RETURN v::integer;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION pg_temp.to_fecha(valor text)
RETURNS date AS $$
DECLARE
    v text;
    d date;
BEGIN
    v := pg_temp.limpio(valor);
    IF v IS NULL THEN
        RETURN NULL;
    END IF;

    BEGIN
        IF v ~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' THEN
            d := to_date(v, 'YYYY-MM-DD');
            IF to_char(d, 'YYYY-MM-DD') = v THEN
                RETURN d;
            END IF;
        ELSIF v ~ '^[0-9]{2}-[0-9]{2}-[0-9]{4}$' THEN
            d := to_date(v, 'DD-MM-YYYY');
            IF to_char(d, 'DD-MM-YYYY') = v THEN
                RETURN d;
            END IF;
        ELSIF v ~ '^[0-9]{2}-[0-9]{2}-[0-9]{2}$' THEN
            d := to_date(v, 'DD-MM-YY');
            IF to_char(d, 'DD-MM-YY') = v THEN
                RETURN d;
            END IF;
        END IF;
    EXCEPTION WHEN others THEN
        RETURN NULL;
    END;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION pg_temp.to_hora(valor text)
RETURNS time AS $$
DECLARE
    v text;
BEGIN
    v := pg_temp.limpio(valor);
    IF v IS NULL THEN
        RETURN NULL;
    END IF;

    BEGIN
        RETURN v::time;
    EXCEPTION WHEN others THEN
        RETURN NULL;
    END;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION pg_temp.to_timestamp_ok(valor text)
RETURNS timestamp AS $$
DECLARE
    v text;
    t timestamp;
BEGIN
    v := pg_temp.limpio(valor);
    IF v IS NULL THEN
        RETURN NULL;
    END IF;

    BEGIN
        IF v ~ '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$' THEN
            t := to_timestamp(v, 'YYYY-MM-DD HH24:MI');
            IF to_char(t, 'YYYY-MM-DD HH24:MI') = v THEN
                RETURN t;
            END IF;
        ELSIF v ~ '^[0-9]{2}-[0-9]{2}-[0-9]{4} [0-9]{2}:[0-9]{2}$' THEN
            t := to_timestamp(v, 'DD-MM-YYYY HH24:MI');
            IF to_char(t, 'DD-MM-YYYY HH24:MI') = v THEN
                RETURN t;
            END IF;
        ELSIF v ~ '^[0-9]{2}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$' THEN
            t := to_timestamp(v, 'DD-MM-YY HH24:MI');
            IF to_char(t, 'DD-MM-YY HH24:MI') = v THEN
                RETURN t;
            END IF;
        END IF;
    EXCEPTION WHEN others THEN
        RETURN NULL;
    END;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- Permite re-ejecutar carga.sql durante pruebas sin duplicar la instancia.
TRUNCATE TABLE
    asistente_evento,
    cargo,
    comuna,
    contacto_empresa,
    cuota,
    empresa,
    evento,
    lugar,
    membresia,
    pago_cuota,
    pago_evento,
    pago_reserva,
    persona,
    persona_cargo,
    precio_lugar,
    region,
    relacion_socio,
    reserva,
    socio,
    sucursal,
    usuario
RESTART IDENTITY CASCADE;

CREATE TEMP TABLE errores_carga (
    archivo text,
    fila text,
    motivo text,
    accion text
);

CREATE TEMP TABLE stg_regiones_comunas (
    codigo_comuna text,
    nombre_comuna text,
    codigo_region text,
    nombre_region text
);

CREATE TEMP TABLE stg_sucursales_lugares (
    sucursal_nombre text,
    direccion_sucursal text,
    comuna_nombre text,
    lugar_nombre text,
    tipo_lugar text,
    capacidad_personas text,
    precio text,
    descuento_socio_evento text,
    tipo_precio text,
    dia_semana text,
    hora_inicio text,
    hora_termino text,
    fecha_inicio_vigencia text,
    fecha_fin_vigencia text
);

CREATE TEMP TABLE stg_personas_socios (
    run_persona text,
    nombre_completo text,
    email text,
    telefono_celular text,
    telefono_alternativo text,
    direccion_calle text,
    comuna_nombre text,
    region_codigo text,
    region_nombre text,
    tipo_persona text,
    run_socio_titular text,
    parentesco text,
    fecha_nacimiento text,
    fecha_inicio_membresia text,
    fecha_fin_membresia text,
    es_usuario_sistema text,
    tipo_usuario text,
    clave_en_texto_plano text,
    sucursal_base_nombre text
);

CREATE TEMP TABLE stg_reservas_arriendos (
    codigo_reserva text,
    fecha_reserva text,
    fecha_inicio text,
    fecha_fin text,
    estado_reserva text,
    run_reservante text,
    nombre_reservante text,
    es_socio text,
    lugar_nombre text,
    sucursal_nombre text,
    monto_total text,
    monto_pagado text,
    medio_pago text,
    fecha_pago text
);

CREATE TEMP TABLE stg_eventos (
    evento_id text,
    nombre_evento text,
    fecha_contratacion text,
    fecha_evento text,
    lugar_nombre text,
    sucursal_nombre text,
    tipo_cliente text,
    run_cliente text,
    nombre_cliente text,
    rut_contacto_empresa text,
    nombre_contacto_empresa text,
    cargo_contacto text,
    lista_asistentes text,
    monto_total_evento text,
    monto_pagado_reserva text,
    monto_pagado_ejecucion text
);

CREATE TEMP TABLE stg_pagos_membresias (
    pago_id text,
    run_socio_titular text,
    nombre_socio_titular text,
    anio_membresia text,
    mes_cuota text,
    fecha_vencimiento text,
    monto_membresia text,
    monto_adicionales text,
    monto_total text,
    estado_pago text,
    fecha_pago text,
    medio_pago text
);

CREATE TEMP TABLE stg_cargos_administrativos (
    run_persona text,
    sucursal_nombre text,
    nombre_cargo text,
    fecha_inicio_cargo text,
    fecha_termino_cargo text
);

\copy stg_regiones_comunas FROM 'regiones_comunasOK.csv' WITH (FORMAT csv, HEADER true, DELIMITER ';', QUOTE '"')
\copy stg_sucursales_lugares FROM 'sucursales_lugaresOK.csv' WITH (FORMAT csv, HEADER true, DELIMITER ';', QUOTE '"')
\copy stg_personas_socios FROM 'personas_sociosOK.csv' WITH (FORMAT csv, HEADER true, DELIMITER ';', QUOTE '"')
\copy stg_reservas_arriendos FROM 'reservas_arriendosOK.csv' WITH (FORMAT csv, HEADER true, DELIMITER ';', QUOTE '"')
\copy stg_eventos FROM 'eventosOK.csv' WITH (FORMAT csv, HEADER true, DELIMITER ';', QUOTE '"')
\copy stg_pagos_membresias FROM 'pagos_membresiasOK.csv' WITH (FORMAT csv, HEADER true, DELIMITER ';', QUOTE '"')
\copy stg_cargos_administrativos FROM 'cargos_administrativosOK.csv' WITH (FORMAT csv, HEADER true, DELIMITER ';', QUOTE '"')

-- 1. Regiones y comunas
INSERT INTO region (codigo_region, nombre)
SELECT DISTINCT
    pg_temp.to_int(codigo_region),
    left(pg_temp.limpio(nombre_region), 100)
FROM stg_regiones_comunas
WHERE pg_temp.to_int(codigo_region) IS NOT NULL
  AND pg_temp.limpio(nombre_region) IS NOT NULL;

INSERT INTO comuna (codigo_comuna, nombre, codigo_region)
SELECT DISTINCT
    pg_temp.to_int(codigo_comuna),
    left(pg_temp.limpio(nombre_comuna), 100),
    pg_temp.to_int(codigo_region)
FROM stg_regiones_comunas
WHERE pg_temp.to_int(codigo_comuna) IS NOT NULL
  AND pg_temp.limpio(nombre_comuna) IS NOT NULL
  AND pg_temp.to_int(codigo_region) IN (SELECT codigo_region FROM region);

INSERT INTO errores_carga
SELECT
    'regiones_comunasOK.csv',
    row_to_json(stg_regiones_comunas)::text,
    'region o comuna invalida',
    'no se cargo la tupla'
FROM stg_regiones_comunas
WHERE pg_temp.to_int(codigo_comuna) IS NULL
   OR pg_temp.limpio(nombre_comuna) IS NULL
   OR pg_temp.to_int(codigo_region) IS NULL
   OR pg_temp.limpio(nombre_region) IS NULL;

-- 2. Sucursales y lugares
CREATE TEMP TABLE map_sucursal AS
SELECT
    'S' || lpad(row_number() OVER (ORDER BY nombre_sucursal)::text, 3, '0') AS codigo_sucursal,
    nombre_sucursal,
    direccion_sucursal,
    codigo_comuna
FROM (
    SELECT DISTINCT ON (lower(pg_temp.limpio(sl.sucursal_nombre)))
        pg_temp.limpio(sl.sucursal_nombre) AS nombre_sucursal,
        pg_temp.limpio(sl.direccion_sucursal) AS direccion_sucursal,
        c.codigo_comuna
    FROM stg_sucursales_lugares sl
    LEFT JOIN comuna c
           ON lower(c.nombre) = lower(pg_temp.limpio(sl.comuna_nombre))
    WHERE pg_temp.limpio(sl.sucursal_nombre) IS NOT NULL
      AND pg_temp.limpio(sl.direccion_sucursal) IS NOT NULL
      AND c.codigo_comuna IS NOT NULL
    ORDER BY lower(pg_temp.limpio(sl.sucursal_nombre)), c.codigo_comuna
) datos;

INSERT INTO sucursal (codigo_sucursal, nombre, direccion, codigo_comuna)
SELECT
    codigo_sucursal,
    left(nombre_sucursal, 100),
    left(direccion_sucursal, 150),
    codigo_comuna
FROM map_sucursal;

INSERT INTO errores_carga
SELECT
    'sucursales_lugaresOK.csv',
    row_to_json(sl)::text,
    'sucursal sin nombre, direccion o comuna valida',
    'no se cargo la sucursal asociada'
FROM stg_sucursales_lugares sl
LEFT JOIN comuna c
       ON lower(c.nombre) = lower(pg_temp.limpio(sl.comuna_nombre))
WHERE pg_temp.limpio(sl.sucursal_nombre) IS NULL
   OR pg_temp.limpio(sl.direccion_sucursal) IS NULL
   OR c.codigo_comuna IS NULL;

CREATE TEMP TABLE map_lugar AS
SELECT
    'L' || lpad(row_number() OVER (ORDER BY ms.codigo_sucursal, datos.nombre_lugar)::text, 5, '0') AS codigo_lugar,
    datos.nombre_lugar,
    datos.tipo_lugar,
    datos.capacidad,
    ms.codigo_sucursal
FROM (
    SELECT DISTINCT ON (
        lower(pg_temp.limpio(sl.sucursal_nombre)),
        lower(pg_temp.limpio(sl.lugar_nombre))
    )
        pg_temp.limpio(sl.sucursal_nombre) AS sucursal_nombre,
        pg_temp.limpio(sl.lugar_nombre) AS nombre_lugar,
        pg_temp.limpio(sl.tipo_lugar) AS tipo_lugar,
        pg_temp.to_int(sl.capacidad_personas) AS capacidad
    FROM stg_sucursales_lugares sl
    WHERE pg_temp.limpio(sl.lugar_nombre) IS NOT NULL
      AND pg_temp.limpio(sl.tipo_lugar) IS NOT NULL
      AND pg_temp.to_int(sl.capacidad_personas) IS NOT NULL
    ORDER BY lower(pg_temp.limpio(sl.sucursal_nombre)), lower(pg_temp.limpio(sl.lugar_nombre))
) datos
JOIN map_sucursal ms
  ON lower(ms.nombre_sucursal) = lower(datos.sucursal_nombre);

INSERT INTO lugar (codigo_lugar, nombre, capacidad, codigo_sucursal, tipo_lugar)
SELECT
    codigo_lugar,
    left(nombre_lugar, 100),
    capacidad,
    codigo_sucursal,
    left(tipo_lugar, 20)
FROM map_lugar;

INSERT INTO precio_lugar (
    codigo_lugar, tipo_precio, dia_semana, hora_inicio, hora_termino,
    fecha_inicio, fecha_fin, monto
)
SELECT
    ml.codigo_lugar,
    left(COALESCE(pg_temp.limpio(sl.tipo_precio), 'sin_tipo'), 10),
    left(pg_temp.limpio(sl.dia_semana), 15),
    pg_temp.to_hora(sl.hora_inicio),
    pg_temp.to_hora(sl.hora_termino),
    pg_temp.to_fecha(sl.fecha_inicio_vigencia),
    pg_temp.to_fecha(sl.fecha_fin_vigencia),
    pg_temp.to_int(sl.precio)
FROM stg_sucursales_lugares sl
JOIN map_sucursal ms
  ON lower(ms.nombre_sucursal) = lower(pg_temp.limpio(sl.sucursal_nombre))
JOIN map_lugar ml
  ON ml.codigo_sucursal = ms.codigo_sucursal
 AND lower(ml.nombre_lugar) = lower(pg_temp.limpio(sl.lugar_nombre))
WHERE pg_temp.to_int(sl.precio) IS NOT NULL;

INSERT INTO errores_carga
SELECT
    'sucursales_lugaresOK.csv',
    row_to_json(sl)::text,
    'lugar o precio invalido',
    'se cargo lo posible; precio/lugar invalido fue omitido'
FROM stg_sucursales_lugares sl
LEFT JOIN map_sucursal ms
       ON lower(ms.nombre_sucursal) = lower(pg_temp.limpio(sl.sucursal_nombre))
LEFT JOIN map_lugar ml
       ON ml.codigo_sucursal = ms.codigo_sucursal
      AND lower(ml.nombre_lugar) = lower(pg_temp.limpio(sl.lugar_nombre))
WHERE pg_temp.limpio(sl.lugar_nombre) IS NULL
   OR pg_temp.limpio(sl.tipo_lugar) IS NULL
   OR pg_temp.to_int(sl.capacidad_personas) IS NULL
   OR pg_temp.to_int(sl.precio) IS NULL
   OR ml.codigo_lugar IS NULL;

-- 3. Personas, socios, relaciones y usuarios
INSERT INTO persona (
    run, nombre_completo, email, telefono_celular, telefono_alternativo,
    direccion_calle, codigo_comuna, fecha_nacimiento
)
SELECT DISTINCT ON (pg_temp.limpio(ps.run_persona))
    left(pg_temp.limpio(ps.run_persona), 12),
    left(pg_temp.limpio(ps.nombre_completo), 150),
    left(pg_temp.limpio(ps.email), 150),
    left(pg_temp.limpio(ps.telefono_celular), 20),
    left(pg_temp.limpio(ps.telefono_alternativo), 20),
    left(pg_temp.limpio(ps.direccion_calle), 150),
    c.codigo_comuna,
    pg_temp.to_fecha(ps.fecha_nacimiento)
FROM stg_personas_socios ps
JOIN comuna c
  ON lower(c.nombre) = lower(pg_temp.limpio(ps.comuna_nombre))
 AND c.codigo_region = pg_temp.to_int(ps.region_codigo)
WHERE pg_temp.limpio(ps.run_persona) IS NOT NULL
  AND pg_temp.limpio(ps.nombre_completo) IS NOT NULL
ORDER BY pg_temp.limpio(ps.run_persona), pg_temp.limpio(ps.email) IS NULL;

INSERT INTO errores_carga
SELECT
    'personas_sociosOK.csv',
    row_to_json(ps)::text,
    'persona sin RUN, nombre o comuna valida',
    'persona no cargada'
FROM stg_personas_socios ps
LEFT JOIN comuna c
       ON lower(c.nombre) = lower(pg_temp.limpio(ps.comuna_nombre))
      AND c.codigo_region = pg_temp.to_int(ps.region_codigo)
WHERE pg_temp.limpio(ps.run_persona) IS NULL
   OR pg_temp.limpio(ps.nombre_completo) IS NULL
   OR c.codigo_comuna IS NULL;

INSERT INTO socio (
    run_persona, tipo_socio, fecha_inicio, fecha_fin, codigo_sucursal_base
)
SELECT DISTINCT ON (pg_temp.limpio(ps.run_persona))
    left(pg_temp.limpio(ps.run_persona), 12),
    left(lower(pg_temp.limpio(ps.tipo_persona)), 30),
    COALESCE(pg_temp.to_fecha(ps.fecha_inicio_membresia), DATE '1900-01-01'),
    pg_temp.to_fecha(ps.fecha_fin_membresia),
    ms.codigo_sucursal
FROM stg_personas_socios ps
JOIN persona p
  ON p.run = left(pg_temp.limpio(ps.run_persona), 12)
LEFT JOIN map_sucursal ms
       ON lower(ms.nombre_sucursal) = lower(pg_temp.limpio(ps.sucursal_base_nombre))
WHERE lower(pg_temp.limpio(ps.tipo_persona)) IN ('socio titular', 'beneficiario', 'adicional')
ORDER BY pg_temp.limpio(ps.run_persona), pg_temp.to_fecha(ps.fecha_inicio_membresia) IS NULL;

INSERT INTO errores_carga
SELECT
    'personas_sociosOK.csv',
    row_to_json(ps)::text,
    'socio sin fecha de inicio de membresia',
    'se cargo fecha 1900-01-01 para cumplir NOT NULL'
FROM stg_personas_socios ps
WHERE lower(pg_temp.limpio(ps.tipo_persona)) IN ('socio titular', 'beneficiario', 'adicional')
  AND pg_temp.limpio(ps.run_persona) IN (SELECT run FROM persona)
  AND pg_temp.to_fecha(ps.fecha_inicio_membresia) IS NULL;

INSERT INTO relacion_socio (id_socio_titular, id_socio_dependiente, parentesco)
SELECT DISTINCT
    st.id_socio,
    sd.id_socio,
    left(COALESCE(pg_temp.limpio(ps.parentesco), 'sin_info'), 30)
FROM stg_personas_socios ps
JOIN socio st
  ON st.run_persona = left(pg_temp.limpio(ps.run_socio_titular), 12)
JOIN socio sd
  ON sd.run_persona = left(pg_temp.limpio(ps.run_persona), 12)
WHERE lower(pg_temp.limpio(ps.tipo_persona)) IN ('beneficiario', 'adicional')
  AND pg_temp.limpio(ps.run_socio_titular) IS NOT NULL
  AND pg_temp.limpio(ps.run_socio_titular) <> pg_temp.limpio(ps.run_persona);

INSERT INTO usuario (run_persona, email_login, clave_encriptada, tipo_usuario)
SELECT DISTINCT ON (lower(pg_temp.limpio(ps.email)))
    left(pg_temp.limpio(ps.run_persona), 12),
    left(lower(pg_temp.limpio(ps.email)), 150),
    md5(COALESCE(pg_temp.limpio(ps.clave_en_texto_plano), 'sin_clave')),
    left(COALESCE(lower(pg_temp.limpio(ps.tipo_usuario)), 'socio'), 30)
FROM stg_personas_socios ps
JOIN persona p
  ON p.run = left(pg_temp.limpio(ps.run_persona), 12)
WHERE upper(pg_temp.limpio(ps.es_usuario_sistema)) = 'SI'
  AND pg_temp.limpio(ps.email) IS NOT NULL
ORDER BY lower(pg_temp.limpio(ps.email)), pg_temp.limpio(ps.clave_en_texto_plano) IS NULL;

-- 4. Reservas y pagos de reservas
INSERT INTO reserva (codigo_reserva, codigo_lugar, run_reservante, fecha_inicio, fecha_fin, estado)
SELECT
    left(pg_temp.limpio(r.codigo_reserva), 20),
    ml.codigo_lugar,
    left(pg_temp.limpio(r.run_reservante), 12),
    pg_temp.to_timestamp_ok(r.fecha_inicio),
    pg_temp.to_timestamp_ok(r.fecha_fin),
    left(lower(pg_temp.limpio(r.estado_reserva)), 20)
FROM stg_reservas_arriendos r
JOIN persona p
  ON p.run = left(pg_temp.limpio(r.run_reservante), 12)
JOIN map_sucursal ms
  ON lower(ms.nombre_sucursal) = lower(pg_temp.limpio(r.sucursal_nombre))
JOIN map_lugar ml
  ON ml.codigo_sucursal = ms.codigo_sucursal
 AND lower(ml.nombre_lugar) = lower(pg_temp.limpio(r.lugar_nombre))
WHERE pg_temp.limpio(r.codigo_reserva) IS NOT NULL
  AND pg_temp.to_timestamp_ok(r.fecha_inicio) IS NOT NULL
  AND pg_temp.to_timestamp_ok(r.fecha_fin) IS NOT NULL
  AND pg_temp.to_timestamp_ok(r.fecha_fin) > pg_temp.to_timestamp_ok(r.fecha_inicio)
  AND pg_temp.limpio(r.estado_reserva) IS NOT NULL;

INSERT INTO pago_reserva (codigo_reserva, fecha_pago, monto, medio_pago)
SELECT
    left(pg_temp.limpio(r.codigo_reserva), 20),
    COALESCE(pg_temp.to_fecha(r.fecha_pago), pg_temp.to_fecha(r.fecha_reserva)),
    pg_temp.to_int(r.monto_pagado),
    left(pg_temp.limpio(r.medio_pago), 30)
FROM stg_reservas_arriendos r
JOIN reserva rv
  ON rv.codigo_reserva = left(pg_temp.limpio(r.codigo_reserva), 20)
WHERE pg_temp.to_int(r.monto_pagado) IS NOT NULL
  AND pg_temp.to_int(r.monto_pagado) > 0
  AND COALESCE(pg_temp.to_fecha(r.fecha_pago), pg_temp.to_fecha(r.fecha_reserva)) IS NOT NULL;

INSERT INTO errores_carga
SELECT
    'reservas_arriendosOK.csv',
    row_to_json(r)::text,
    'reserva con FK o dato obligatorio invalido',
    'reserva no cargada'
FROM stg_reservas_arriendos r
LEFT JOIN persona p
       ON p.run = left(pg_temp.limpio(r.run_reservante), 12)
LEFT JOIN map_sucursal ms
       ON lower(ms.nombre_sucursal) = lower(pg_temp.limpio(r.sucursal_nombre))
LEFT JOIN map_lugar ml
       ON ml.codigo_sucursal = ms.codigo_sucursal
      AND lower(ml.nombre_lugar) = lower(pg_temp.limpio(r.lugar_nombre))
WHERE pg_temp.limpio(r.codigo_reserva) IS NULL
   OR p.run IS NULL
   OR ml.codigo_lugar IS NULL
   OR pg_temp.to_timestamp_ok(r.fecha_inicio) IS NULL
   OR pg_temp.to_timestamp_ok(r.fecha_fin) IS NULL
   OR pg_temp.to_timestamp_ok(r.fecha_fin) <= pg_temp.to_timestamp_ok(r.fecha_inicio)
   OR pg_temp.limpio(r.estado_reserva) IS NULL;

-- 5. Eventos, empresas, contactos, asistentes y pagos de eventos
INSERT INTO empresa (rut_empresa, nombre)
SELECT DISTINCT ON (left(pg_temp.limpio(ev.run_cliente), 15))
    left(pg_temp.limpio(ev.run_cliente), 15),
    left(pg_temp.limpio(ev.nombre_cliente), 150)
FROM stg_eventos ev
WHERE lower(pg_temp.limpio(ev.tipo_cliente)) IN ('empresa', 'empresa-institucion')
  AND pg_temp.limpio(ev.run_cliente) IS NOT NULL
  AND pg_temp.limpio(ev.nombre_cliente) IS NOT NULL
ORDER BY left(pg_temp.limpio(ev.run_cliente), 15);

INSERT INTO contacto_empresa (rut_empresa, run_persona, nombre, cargo)
SELECT DISTINCT
    e.rut_empresa,
    left(pg_temp.limpio(ev.rut_contacto_empresa), 12),
    left(pg_temp.limpio(ev.nombre_contacto_empresa), 50),
    left(pg_temp.limpio(ev.cargo_contacto), 50)
FROM stg_eventos ev
JOIN empresa e
  ON e.rut_empresa = left(pg_temp.limpio(ev.run_cliente), 15)
WHERE pg_temp.limpio(ev.rut_contacto_empresa) IS NOT NULL
   OR pg_temp.limpio(ev.nombre_contacto_empresa) IS NOT NULL;

INSERT INTO evento (
    codigo_evento, nombre, fecha_evento, codigo_lugar,
    codigo_sucursal, tipo_cliente, identificador_cliente
)
SELECT
    left(pg_temp.limpio(ev.evento_id), 20),
    left(pg_temp.limpio(ev.nombre_evento), 150),
    pg_temp.to_fecha(ev.fecha_evento),
    ml.codigo_lugar,
    ms.codigo_sucursal,
    left(lower(pg_temp.limpio(ev.tipo_cliente)), 20),
    left(pg_temp.limpio(ev.run_cliente), 20)
FROM stg_eventos ev
JOIN map_sucursal ms
  ON lower(ms.nombre_sucursal) = lower(pg_temp.limpio(ev.sucursal_nombre))
JOIN map_lugar ml
  ON ml.codigo_sucursal = ms.codigo_sucursal
 AND lower(ml.nombre_lugar) = lower(pg_temp.limpio(ev.lugar_nombre))
WHERE pg_temp.limpio(ev.evento_id) IS NOT NULL
  AND pg_temp.limpio(ev.nombre_evento) IS NOT NULL
  AND pg_temp.to_fecha(ev.fecha_evento) IS NOT NULL
  AND pg_temp.limpio(ev.tipo_cliente) IS NOT NULL
  AND pg_temp.limpio(ev.run_cliente) IS NOT NULL;

INSERT INTO asistente_evento (codigo_evento, run_asistente, nombre_asistente)
SELECT
    left(pg_temp.limpio(ev.evento_id), 20),
    NULL,
    left(pg_temp.limpio(a.nombre_asistente), 150)
FROM stg_eventos ev
CROSS JOIN LATERAL unnest(string_to_array(COALESCE(ev.lista_asistentes, ''), ';')) AS a(nombre_asistente)
JOIN evento e
  ON e.codigo_evento = left(pg_temp.limpio(ev.evento_id), 20)
WHERE pg_temp.limpio(a.nombre_asistente) IS NOT NULL;

INSERT INTO pago_evento (codigo_evento, fecha_pago, monto, tipo_pago)
SELECT
    e.codigo_evento,
    COALESCE(pg_temp.to_fecha(ev.fecha_contratacion), e.fecha_evento),
    pg_temp.to_int(ev.monto_pagado_reserva),
    'reserva'
FROM stg_eventos ev
JOIN evento e
  ON e.codigo_evento = left(pg_temp.limpio(ev.evento_id), 20)
WHERE pg_temp.to_int(ev.monto_pagado_reserva) IS NOT NULL
  AND pg_temp.to_int(ev.monto_pagado_reserva) > 0;

INSERT INTO pago_evento (codigo_evento, fecha_pago, monto, tipo_pago)
SELECT
    e.codigo_evento,
    e.fecha_evento,
    pg_temp.to_int(ev.monto_pagado_ejecucion),
    'ejecucion'
FROM stg_eventos ev
JOIN evento e
  ON e.codigo_evento = left(pg_temp.limpio(ev.evento_id), 20)
WHERE pg_temp.to_int(ev.monto_pagado_ejecucion) IS NOT NULL
  AND pg_temp.to_int(ev.monto_pagado_ejecucion) > 0;

INSERT INTO errores_carga
SELECT
    'eventosOK.csv',
    row_to_json(ev)::text,
    'evento con FK o dato obligatorio invalido',
    'evento no cargado'
FROM stg_eventos ev
LEFT JOIN map_sucursal ms
       ON lower(ms.nombre_sucursal) = lower(pg_temp.limpio(ev.sucursal_nombre))
LEFT JOIN map_lugar ml
       ON ml.codigo_sucursal = ms.codigo_sucursal
      AND lower(ml.nombre_lugar) = lower(pg_temp.limpio(ev.lugar_nombre))
WHERE pg_temp.limpio(ev.evento_id) IS NULL
   OR pg_temp.limpio(ev.nombre_evento) IS NULL
   OR pg_temp.to_fecha(ev.fecha_evento) IS NULL
   OR ml.codigo_lugar IS NULL
   OR pg_temp.limpio(ev.tipo_cliente) IS NULL
   OR pg_temp.limpio(ev.run_cliente) IS NULL;

-- 6. Membresias, cuotas y pagos de cuotas
INSERT INTO membresia (id_socio_titular, anio, fecha_inicio, fecha_fin, monto_base)
SELECT
    s.id_socio,
    pg_temp.to_int(pm.anio_membresia),
    make_date(pg_temp.to_int(pm.anio_membresia), 1, 1),
    make_date(pg_temp.to_int(pm.anio_membresia), 12, 31),
    max(pg_temp.to_int(pm.monto_membresia))
FROM stg_pagos_membresias pm
JOIN socio s
  ON s.run_persona = left(pg_temp.limpio(pm.run_socio_titular), 12)
WHERE pg_temp.to_int(pm.anio_membresia) BETWEEN 1900 AND 2100
  AND pg_temp.to_int(pm.monto_membresia) IS NOT NULL
GROUP BY s.id_socio, pg_temp.to_int(pm.anio_membresia);

INSERT INTO cuota (id_membresia, mes, fecha_vencimiento, monto_total, estado)
SELECT
    m.id_socio,
    pg_temp.to_int(pm.mes_cuota),
    pg_temp.to_fecha(pm.fecha_vencimiento),
    pg_temp.to_int(pm.monto_total),
    left(lower(pg_temp.limpio(pm.estado_pago)), 20)
FROM stg_pagos_membresias pm
JOIN socio s
  ON s.run_persona = left(pg_temp.limpio(pm.run_socio_titular), 12)
JOIN membresia m
  ON m.id_socio_titular = s.id_socio
 AND m.anio = pg_temp.to_int(pm.anio_membresia)
WHERE pg_temp.to_int(pm.mes_cuota) BETWEEN 1 AND 12
  AND pg_temp.to_fecha(pm.fecha_vencimiento) IS NOT NULL
  AND pg_temp.to_int(pm.monto_total) IS NOT NULL
  AND pg_temp.limpio(pm.estado_pago) IS NOT NULL;

INSERT INTO pago_cuota (cuota_numero, fecha_pago, monto_pagado, medio_pago, id_socio)
SELECT
    c.id_cuota,
    pg_temp.to_fecha(pm.fecha_pago),
    pg_temp.to_int(pm.monto_total),
    left(pg_temp.limpio(pm.medio_pago), 30),
    s.id_socio
FROM stg_pagos_membresias pm
JOIN socio s
  ON s.run_persona = left(pg_temp.limpio(pm.run_socio_titular), 12)
JOIN membresia m
  ON m.id_socio_titular = s.id_socio
 AND m.anio = pg_temp.to_int(pm.anio_membresia)
JOIN cuota c
  ON c.id_membresia = m.id_socio
 AND c.mes = pg_temp.to_int(pm.mes_cuota)
 AND c.fecha_vencimiento = pg_temp.to_fecha(pm.fecha_vencimiento)
WHERE lower(pg_temp.limpio(pm.estado_pago)) = 'pagado'
  AND pg_temp.to_fecha(pm.fecha_pago) IS NOT NULL
  AND pg_temp.to_int(pm.monto_total) IS NOT NULL;

INSERT INTO errores_carga
SELECT
    'pagos_membresiasOK.csv',
    row_to_json(pm)::text,
    'pago/membresia con socio inexistente o dato obligatorio invalido',
    'membresia, cuota o pago no cargado'
FROM stg_pagos_membresias pm
LEFT JOIN socio s
       ON s.run_persona = left(pg_temp.limpio(pm.run_socio_titular), 12)
WHERE s.id_socio IS NULL
   OR pg_temp.to_int(pm.anio_membresia) NOT BETWEEN 1900 AND 2100
   OR pg_temp.to_int(pm.mes_cuota) NOT BETWEEN 1 AND 12
   OR pg_temp.to_fecha(pm.fecha_vencimiento) IS NULL
   OR pg_temp.to_int(pm.monto_membresia) IS NULL
   OR pg_temp.to_int(pm.monto_total) IS NULL
   OR pg_temp.limpio(pm.estado_pago) IS NULL;

-- 7. Cargos administrativos
INSERT INTO cargo (nombre)
SELECT DISTINCT left(pg_temp.limpio(ca.nombre_cargo), 100)
FROM stg_cargos_administrativos ca
WHERE pg_temp.limpio(ca.nombre_cargo) IS NOT NULL;

INSERT INTO persona_cargo (run_persona, id_cargo, codigo_sucursal, fecha_inicio, fecha_termino)
SELECT
    p.run,
    c.id_cargo,
    ms.codigo_sucursal,
    pg_temp.to_fecha(ca.fecha_inicio_cargo),
    pg_temp.to_fecha(ca.fecha_termino_cargo)
FROM stg_cargos_administrativos ca
JOIN persona p
  ON p.run = left(pg_temp.limpio(ca.run_persona), 12)
JOIN cargo c
  ON lower(c.nombre) = lower(left(pg_temp.limpio(ca.nombre_cargo), 100))
JOIN map_sucursal ms
  ON lower(ms.nombre_sucursal) = lower(pg_temp.limpio(ca.sucursal_nombre))
WHERE pg_temp.to_fecha(ca.fecha_inicio_cargo) IS NOT NULL;

INSERT INTO errores_carga
SELECT
    'cargos_administrativosOK.csv',
    row_to_json(ca)::text,
    'cargo con persona/sucursal inexistente o fecha invalida',
    'cargo administrativo no cargado'
FROM stg_cargos_administrativos ca
LEFT JOIN persona p
       ON p.run = left(pg_temp.limpio(ca.run_persona), 12)
LEFT JOIN map_sucursal ms
       ON lower(ms.nombre_sucursal) = lower(pg_temp.limpio(ca.sucursal_nombre))
WHERE p.run IS NULL
   OR ms.codigo_sucursal IS NULL
   OR pg_temp.limpio(ca.nombre_cargo) IS NULL
   OR pg_temp.to_fecha(ca.fecha_inicio_cargo) IS NULL;

\copy errores_carga TO 'cargaERR.csv' WITH (FORMAT csv, HEADER true, DELIMITER ';', QUOTE '"')

\o cargaLOG.txt
SELECT 'region' AS tabla, count(*) AS registros FROM region
UNION ALL SELECT 'comuna', count(*) FROM comuna
UNION ALL SELECT 'sucursal', count(*) FROM sucursal
UNION ALL SELECT 'lugar', count(*) FROM lugar
UNION ALL SELECT 'precio_lugar', count(*) FROM precio_lugar
UNION ALL SELECT 'persona', count(*) FROM persona
UNION ALL SELECT 'socio', count(*) FROM socio
UNION ALL SELECT 'relacion_socio', count(*) FROM relacion_socio
UNION ALL SELECT 'usuario', count(*) FROM usuario
UNION ALL SELECT 'reserva', count(*) FROM reserva
UNION ALL SELECT 'pago_reserva', count(*) FROM pago_reserva
UNION ALL SELECT 'empresa', count(*) FROM empresa
UNION ALL SELECT 'contacto_empresa', count(*) FROM contacto_empresa
UNION ALL SELECT 'evento', count(*) FROM evento
UNION ALL SELECT 'asistente_evento', count(*) FROM asistente_evento
UNION ALL SELECT 'pago_evento', count(*) FROM pago_evento
UNION ALL SELECT 'membresia', count(*) FROM membresia
UNION ALL SELECT 'cuota', count(*) FROM cuota
UNION ALL SELECT 'pago_cuota', count(*) FROM pago_cuota
UNION ALL SELECT 'cargo', count(*) FROM cargo
UNION ALL SELECT 'persona_cargo', count(*) FROM persona_cargo
UNION ALL SELECT 'errores_carga', count(*) FROM errores_carga
ORDER BY tabla;

SELECT archivo, motivo, accion, count(*) AS cantidad
FROM errores_carga
GROUP BY archivo, motivo, accion
ORDER BY archivo, motivo, accion;
\o
