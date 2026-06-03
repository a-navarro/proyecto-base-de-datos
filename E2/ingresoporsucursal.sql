-- IA: Archivo generado con apoyo de ChatGPT. Revise, adapte y declare su uso en el README segun las reglas del curso.
\set ON_ERROR_STOP on
\pset pager off
\pset border 2
\pset null ''
\o ingresoporsucursal.txt

WITH ingresos AS (
    SELECT
        s.codigo_sucursal,
        SUM(pc.monto_pagado)::numeric AS monto
    FROM public.pago_cuota AS pc
    JOIN public.cuota AS c
        ON pc.cuota_numero = c.id_cuota
    JOIN public.membresia AS m
        ON c.id_membresia = m.id_socio
    JOIN public.socio AS so
        ON m.id_socio_titular = so.id_socio
    JOIN public.sucursal AS s
        ON so.codigo_sucursal_base = s.codigo_sucursal
    WHERE pc.fecha_pago >= DATE '2025-01-01'
      AND pc.fecha_pago <  DATE '2026-01-01'
    GROUP BY s.codigo_sucursal

    UNION ALL

    SELECT
        s.codigo_sucursal,
        SUM(pr.monto)::numeric AS monto
    FROM public.pago_reserva AS pr
    JOIN public.reserva AS r
        ON pr.codigo_reserva = r.codigo_reserva
    JOIN public.lugar AS l
        ON r.codigo_lugar = l.codigo_lugar
    JOIN public.sucursal AS s
        ON l.codigo_sucursal = s.codigo_sucursal
    WHERE pr.fecha_pago >= DATE '2025-01-01'
      AND pr.fecha_pago <  DATE '2026-01-01'
    GROUP BY s.codigo_sucursal

    UNION ALL

    SELECT
        s.codigo_sucursal,
        SUM(pe.monto)::numeric AS monto
    FROM public.pago_evento AS pe
    JOIN public.evento AS e
        ON pe.codigo_evento = e.codigo_evento
    JOIN public.sucursal AS s
        ON e.codigo_sucursal = s.codigo_sucursal
    WHERE pe.fecha_pago >= DATE '2025-01-01'
      AND pe.fecha_pago <  DATE '2026-01-01'
    GROUP BY s.codigo_sucursal
),
ingresos_por_sucursal AS (
    SELECT
        s.codigo_sucursal,
        s.nombre AS nombre_sucursal,
        COALESCE(SUM(i.monto), 0) AS ingreso_total
    FROM public.sucursal AS s
    LEFT JOIN ingresos AS i
        ON s.codigo_sucursal = i.codigo_sucursal
    GROUP BY s.codigo_sucursal, s.nombre
),
gerentes AS (
    SELECT
        pc.codigo_sucursal,
        STRING_AGG(DISTINCT p.nombre_completo, ' | ' ORDER BY p.nombre_completo) AS gerente_a_cargo
    FROM public.persona_cargo AS pc
    JOIN public.cargo AS c
        ON pc.id_cargo = c.id_cargo
    JOIN public.persona AS p
        ON pc.run_persona = p.run
    WHERE LOWER(TRIM(c.nombre)) = 'gerente'
      AND pc.fecha_inicio <= DATE '2025-12-31'
      AND (pc.fecha_termino IS NULL OR pc.fecha_termino >= DATE '2025-01-01')
    GROUP BY pc.codigo_sucursal
),
total_club AS (
    SELECT SUM(ingreso_total) AS ingreso_total_club
    FROM ingresos_por_sucursal
)
SELECT
    ips.nombre_sucursal,
    COALESCE(g.gerente_a_cargo, 'Sin gerente registrado') AS gerente_a_cargo,
    ips.ingreso_total::bigint AS ingresos_totales_2025,
    COALESCE(
        ROUND((100.0 * ips.ingreso_total / NULLIF(t.ingreso_total_club, 0))::numeric, 2),
        0
    ) AS porcentaje_total_club_2025
FROM ingresos_por_sucursal AS ips
LEFT JOIN gerentes AS g
    ON ips.codigo_sucursal = g.codigo_sucursal
CROSS JOIN total_club AS t
ORDER BY ips.ingreso_total DESC, ips.nombre_sucursal;

\o
