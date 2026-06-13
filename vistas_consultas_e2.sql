-- IA: Archivo creado con asistencia de IA para completar el punto 2.4 de la Etapa 3 IIC2413.
-- Porcentaje estimado de apoyo IA: 80%.
-- Tecnología utilizada: ChatGPT.
-- Prompt utilizado: "Sigamos con el punto 2.4: Consultas similares a E2".
-- El estudiante debe revisar, adaptar y comprender completamente este SQL.

-- Vistas para las consultas similares a la Etapa 2.
-- Se crean vistas generales y luego el PHP filtra por sucursal, semana, mes o año.

CREATE OR REPLACE VIEW public.v_e2_agenda AS
SELECT
    base.codigo_sucursal,
    base.nombre_sucursal,
    date_trunc('week', base.fecha)::date AS lunes_semana,
    CASE EXTRACT(ISODOW FROM base.fecha)::int
        WHEN 1 THEN 'lunes'
        WHEN 2 THEN 'martes'
        WHEN 3 THEN 'miercoles'
        WHEN 4 THEN 'jueves'
        WHEN 5 THEN 'viernes'
        WHEN 6 THEN 'sabado'
        WHEN 7 THEN 'domingo'
    END AS dia,
    base.fecha,
    base.hora,
    base.codigo_lugar,
    base.lugar,
    base.tipo_registro,
    base.detalle,
    base.estado,
    base.codigo_referencia
FROM (
    SELECT
        su.codigo_sucursal,
        su.nombre AS nombre_sucursal,
        r.fecha_inicio::date AS fecha,
        to_char(r.fecha_inicio, 'HH24:MI') AS hora,
        l.codigo_lugar,
        l.nombre AS lugar,
        'reserva'::text AS tipo_registro,
        COALESCE(p.nombre_completo, r.run_reservante) AS detalle,
        r.estado,
        r.codigo_reserva AS codigo_referencia
    FROM public.reserva r
    INNER JOIN public.lugar l
        ON l.codigo_lugar = r.codigo_lugar
    INNER JOIN public.sucursal su
        ON su.codigo_sucursal = l.codigo_sucursal
    LEFT JOIN public.persona p
        ON p.run = r.run_reservante
    WHERE LOWER(r.estado) IN ('reservada', 'ejecutada')

    UNION ALL

    SELECT
        su.codigo_sucursal,
        su.nombre AS nombre_sucursal,
        e.fecha_evento AS fecha,
        '00:00'::text AS hora,
        l.codigo_lugar,
        COALESCE(l.nombre, 'Sin lugar') AS lugar,
        'evento'::text AS tipo_registro,
        e.nombre AS detalle,
        'programado'::varchar(20) AS estado,
        e.codigo_evento AS codigo_referencia
    FROM public.evento e
    LEFT JOIN public.lugar l
        ON l.codigo_lugar = e.codigo_lugar
    LEFT JOIN public.sucursal su
        ON su.codigo_sucursal = COALESCE(e.codigo_sucursal, l.codigo_sucursal)
    WHERE su.codigo_sucursal IS NOT NULL
) base;


CREATE OR REPLACE VIEW public.v_e2_ingreso_mensual AS
SELECT
    ingresos.codigo_sucursal,
    ingresos.nombre_sucursal,
    ingresos.anio,
    ingresos.mes,
    ingresos.concepto,
    ingresos.estado_ingreso,
    SUM(ingresos.monto)::integer AS monto
