-- IA: Archivo generado con apoyo de ChatGPT. Revise, adapte y declare su uso en el README segun las reglas del curso.
\set ON_ERROR_STOP on
\pset pager off
\pset border 2
\pset null ''
\o morosos.txt

WITH pagos_por_cuota AS (
    SELECT
        cuota_numero,
        SUM(monto_pagado) AS total_pagado
    FROM public.pago_cuota
    GROUP BY cuota_numero
),
cuotas_pendientes AS (
    SELECT
        p.nombre_completo,
        p.run,
        s.nombre AS sucursal,
        c.id_cuota,
        GREATEST(c.monto_total - COALESCE(ppc.total_pagado, 0), 0) AS monto_pendiente
    FROM public.cuota AS c
    JOIN public.membresia AS m
        ON c.id_membresia = m.id_socio
    JOIN public.socio AS so
        ON m.id_socio_titular = so.id_socio
    JOIN public.persona AS p
        ON so.run_persona = p.run
    JOIN public.sucursal AS s
        ON so.codigo_sucursal_base = s.codigo_sucursal
    LEFT JOIN pagos_por_cuota AS ppc
        ON c.id_cuota = ppc.cuota_numero
    WHERE (
            LOWER(TRIM(c.estado)) = 'atrasado'
            OR c.fecha_vencimiento < CURRENT_DATE
          )
      AND GREATEST(c.monto_total - COALESCE(ppc.total_pagado, 0), 0) > 0
)
SELECT
    nombre_completo,
    run,
    sucursal,
    SUM(monto_pendiente)::bigint AS monto,
    COUNT(id_cuota) AS numero_cuotas
FROM cuotas_pendientes
GROUP BY nombre_completo, run, sucursal
ORDER BY monto DESC, numero_cuotas DESC, nombre_completo;

\o
