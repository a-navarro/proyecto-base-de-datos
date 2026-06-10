-- IA: SQL creado con asistencia de IA para el punto 2.2 de la Etapa 3 IIC2413.
-- Porcentaje estimado de apoyo IA: 80%.
-- Tecnología utilizada: ChatGPT.
-- Prompt utilizado: "vamos con la siguiente opcion: administrar socios".
-- El estudiante debe revisar, adaptar y comprender completamente este código.

-- SP: crea o recalcula el plan mensual de pagos 2026 de un Socio Titular.
-- Regla usada:
--   monto_base = precio_socio de la sucursal base del titular.
--   monto_adicional = precio_adicional * cantidad de adicionales activos
--                     + precio_adicional * beneficiarios hijos que ya tienen 29 años o más al 2026.
--   monto_pagado queda como el total de la cuota a pagar, aunque fecha_pago queda NULL hasta que se pague.

CREATE OR REPLACE FUNCTION public.sp_crear_plan_pagos_2026(p_id_socio_titular integer)
RETURNS void
LANGUAGE plpgsql
AS $$
DECLARE
    v_codigo_sucursal character varying(10);
    v_monto_base integer := 0;
    v_precio_adicional integer := 0;
    v_cantidad_adicionales integer := 0;
    v_cantidad_beneficiarios_cobrables integer := 0;
    v_monto_adicional integer := 0;
    v_total integer := 0;
    v_mes integer;
BEGIN
    -- Evita choques con ids existentes, porque el dump deja secuencias en 1.
    PERFORM setval(
        'public.pago_cuota_id_pago_cuota_seq',
        COALESCE((SELECT MAX(id_pago_cuota) FROM public.pago_cuota), 0) + 1,
        false
    );

    SELECT s.codigo_sucursal_base,
           COALESCE(sc.precio_socio, 0),
           COALESCE(sc.precio_adicional, 0)
    INTO v_codigo_sucursal, v_monto_base, v_precio_adicional
    FROM public.socio s
    LEFT JOIN public.sucursal sc
        ON sc.codigo_sucursal = s.codigo_sucursal_base
    WHERE s.id_socio = p_id_socio_titular
      AND LOWER(s.tipo_socio) = 'socio_titular';

    IF NOT FOUND THEN
        RAISE EXCEPTION 'El socio % no existe o no es socio titular', p_id_socio_titular;
    END IF;

    SELECT COUNT(*)
    INTO v_cantidad_adicionales
    FROM public.relacion_socio r
    INNER JOIN public.socio sd
        ON sd.id_socio = r.id_socio_dependiente
    WHERE r.id_socio_titular = p_id_socio_titular
      AND LOWER(sd.tipo_socio) = 'adicional'
      AND (sd.fecha_fin IS NULL OR sd.fecha_fin >= DATE '2026-01-01');

    SELECT COUNT(*)
    INTO v_cantidad_beneficiarios_cobrables
    FROM public.relacion_socio r
    INNER JOIN public.socio sd
        ON sd.id_socio = r.id_socio_dependiente
    INNER JOIN public.persona p
        ON p.run = sd.run_persona
    WHERE r.id_socio_titular = p_id_socio_titular
      AND LOWER(sd.tipo_socio) = 'beneficiario'
      AND LOWER(r.parentesco) IN ('hijo', 'hija')
      AND p.fecha_nacimiento IS NOT NULL
      AND p.fecha_nacimiento <= DATE '1997-12-31'
      AND (sd.fecha_fin IS NULL OR sd.fecha_fin >= DATE '2026-01-01');

    v_monto_adicional := (v_cantidad_adicionales + v_cantidad_beneficiarios_cobrables) * v_precio_adicional;
    v_total := v_monto_base + v_monto_adicional;

    -- Recalcula solo cuotas impagas. No toca cuotas ya pagadas.
    DELETE FROM public.pago_cuota
    WHERE id_socio = p_id_socio_titular
      AND fecha_pago IS NULL
      AND medio_pago IS NULL
      AND cuota_numero BETWEEN 1 AND 12;

    FOR v_mes IN 1..12 LOOP
        INSERT INTO public.pago_cuota (
            cuota_numero,
            fecha_pago,
            monto_pagado,
            medio_pago,
            id_socio,
            monto_base,
            monto_adicional
        ) VALUES (
            v_mes,
            NULL,
            v_total,
            NULL,
            p_id_socio_titular,
            v_monto_base,
            v_monto_adicional
        );
    END LOOP;
END;
$$;

-- Función del trigger: al cambiar la relación titular-dependiente, recalcula el plan del titular.
CREATE OR REPLACE FUNCTION public.trg_recalcular_plan_2026_por_dependiente()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    v_id_titular integer;
BEGIN
    IF TG_OP = 'DELETE' THEN
        v_id_titular := OLD.id_socio_titular;
    ELSE
        v_id_titular := NEW.id_socio_titular;
    END IF;

    IF v_id_titular IS NOT NULL THEN
        PERFORM public.sp_crear_plan_pagos_2026(v_id_titular);
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS tr_recalcular_plan_2026_relacion_socio ON public.relacion_socio;

CREATE TRIGGER tr_recalcular_plan_2026_relacion_socio
AFTER INSERT OR UPDATE OR DELETE ON public.relacion_socio
FOR EACH ROW
EXECUTE FUNCTION public.trg_recalcular_plan_2026_por_dependiente();