FROM (
    -- Membresías: pagadas = recibido; impagas = futuro esperado.
    SELECT
        su.codigo_sucursal,
        su.nombre AS nombre_sucursal,
        CASE
            WHEN pc.fecha_pago IS NOT NULL THEN EXTRACT(YEAR FROM pc.fecha_pago)::integer
            ELSE EXTRACT(YEAR FROM s.fecha_inicio)::integer
        END AS anio,
        CASE
            WHEN pc.fecha_pago IS NOT NULL THEN EXTRACT(MONTH FROM pc.fecha_pago)::integer
            ELSE pc.cuota_numero
        END AS mes,
        'membresias'::text AS concepto,
        CASE
            WHEN pc.fecha_pago IS NOT NULL AND pc.medio_pago IS NOT NULL THEN 'recibido'
            ELSE 'futuro_esperado'
        END AS estado_ingreso,
        COALESCE(pc.monto_pagado, 0) AS monto
    FROM public.pago_cuota pc
    INNER JOIN public.socio s
        ON s.id_socio = pc.id_socio
    INNER JOIN public.sucursal su
        ON su.codigo_sucursal = s.codigo_sucursal_base

    UNION ALL

    -- Reservas: ejecutadas y pagadas = recibido; reservadas o sin pago = futuro esperado.
    SELECT
        su.codigo_sucursal,
        su.nombre AS nombre_sucursal,
        CASE
            WHEN pr.fecha_pago IS NOT NULL AND pr.monto IS NOT NULL AND pr.medio_pago IS NOT NULL
                THEN EXTRACT(YEAR FROM pr.fecha_pago)::integer
            ELSE EXTRACT(YEAR FROM r.fecha_inicio)::integer
        END AS anio,
        CASE
            WHEN pr.fecha_pago IS NOT NULL AND pr.monto IS NOT NULL AND pr.medio_pago IS NOT NULL
                THEN EXTRACT(MONTH FROM pr.fecha_pago)::integer
            ELSE EXTRACT(MONTH FROM r.fecha_inicio)::integer
        END AS mes,
        'reservas'::text AS concepto,
        CASE
            WHEN LOWER(r.estado) = 'ejecutada'
                 AND pr.fecha_pago IS NOT NULL
                 AND pr.monto IS NOT NULL
                 AND pr.medio_pago IS NOT NULL
                THEN 'recibido'
            ELSE 'futuro_esperado'
        END AS estado_ingreso,
        COALESCE(pr.monto, precio.monto, 0) AS monto
    FROM public.reserva r
    INNER JOIN public.lugar l
        ON l.codigo_lugar = r.codigo_lugar
    INNER JOIN public.sucursal su
        ON su.codigo_sucursal = l.codigo_sucursal
    LEFT JOIN public.pago_reserva pr
        ON pr.codigo_reserva = r.codigo_reserva
    LEFT JOIN LATERAL (
        SELECT pl.monto
        FROM public.precio_lugar pl
        WHERE pl.codigo_lugar = r.codigo_lugar
          AND r.fecha_inicio::date BETWEEN pl.fecha_inicio AND pl.fecha_fin
          AND (
                LOWER(pl.tipo_precio) = 'dia'
                OR (
                    LOWER(pl.tipo_precio) = 'hora'
                    AND pl.hora_inicio <= r.fecha_inicio::time
                    AND pl.hora_termino >= r.fecha_fin::time
                )
          )
        ORDER BY pl.fecha_inicio DESC, pl.id_precio DESC
        LIMIT 1
    ) precio ON true
    WHERE LOWER(r.estado) IN ('reservada', 'ejecutada')

    UNION ALL

    -- Eventos: la base solo conserva pagos registrados, por tanto se informan como recibidos.
    SELECT
        su.codigo_sucursal,
        su.nombre AS nombre_sucursal,
        EXTRACT(YEAR FROM pe.fecha_pago)::integer AS anio,
        EXTRACT(MONTH FROM pe.fecha_pago)::integer AS mes,
        'eventos'::text AS concepto,
        'recibido'::text AS estado_ingreso,
        COALESCE(pe.monto, 0) AS monto
    FROM public.pago_evento pe
    INNER JOIN public.evento e
        ON e.codigo_evento = pe.codigo_evento
    LEFT JOIN public.lugar l
        ON l.codigo_lugar = e.codigo_lugar
    INNER JOIN public.sucursal su
        ON su.codigo_sucursal = COALESCE(e.codigo_sucursal, l.codigo_sucursal)
) ingresos
GROUP BY
    ingresos.codigo_sucursal,
    ingresos.nombre_sucursal,
    ingresos.anio,
    ingresos.mes,
    ingresos.concepto,
    ingresos.estado_ingreso;


CREATE OR REPLACE VIEW public.v_e2_morosos AS
SELECT
    s.id_socio,
    p.run,
    p.nombre_completo,
    su.nombre AS nombre_sucursal,
    SUM(COALESCE(pc.monto_pagado, 0))::integer AS monto_atrasado,
    COUNT(*)::integer AS numero_cuotas,
    MIN(pc.cuota_numero)::integer AS primera_cuota_atrasada,
    MAX(pc.cuota_numero)::integer AS ultima_cuota_atrasada
FROM public.pago_cuota pc
INNER JOIN public.socio s
    ON s.id_socio = pc.id_socio
INNER JOIN public.persona p
    ON p.run = s.run_persona
INNER JOIN public.sucursal su
    ON su.codigo_sucursal = s.codigo_sucursal_base
WHERE LOWER(s.tipo_socio) = 'socio_titular'
  AND (pc.fecha_pago IS NULL OR pc.medio_pago IS NULL)
GROUP BY
    s.id_socio,
    p.run,
    p.nombre_completo,
    su.nombre;


CREATE OR REPLACE VIEW public.v_e2_finbeneficiario AS
SELECT
    st.id_socio AS id_socio_titular,
    pt.run AS run_socio_titular,
    pt.nombre_completo AS nombre_socio_titular,
    pt.email AS email_socio_titular,
    pt.telefono_celular AS telefono_socio_titular,
    sd.id_socio AS id_socio_dependiente,
    pd.run AS run_beneficiario,
    pd.nombre_completo AS nombre_beneficiario,
    pd.email AS email_beneficiario,
    pd.telefono_celular AS telefono_beneficiario,
    rs.parentesco,
    pd.fecha_nacimiento,
    (EXTRACT(YEAR FROM pd.fecha_nacimiento)::integer + 29) AS anio_cumple_29,
    su.nombre AS nombre_sucursal
