-- IA: Archivo generado con apoyo de ChatGPT. Revise, adapte y declare su uso en el README segun las reglas del curso.
\set ON_ERROR_STOP on
\pset pager off
\pset border 2
\pset null ''

DROP TABLE IF EXISTS tmp_ingresomensual;
CREATE TEMP TABLE tmp_ingresomensual (
    concepto text,
    tipo_ingreso text,
    monto numeric
);

WITH parametros AS (
    SELECT
        DATE_TRUNC('month', CURRENT_DATE)::date AS inicio_mes,
        (DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month')::date AS fin_mes,
        'santa cruz'::text AS sucursal_buscada
)
INSERT INTO tmp_ingresomensual (concepto, tipo_ingreso, monto)
SELECT
    'Membresias',
    'ingresos efectivamente recibidos',
    COALESCE(SUM(pc.monto_pagado), 0)
FROM public.pago_cuota AS pc
JOIN public.cuota AS c
    ON pc.cuota_numero = c.id_cuota
JOIN public.membresia AS m
    ON c.id_membresia = m.id_socio
JOIN public.socio AS so
    ON m.id_socio_titular = so.id_socio
JOIN public.sucursal AS s
    ON so.codigo_sucursal_base = s.codigo_sucursal
CROSS JOIN parametros AS p
WHERE LOWER(TRIM(s.nombre)) = p.sucursal_buscada
  AND pc.fecha_pago >= p.inicio_mes
  AND pc.fecha_pago < p.fin_mes;

WITH parametros AS (
    SELECT
        DATE_TRUNC('month', CURRENT_DATE)::date AS inicio_mes,
        (DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month')::date AS fin_mes,
        'santa cruz'::text AS sucursal_buscada
),
pagos_por_cuota AS (
    SELECT
        cuota_numero,
        SUM(monto_pagado) AS total_pagado
    FROM public.pago_cuota
    GROUP BY cuota_numero
)
INSERT INTO tmp_ingresomensual (concepto, tipo_ingreso, monto)
SELECT
    'Membresias',
    'ingresos futuros esperados',
    COALESCE(SUM(GREATEST(c.monto_total - COALESCE(ppc.total_pagado, 0), 0)), 0)
FROM public.cuota AS c
JOIN public.membresia AS m
    ON c.id_membresia = m.id_socio
JOIN public.socio AS so
    ON m.id_socio_titular = so.id_socio
JOIN public.sucursal AS s
    ON so.codigo_sucursal_base = s.codigo_sucursal
LEFT JOIN pagos_por_cuota AS ppc
    ON c.id_cuota = ppc.cuota_numero
CROSS JOIN parametros AS p
WHERE LOWER(TRIM(s.nombre)) = p.sucursal_buscada
  AND c.fecha_vencimiento >= p.inicio_mes
  AND c.fecha_vencimiento < p.fin_mes;

WITH parametros AS (
    SELECT
        DATE_TRUNC('month', CURRENT_DATE)::date AS inicio_mes,
        (DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month')::date AS fin_mes,
        'santa cruz'::text AS sucursal_buscada
)
INSERT INTO tmp_ingresomensual (concepto, tipo_ingreso, monto)
SELECT
    'Reservas ejecutadas',
    'ingresos efectivamente recibidos',
    COALESCE(SUM(pr.monto), 0)
FROM public.pago_reserva AS pr
JOIN public.reserva AS r
    ON pr.codigo_reserva = r.codigo_reserva
JOIN public.lugar AS l
    ON r.codigo_lugar = l.codigo_lugar
JOIN public.sucursal AS s
    ON l.codigo_sucursal = s.codigo_sucursal
CROSS JOIN parametros AS p
WHERE LOWER(TRIM(s.nombre)) = p.sucursal_buscada
  AND LOWER(TRIM(r.estado)) = 'ejecutada'
  AND pr.fecha_pago >= p.inicio_mes
  AND pr.fecha_pago < p.fin_mes;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'reserva'
          AND column_name = 'monto_total'
    ) THEN
        EXECUTE $sql$
            WITH parametros AS (
                SELECT
                    DATE_TRUNC('month', CURRENT_DATE)::date AS inicio_mes,
                    (DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month')::date AS fin_mes,
                    'santa cruz'::text AS sucursal_buscada
            ),
            pagos_por_reserva AS (
                SELECT
                    codigo_reserva,
                    SUM(monto) AS total_pagado
                FROM public.pago_reserva
                GROUP BY codigo_reserva
            )
            INSERT INTO tmp_ingresomensual (concepto, tipo_ingreso, monto)
            SELECT
                'Reservas ejecutadas',
                'ingresos futuros esperados',
                COALESCE(SUM(GREATEST(r.monto_total - COALESCE(ppr.total_pagado, 0), 0)), 0)
            FROM public.reserva AS r
            JOIN public.lugar AS l
                ON r.codigo_lugar = l.codigo_lugar
            JOIN public.sucursal AS s
                ON l.codigo_sucursal = s.codigo_sucursal
            LEFT JOIN pagos_por_reserva AS ppr
                ON r.codigo_reserva = ppr.codigo_reserva
            CROSS JOIN parametros AS p
            WHERE LOWER(TRIM(s.nombre)) = p.sucursal_buscada
              AND LOWER(TRIM(r.estado)) = 'ejecutada'
              AND r.fecha_inicio >= p.inicio_mes
              AND r.fecha_inicio < p.fin_mes
        $sql$;
    ELSE
        INSERT INTO tmp_ingresomensual VALUES ('Reservas ejecutadas', 'ingresos futuros esperados', 0);
    END IF;
END $$;

WITH parametros AS (
    SELECT
        DATE_TRUNC('month', CURRENT_DATE)::date AS inicio_mes,
        (DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month')::date AS fin_mes,
        'santa cruz'::text AS sucursal_buscada
)
INSERT INTO tmp_ingresomensual (concepto, tipo_ingreso, monto)
SELECT
    'Eventos',
    'ingresos efectivamente recibidos',
    COALESCE(SUM(pe.monto), 0)
FROM public.pago_evento AS pe
JOIN public.evento AS e
    ON pe.codigo_evento = e.codigo_evento
JOIN public.sucursal AS s
    ON e.codigo_sucursal = s.codigo_sucursal
CROSS JOIN parametros AS p
WHERE LOWER(TRIM(s.nombre)) = p.sucursal_buscada
  AND pe.fecha_pago >= p.inicio_mes
  AND pe.fecha_pago < p.fin_mes;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'evento'
          AND column_name = 'monto_total'
    ) THEN
        EXECUTE $sql$
            WITH parametros AS (
                SELECT
                    DATE_TRUNC('month', CURRENT_DATE)::date AS inicio_mes,
                    (DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month')::date AS fin_mes,
                    'santa cruz'::text AS sucursal_buscada
            ),
            pagos_por_evento AS (
                SELECT
                    codigo_evento,
                    SUM(monto) AS total_pagado
                FROM public.pago_evento
                GROUP BY codigo_evento
            )
            INSERT INTO tmp_ingresomensual (concepto, tipo_ingreso, monto)
            SELECT
                'Eventos',
                'ingresos futuros esperados',
                COALESCE(SUM(GREATEST(e.monto_total - COALESCE(ppe.total_pagado, 0), 0)), 0)
            FROM public.evento AS e
            JOIN public.sucursal AS s
                ON e.codigo_sucursal = s.codigo_sucursal
            LEFT JOIN pagos_por_evento AS ppe
                ON e.codigo_evento = ppe.codigo_evento
            CROSS JOIN parametros AS p
            WHERE LOWER(TRIM(s.nombre)) = p.sucursal_buscada
              AND e.fecha_evento >= p.inicio_mes
              AND e.fecha_evento < p.fin_mes
        $sql$;
    ELSE
        INSERT INTO tmp_ingresomensual VALUES ('Eventos', 'ingresos futuros esperados', 0);
    END IF;
END $$;

\o ingresomensual.txt

WITH base_reporte AS (
    SELECT *
    FROM (
        VALUES
            ('Membresias', 'ingresos efectivamente recibidos', 1, 1),
            ('Membresias', 'ingresos futuros esperados', 1, 2),
            ('Reservas ejecutadas', 'ingresos efectivamente recibidos', 2, 1),
            ('Reservas ejecutadas', 'ingresos futuros esperados', 2, 2),
            ('Eventos', 'ingresos efectivamente recibidos', 3, 1),
            ('Eventos', 'ingresos futuros esperados', 3, 2)
    ) AS t(concepto, tipo_ingreso, orden_concepto, orden_tipo)
)
SELECT
    b.concepto,
    b.tipo_ingreso,
    COALESCE(SUM(i.monto), 0)::bigint AS monto
FROM base_reporte AS b
LEFT JOIN tmp_ingresomensual AS i
    ON b.concepto = i.concepto
   AND b.tipo_ingreso = i.tipo_ingreso
GROUP BY b.concepto, b.tipo_ingreso, b.orden_concepto, b.orden_tipo
ORDER BY b.orden_concepto, b.orden_tipo;

\o
