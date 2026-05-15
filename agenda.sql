\pset pager off
\pset border 2
\pset null ''
\o agenda.txt

WITH agenda_base AS (

    SELECT
        r.fecha_inicio::date AS fecha,
        r.fecha_inicio::time AS hora,
        l.nombre AS lugar,
        'Socio: ' || COALESCE(p.nombre_completo, r.run_reservante) AS descripcion
    FROM reserva r
    JOIN lugar l
        ON r.codigo_lugar = l.codigo_lugar
    JOIN sucursal s
        ON l.codigo_sucursal = s.codigo_sucursal
    LEFT JOIN persona p
        ON r.run_reservante = p.run
    WHERE s.nombre = 'Santa Cruz'
      AND r.estado <> 'cancelada'
      AND r.fecha_inicio::date >= DATE '2026-04-06'
      AND r.fecha_inicio::date < DATE '2026-04-13'

    UNION ALL

    SELECT
        e.fecha_evento AS fecha,
        TIME '00:00' AS hora,
        l.nombre AS lugar,
        'Evento: ' || e.nombre AS descripcion
    FROM evento e
    JOIN lugar l
        ON e.codigo_lugar = l.codigo_lugar
    JOIN sucursal s
        ON l.codigo_sucursal = s.codigo_sucursal
    WHERE s.nombre = 'Santa Cruz'
      AND e.fecha_evento >= DATE '2026-04-06'
      AND e.fecha_evento < DATE '2026-04-13'
),

agenda_formateada AS (
    SELECT
        CASE EXTRACT(ISODOW FROM fecha)
            WHEN 1 THEN 'lunes'
            WHEN 2 THEN 'martes'
            WHEN 3 THEN 'miercoles'
            WHEN 4 THEN 'jueves'
            WHEN 5 THEN 'viernes'
            WHEN 6 THEN 'sabado'
            WHEN 7 THEN 'domingo'
        END AS dia,
        fecha,
        hora,
        lugar,
        descripcion
    FROM agenda_base
)

SELECT
    dia,
    TO_CHAR(fecha, 'YYYY-MM-DD') AS fecha,
    TO_CHAR(hora, 'HH24:MI') AS hora,
    lugar,
    STRING_AGG(descripcion, ' | ' ORDER BY descripcion) AS evento_o_socio
FROM agenda_formateada
GROUP BY dia, fecha, hora, lugar
ORDER BY fecha, hora, lugar;

\o