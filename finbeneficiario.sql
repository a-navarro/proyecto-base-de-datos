-- IA: Archivo generado con apoyo de ChatGPT. Revise, adapte y declare su uso en el README segun las reglas del curso.
\set ON_ERROR_STOP on
\pset pager off
\pset border 2
\pset null ''
\o finbeneficiario.txt

WITH proxima_renovacion AS (
    SELECT
        id_socio_titular,
        COALESCE(
            MIN(fecha_fin) FILTER (WHERE fecha_fin >= CURRENT_DATE),
            MAX(fecha_fin)
        ) AS fecha_renovacion
    FROM public.membresia
    GROUP BY id_socio_titular
)
SELECT
    pb.run AS run_beneficiario,
    pb.nombre_completo AS nombre_beneficiario,
    pb.email AS correo_beneficiario,
    pb.telefono_celular AS celular_beneficiario,
    pt.run AS run_socio_titular,
    pt.nombre_completo AS nombre_socio_titular,
    pt.email AS correo_socio_titular,
    pt.telefono_celular AS celular_socio_titular,
    pr.fecha_renovacion
FROM public.relacion_socio AS rs
JOIN public.socio AS sb
    ON rs.id_socio_dependiente = sb.id_socio
JOIN public.persona AS pb
    ON sb.run_persona = pb.run
JOIN public.socio AS st
    ON rs.id_socio_titular = st.id_socio
JOIN public.persona AS pt
    ON st.run_persona = pt.run
JOIN proxima_renovacion AS pr
    ON st.id_socio = pr.id_socio_titular
WHERE LOWER(TRIM(rs.parentesco)) IN ('hijo', 'hija', 'hijo/a')
  AND pb.fecha_nacimiento IS NOT NULL
  AND pb.fecha_nacimiento + INTERVAL '29 years' <= pr.fecha_renovacion
  AND pb.fecha_nacimiento + INTERVAL '30 years' > pr.fecha_renovacion
ORDER BY pr.fecha_renovacion, pb.nombre_completo;

\o