FROM public.relacion_socio rs
INNER JOIN public.socio st
    ON st.id_socio = rs.id_socio_titular
INNER JOIN public.persona pt
    ON pt.run = st.run_persona
INNER JOIN public.socio sd
    ON sd.id_socio = rs.id_socio_dependiente
INNER JOIN public.persona pd
    ON pd.run = sd.run_persona
LEFT JOIN public.sucursal su
    ON su.codigo_sucursal = st.codigo_sucursal_base
WHERE LOWER(st.tipo_socio) = 'socio_titular'
  AND LOWER(sd.tipo_socio) = 'beneficiario'
  AND LOWER(rs.parentesco) IN ('hijo', 'hija', 'hijo/a')
  AND pd.fecha_nacimiento IS NOT NULL;


CREATE OR REPLACE VIEW public.v_e2_ingreso_sucursal_2025 AS
WITH ingresos AS (
    SELECT
        su.codigo_sucursal,
        SUM(COALESCE(pc.monto_pagado, 0))::numeric AS monto
    FROM public.pago_cuota pc
    INNER JOIN public.socio s
        ON s.id_socio = pc.id_socio
    INNER JOIN public.sucursal su
        ON su.codigo_sucursal = s.codigo_sucursal_base
    WHERE pc.fecha_pago >= DATE '2025-01-01'
      AND pc.fecha_pago < DATE '2026-01-01'
      AND pc.medio_pago IS NOT NULL
    GROUP BY su.codigo_sucursal

    UNION ALL

    SELECT
        su.codigo_sucursal,
        SUM(COALESCE(pr.monto, 0))::numeric AS monto
    FROM public.pago_reserva pr
    INNER JOIN public.reserva r
        ON r.codigo_reserva = pr.codigo_reserva
    INNER JOIN public.lugar l
        ON l.codigo_lugar = r.codigo_lugar
    INNER JOIN public.sucursal su
        ON su.codigo_sucursal = l.codigo_sucursal
    WHERE pr.fecha_pago >= DATE '2025-01-01'
      AND pr.fecha_pago < DATE '2026-01-01'
      AND pr.monto IS NOT NULL
      AND pr.medio_pago IS NOT NULL
    GROUP BY su.codigo_sucursal

    UNION ALL

    SELECT
        su.codigo_sucursal,
        SUM(COALESCE(pe.monto, 0))::numeric AS monto
    FROM public.pago_evento pe
    INNER JOIN public.evento e
        ON e.codigo_evento = pe.codigo_evento
    LEFT JOIN public.lugar l
        ON l.codigo_lugar = e.codigo_lugar
    INNER JOIN public.sucursal su
        ON su.codigo_sucursal = COALESCE(e.codigo_sucursal, l.codigo_sucursal)
    WHERE pe.fecha_pago >= DATE '2025-01-01'
      AND pe.fecha_pago < DATE '2026-01-01'
    GROUP BY su.codigo_sucursal
),
totales AS (
    SELECT
        su.codigo_sucursal,
        su.nombre AS nombre_sucursal,
        COALESCE(SUM(i.monto), 0)::numeric AS ingresos_totales
    FROM public.sucursal su
    LEFT JOIN ingresos i
        ON i.codigo_sucursal = su.codigo_sucursal
    GROUP BY su.codigo_sucursal, su.nombre
),
gerentes AS (
    SELECT
        pc.codigo_sucursal,
        string_agg(DISTINCT p.nombre_completo, ', ' ORDER BY p.nombre_completo) AS gerente_a_cargo
    FROM public.persona_cargo pc
    INNER JOIN public.cargo c
        ON c.id_cargo = pc.id_cargo
    INNER JOIN public.persona p
        ON p.run = pc.run_persona
    WHERE LOWER(c.nombre) = 'gerente'
      AND pc.fecha_inicio <= DATE '2025-12-31'
      AND COALESCE(pc.fecha_termino, DATE '9999-12-31') >= DATE '2025-01-01'
    GROUP BY pc.codigo_sucursal
)
SELECT
    t.codigo_sucursal,
    t.nombre_sucursal,
    COALESCE(g.gerente_a_cargo, 'Sin gerente registrado') AS gerente_a_cargo,
    t.ingresos_totales::integer AS ingresos_totales,
    ROUND(
        CASE
            WHEN SUM(t.ingresos_totales) OVER () > 0
                THEN (t.ingresos_totales / SUM(t.ingresos_totales) OVER ()) * 100
            ELSE 0
        END,
        2
    ) AS porcentaje_total_club
FROM totales t
LEFT JOIN gerentes g
    ON g.codigo_sucursal = t.codigo_sucursal;
