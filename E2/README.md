# Informe Entrega 2 - Bases de Datos IIC2413

## Datos del Alumno

| **Apellidos** | **Nombres** | **Número de Alumno** |
|---|---|---|
| Navarro Aragon | Antonio | 25663259 |

---

## 1. Descripción y análisis del problema

El Club Social y Deportivo DCColo requiere construir una base de datos relacional a partir de archivos CSV exportados desde planillas operacionales. Los archivos originales no fueron diseñados bajo un modelo relacional normalizado, por lo que contienen datos mezclados, atributos repetidos, valores de negocio usados como identificadores, fechas en formatos distintos, posibles duplicaciones, campos vacíos, errores de codificación, referencias a entidades que todavía no existen y registros que no cumplen restricciones de integridad.

La etapa 2 del proyecto consistió en implementar dos procesos principales:

1. **Limpieza de datos con PHP (`main.php`)**: lectura de los CSV originales, normalización de valores, reparación de registros cuando era razonable hacerlo, separación de registros limpios en archivos `XXXOK.csv`, y generación de archivos `XXXERR.csv` y `XXXLOG.csv` para documentar errores y acciones de limpieza.
2. **Carga de datos con PostgreSQL (`carga.sql`)**: creación del esquema relacional, carga de los archivos limpios, distribución de la información en tablas normalizadas y validación de integridad mediante claves primarias, claves foráneas, dominios y restricciones SQL.

El objetivo técnico fue transformar información plana y parcialmente inconsistente en una instancia relacional consultable, manteniendo la mayor cantidad posible de información válida y dejando trazabilidad de los registros rechazados o no cargados.

La solución se diseñó considerando que los CSV originales identifican varias entidades por atributos de negocio, no por claves internas. Por esto, durante la carga se generaron claves sustitutas cuando correspondía, y se utilizaron relaciones intermedias para representar membresías, cuotas, pagos, reservas, eventos, asistentes, usuarios, cargos administrativos y relaciones entre socios titulares y beneficiarios.

### Estado general observado al final de las pruebas

La base queda construida y varias tablas principales cargan datos correctamente. Sin embargo, la entrega presenta limitaciones conocidas que afectan algunos resultados:

- La tabla `evento` contiene registros, pero se detectó un problema de codificación en algunos caracteres especiales, por ejemplo la letra `ñ`. Esto sugiere una inconsistencia entre la codificación del archivo original, la lectura/escritura desde PHP y/o el `client_encoding` usado al cargar en PostgreSQL.
- La tabla `reserva` queda con **0 registros** y la tabla `pago_reserva` también queda con **0 registros**. Esto impacta directamente la consulta `agenda.sql` y los ingresos por reservas ejecutadas.
- La consulta `agenda.sql` entrega 0 filas. Dado que `reserva` está vacía, no es posible listar reservas de lugares para la semana solicitada.
- La consulta `finbeneficiario.sql` entrega 0 filas. Esto puede ser correcto si no existen beneficiarios hijos que cumplan la condición de próxima renovación con 29 años, pero debe verificarse contra las fechas de nacimiento y renovación cargadas.
- La carga genera errores controlados en `errores_carga`, principalmente por reservas inválidas, pagos de membresía sin socio válido o con datos obligatorios inválidos, y cargos administrativos con persona/sucursal inexistente o fecha inválida.

Estas limitaciones no impiden que `main.php`, `carga.sql` y las consultas se ejecuten, pero sí deben considerarse al interpretar los resultados.

---

## 2. Solución aplicada

La solución se organizó en tres niveles:

1. **Preprocesamiento en PHP**: limpieza sintáctica y de formato antes de cargar al DBMS.
2. **Carga relacional en SQL**: creación de tablas, carga en tablas auxiliares o staging, transformación hacia tablas finales y registro de errores de integridad.
3. **Consultas SQL finales**: generación de archivos `.txt` solicitados en el enunciado.

La decisión central fue separar responsabilidades: PHP se utiliza para limpiar formatos y valores evidentes, mientras que PostgreSQL se utiliza para validar integridad referencial y consistencia relacional.

---

### 2.1 Limpieza de datos con PHP

El archivo `main.php` procesa los CSV originales y genera archivos limpios `XXXOK.csv`, archivos de error `XXXERR.csv` y archivos de log `XXXLOG.csv`. El criterio general aplicado fue:

- **Reparar** cuando el valor podía corregirse sin inventar información esencial.
- **Normalizar** cuando el valor era válido pero venía en otro formato.
- **Asignar `NULL` o valor vacío controlado** cuando el campo permitía nulos.
- **Rechazar o dejar para validación SQL** cuando el registro tenía errores esenciales que no podían resolverse en PHP.

#### Archivos procesados

| Archivo original | Archivo limpio esperado | Descripción |
|---|---|---|
| `personas_socios.csv` | `personas_sociosOK.csv` | Personas, socios titulares, beneficiarios, adicionales, invitados, usuarios y datos personales. |
| `sucursales_lugares.csv` | `sucursales_lugaresOK.csv` | Sucursales, lugares, precios, horarios y vigencias. |
| `reservas_arriendos.csv` | `reservas_arriendosOK.csv` | Reservas históricas de lugares y pagos asociados. |
| `eventos.csv` | `eventosOK.csv` | Eventos, clientes, contactos, asistentes y montos asociados. |
| `pagos_membresias.csv` | `pagos_membresiasOK.csv` | Cuotas de membresía, pagos, vencimientos y estados. |
| `cargos_administrativos.csv` | `cargos_administrativosOK.csv` | Cargos administrativos asociados a personas y sucursales. |

#### Tipos de limpieza aplicados

| Tipo de problema | Acción aplicada | Justificación |
|---|---|---|
| Espacios al inicio o final | Aplicación de `trim` a campos de texto | Evita errores de comparación en joins por nombres de comuna, sucursal, lugar, cargo o tipo de persona. |
| BOM al inicio de archivos | Eliminación de marca BOM en encabezados | Evita que el primer nombre de columna quede distinto, por ejemplo `﻿run_persona` en vez de `run_persona`. |
| Fechas en formato `DD-MM-YY` o `DD-MM-YYYY` | Conversión a `YYYY-MM-DD` | El enunciado exige fechas en formato SQL estándar. PostgreSQL trabaja de forma más estable con `YYYY-MM-DD`. |
| Fechas con hora | Conversión a `YYYY-MM-DD HH:MM` | Permite cargar reservas y eventos con tipo `timestamp` cuando corresponde. |
| Fechas imposibles | Rechazo o envío a log según criticidad | Una fecha de nacimiento futura, por ejemplo año 2050, no es consistente para una persona real. |
| Campos vacíos | Conversión a `NULL` o vacío controlado según el campo | Respeta la diferencia entre campo opcional y campo obligatorio. |
| Teléfonos no válidos | Normalización cuando era posible; si no, se conserva nulo cuando el campo lo permite | Los teléfonos no son clave primaria ni FK; no deben provocar pérdida de registros completos si el modelo permite nulos. |
| Correos malformados | Reparación simple cuando era evidente; si no, se deja nulo cuando corresponde | El correo puede ser nulo para personas, pero es obligatorio para usuarios del sistema. |
| RUN/RUT con formato inconsistente | Normalización de puntos, guion y dígito verificador cuando era posible | RUN y RUT son identificadores de negocio; deben tener formato consistente para poder cruzar tablas. |
| Texto con caracteres especiales | Se intentó conservar texto en UTF-8 | Existe una limitación pendiente: algunos caracteres como `ñ` no se procesaron correctamente en `evento`. |
| Listas de asistentes separadas por punto y coma | Separación de asistentes para carga posterior | El campo `lista_asistentes` representa una relación multivaluada y debe transformarse en varias filas en `asistente_evento`. |
| Duplicados operacionales | Uso de claves de negocio y `DISTINCT` en carga SQL | Evita crear varias veces la misma persona, sucursal, lugar, región, comuna, cargo o empresa. |

#### Registros reparados, anulados o eliminados

El detalle exacto de correcciones PHP se encuentra en los archivos `XXXLOG.csv` y `XXXERR.csv`. A nivel de carga final, se observaron los siguientes rechazos registrados:

| Archivo | Motivo | Acción | Cantidad |
|---|---|---:|---:|
| `cargos_administrativosOK.csv` | Cargo con persona/sucursal inexistente o fecha inválida | Cargo administrativo no cargado | 17 |
| `pagos_membresiasOK.csv` | Pago/membresía con socio inexistente o dato obligatorio inválido | Membresía, cuota o pago no cargado | 773 |
| `reservas_arriendosOK.csv` | Reserva con FK o dato obligatorio inválido | Reserva no cargada | 2000 |

La pérdida más relevante está en `reservas_arriendosOK.csv`, porque el 100% de las reservas observadas fue rechazado en la etapa SQL. Esto deja `reserva` y `pago_reserva` sin datos.

#### Limitación conocida: codificación de caracteres en eventos

Se detectó que en la tabla `evento` algunos caracteres especiales no fueron preservados correctamente, en particular la letra `ñ`. La causa más probable es una diferencia entre:

- codificación real del CSV original,
- codificación asumida por PHP al leer y escribir,
- codificación del cliente `psql`,
- codificación de la base de datos PostgreSQL.

Para corregirlo completamente, el flujo debería asegurar UTF-8 de extremo a extremo:

```bash
file -bi eventos.csv eventosOK.csv
iconv -f UTF-8 -t UTF-8 eventosOK.csv > /dev/null
```

Y en PostgreSQL:

```sql
SHOW server_encoding;
SHOW client_encoding;
```

Si aparecen caracteres como `Ã±`, `�` o secuencias similares, se debe convertir el archivo a UTF-8 antes de cargarlo o usar una conversión explícita en PHP con `mb_convert_encoding`.

#### Limitación conocida: reservas no cargadas

El resultado final muestra:

| Tabla | Registros |
|---|---:|
| `reserva` | 0 |
| `pago_reserva` | 0 |

La causa inmediata, registrada en `errores_carga`, es que las reservas tienen FK o datos obligatorios inválidos. En términos prácticos, esto puede deberse a alguno de estos problemas:

- `run_reservante` no existe en `persona`.
- El reservante existe como persona, pero no fue cargado como socio cuando la regla exige que las reservas estén asignadas a socios.
- `lugar_nombre` y `sucursal_nombre` no logran cruzar con la tabla `lugar` por diferencia de nombres, espacios, mayúsculas, tildes o codificación.
- `codigo_reserva` viene vacío, duplicado o no fue generado correctamente.
- Las fechas de inicio/fin no quedaron en formato `timestamp` válido.
- El monto o estado de reserva quedó nulo pese a ser obligatorio.

Esta limitación explica que `agenda.sql` no tenga filas y que los ingresos por reservas ejecutadas sean 0.

---

### 2.2 Carga de datos con PSQL

El archivo `carga.sql` crea el esquema relacional y carga los datos desde los CSV limpios. La carga se realizó separando entidades principales, relaciones y pagos.

#### Tablas finales y distribución de datos observada

| Tabla | Registros | Rol en el modelo |
|---|---:|---|
| `region` | 15 | Catálogo territorial de regiones. |
| `comuna` | 345 | Catálogo territorial de comunas asociadas a región. |
| `sucursal` | 4 | Sucursales del club. |
| `lugar` | 256 | Lugares o instalaciones dentro de sucursales. |
| `precio_lugar` | 2000 | Historial de precios, horarios, vigencias y tipos de precio por lugar. |
| `persona` | 1158 | Personas naturales relacionadas con el club. |
| `usuario` | 324 | Usuarios del sistema asociados a personas. |
| `socio` | 813 | Socios titulares, beneficiarios o adicionales. |
| `relacion_socio` | 281 | Relaciones entre socio titular y dependientes. |
| `membresia` | 72 | Membresías anuales asociadas a socios. |
| `cuota` | 857 | Cuotas mensuales de membresía. |
| `pago_cuota` | 585 | Pagos de cuotas de membresía. |
| `empresa` | 396 | Empresas que contratan eventos. |
| `contacto_empresa` | 398 | Contactos de empresas. |
| `evento` | 585 | Eventos históricos realizados o contratados. |
| `asistente_evento` | 26325 | Asistentes asociados a eventos. |
| `pago_evento` | 1148 | Pagos asociados a eventos. |
| `reserva` | 0 | Reservas históricas. No se cargaron por errores de FK/datos obligatorios. |
| `pago_reserva` | 0 | Pagos de reservas. Depende de `reserva`, por lo que tampoco carga. |
| `cargo` | 10 | Catálogo de cargos administrativos. |
| `persona_cargo` | 11 | Personas asignadas a cargos en sucursales. |
| `errores_carga` | 2790 | Registro de rechazos durante carga SQL. |

#### Decisiones de diseño relacional

| Decisión | Justificación |
|---|---|
| Uso de `RUN` como PK de `persona` | El RUN identifica de forma única a personas naturales en los archivos. |
| Uso de `rut_empresa` como PK de `empresa` | Las empresas se identifican por RUT y pueden contratar eventos. |
| Uso de códigos internos para `sucursal` y `lugar` | Los CSV traen nombres de negocio; el modelo necesita claves estables para FK. |
| Separación de `socio` respecto de `persona` | Una persona puede tener roles distintos; ser persona no implica necesariamente ser socio. |
| Separación de `membresia`, `cuota` y `pago_cuota` | Permite representar membresía anual, cuotas mensuales y pagos efectivos de manera independiente. |
| Separación de `evento`, `asistente_evento` y `pago_evento` | Evita mantener listas multivaluadas dentro de una sola celda y permite consultar asistentes/pagos por evento. |
| Separación de `reserva` y `pago_reserva` | Permite distinguir la reserva del pago asociado. En la carga final no se poblaron por errores de integridad. |
| Tabla `errores_carga` | Permite dejar trazabilidad de registros no cargados y motivos de rechazo. |
| Uso de FK | Delega al DBMS la validación de integridad referencial entre personas, socios, lugares, sucursales, eventos y pagos. |

#### Orden lógico de carga

El orden de carga respeta dependencias de claves foráneas:

1. `region`
2. `comuna`
3. `sucursal`
4. `lugar`
5. `precio_lugar`
6. `persona`
7. `usuario`
8. `socio`
9. `relacion_socio`
10. `membresia`
11. `cuota`
12. `pago_cuota`
13. `empresa`
14. `contacto_empresa`
15. `evento`
16. `asistente_evento`
17. `pago_evento`
18. `cargo`
19. `persona_cargo`
20. `reserva`
21. `pago_reserva`
22. `errores_carga`

Las tablas que dependen de otras se cargan después de sus entidades base para evitar violaciones de FK.

---

### 2.3 Consultas SQL

Se desarrollaron las consultas solicitadas en archivos independientes. Cada consulta genera su respectivo archivo `.txt`.

#### `agenda.sql`

**Objetivo:** listar la agenda de la sucursal Santa Cruz para la semana que comienza el 6 de abril de 2026, indicando día, fecha, hora, lugar y evento o socio que tiene reservado cada lugar.

**Estado observado:** la consulta se ejecuta, pero entrega 0 filas.

Resultado observado:

```text
+-----+-------+------+-------+----------------+
| dia | fecha | hora | lugar | evento_o_socio |
+-----+-------+------+-------+----------------+
+-----+-------+------+-------+----------------+
(0 rows)
```

**Interpretación:** el resultado vacío se explica principalmente porque `reserva` tiene 0 registros. Si la agenda depende de reservas, no hay datos desde los cuales construir el listado.

#### `ingresomensual.sql`

**Objetivo:** calcular ingresos mensuales por membresías, reservas ejecutadas y eventos, separados entre ingresos efectivamente recibidos e ingresos futuros esperados.

Resultado observado:

| Concepto | Tipo de ingreso | Monto |
|---|---|---:|
| Membresias | ingresos efectivamente recibidos | 175000 |
| Membresias | ingresos futuros esperados | 35000 |
| Reservas ejecutadas | ingresos efectivamente recibidos | 0 |
| Reservas ejecutadas | ingresos futuros esperados | 0 |
| Eventos | ingresos efectivamente recibidos | 0 |
| Eventos | ingresos futuros esperados | 0 |

**Interpretación:** la parte de membresías sí retorna montos. Las reservas aparecen en 0 porque no hay registros en `reserva` ni `pago_reserva`. Los eventos aparecen en 0 en esta consulta específica, probablemente por el filtro temporal y/o de sucursal usado para el mes actual.

#### `morosos.sql`

**Objetivo:** listar socios con cuotas atrasadas, incluyendo nombre completo, RUN, sucursal, monto y número de cuotas.

**Estado observado:** entrega 46 filas.

La consulta identifica socios con cuotas atrasadas y los ordena por monto/número de cuotas. Los mayores montos observados corresponden a socios con 8 cuotas atrasadas por $265.000.

Ejemplos de registros con mayor deuda:

| Nombre completo | RUN | Sucursal | Monto | Número de cuotas |
|---|---|---|---:|---:|
| Beatriz Alvarez Lagos | 5058856-4 | Providencia | 265000 | 8 |
| Beatriz Jara Ortiz | 22924363-2 | Santiago Centro | 265000 | 8 |
| Claudio Mendez Nunez | 23115895-2 | Providencia | 265000 | 8 |
| Daniela Henriquez Duarte | 20789127-4 | Providencia | 265000 | 8 |

#### `finbeneficiario.sql`

**Objetivo:** listar beneficiarios hijos y datos de su socio titular cuando en la próxima renovación de membresía deban pagar costo adicional por cumplir 29 años.

**Estado observado:** la consulta se ejecuta, pero entrega 0 filas.

Resultado observado:

```text
+------------------+---------------------+---------------------+----------------------+-------------------+----------------------+----------------------+-----------------------+------------------+
| run_beneficiario | nombre_beneficiario | correo_beneficiario | celular_beneficiario | run_socio_titular | nombre_socio_titular | correo_socio_titular | celular_socio_titular | fecha_renovacion |
+------------------+---------------------+---------------------+----------------------+-------------------+----------------------+----------------------+-----------------------+------------------+
+------------------+---------------------+---------------------+----------------------+-------------------+----------------------+----------------------+-----------------------+------------------+
(0 rows)
```

**Interpretación:** puede ser un resultado correcto si ningún beneficiario hijo cumple la condición etaria en la próxima renovación. Sin embargo, se recomienda verificarlo revisando `fecha_nacimiento`, `parentesco`, `fecha_fin` o fecha de renovación de membresía.

#### `ingresoporsucursal.sql`

**Objetivo:** generar reporte 2025 de ingresos por sucursal, incluyendo gerente, ingresos totales y porcentaje sobre el total del club.

Resultado observado:

| Nombre sucursal | Gerente a cargo | Ingresos totales 2025 | Porcentaje total club 2025 |
|---|---|---:|---:|
| Providencia | Loreto Alvarez Barrera | 1191836000 | 31.67 |
| Santiago Centro | Sin gerente registrado | 954838000 | 25.38 |
| Santa Cruz | Sin gerente registrado | 874391000 | 23.24 |
| La Florida | Sin gerente registrado | 741694000 | 19.71 |

**Interpretación:** la consulta entrega una distribución completa por las cuatro sucursales. Solo Providencia tiene gerente registrado en la carga final. Las otras sucursales se informan como `Sin gerente registrado`, lo que evita perderlas del reporte por ausencia de cargo asociado.

---

## 3. Comandos de ejecución

Los archivos deben ejecutarse desde el directorio `E2` del servidor del curso.

### 3.1 Ejecutar limpieza PHP

```bash
php main.php
```

Este comando lee los CSV originales y genera los archivos `XXXOK.csv`, `XXXERR.csv` y `XXXLOG.csv`.

### 3.2 Ejecutar carga SQL

```bash
psql -d "$USER" -f carga.sql
```

Si el servidor usa otro nombre de base de datos, reemplazar `$USER` por el nombre correspondiente.

### 3.3 Ejecutar consultas

```bash
psql -d "$USER" -f agenda.sql > agenda.txt
psql -d "$USER" -f ingresomensual.sql > ingresomensual.txt
psql -d "$USER" -f morosos.sql > morosos.txt
psql -d "$USER" -f finbeneficiario.sql > finbeneficiario.txt
psql -d "$USER" -f ingresoporsucursal.sql > ingresoporsucursal.txt
```

### 3.4 Verificar resultados consolidados

```bash
cat *.txt
```

---

## 4. Pruebas de validación recomendadas

Estas pruebas permiten verificar el estado actual de la base después de ejecutar `main.php`, `carga.sql` y las consultas.

### 4.1 Conteo de registros por tabla

```sql
SELECT 'asistente_evento' AS tabla, COUNT(*) AS registros FROM asistente_evento
UNION ALL SELECT 'cargo', COUNT(*) FROM cargo
UNION ALL SELECT 'comuna', COUNT(*) FROM comuna
UNION ALL SELECT 'contacto_empresa', COUNT(*) FROM contacto_empresa
UNION ALL SELECT 'cuota', COUNT(*) FROM cuota
UNION ALL SELECT 'empresa', COUNT(*) FROM empresa
UNION ALL SELECT 'errores_carga', COUNT(*) FROM errores_carga
UNION ALL SELECT 'evento', COUNT(*) FROM evento
UNION ALL SELECT 'lugar', COUNT(*) FROM lugar
UNION ALL SELECT 'membresia', COUNT(*) FROM membresia
UNION ALL SELECT 'pago_cuota', COUNT(*) FROM pago_cuota
UNION ALL SELECT 'pago_evento', COUNT(*) FROM pago_evento
UNION ALL SELECT 'pago_reserva', COUNT(*) FROM pago_reserva
UNION ALL SELECT 'persona', COUNT(*) FROM persona
UNION ALL SELECT 'persona_cargo', COUNT(*) FROM persona_cargo
UNION ALL SELECT 'precio_lugar', COUNT(*) FROM precio_lugar
UNION ALL SELECT 'region', COUNT(*) FROM region
UNION ALL SELECT 'relacion_socio', COUNT(*) FROM relacion_socio
UNION ALL SELECT 'reserva', COUNT(*) FROM reserva
UNION ALL SELECT 'socio', COUNT(*) FROM socio
UNION ALL SELECT 'sucursal', COUNT(*) FROM sucursal
UNION ALL SELECT 'usuario', COUNT(*) FROM usuario
ORDER BY tabla;
```

### 4.2 Revisar errores de carga

```sql
SELECT archivo, motivo, accion, cantidad
FROM errores_carga
ORDER BY cantidad DESC;
```

### 4.3 Diagnosticar problema de codificación en eventos

```sql
SHOW server_encoding;
SHOW client_encoding;

SELECT codigo_evento, nombre
FROM evento
WHERE nombre LIKE '%Ã%'
   OR nombre LIKE '%�%'
   OR nombre LIKE '%�%'
LIMIT 20;
```

Si esta consulta retorna filas, hay mojibake o caracteres de reemplazo, y debe revisarse la codificación de `eventos.csv`, `eventosOK.csv` y el encoding usado por `psql`.

### 4.4 Diagnosticar reservas vacías

```sql
SELECT COUNT(*) AS total_reservas FROM reserva;
SELECT COUNT(*) AS total_pagos_reserva FROM pago_reserva;

SELECT archivo, motivo, accion, cantidad
FROM errores_carga
WHERE archivo ILIKE '%reserva%';
```

Si las tablas staging se conservan después de la carga, ejecutar además:

```sql
SELECT COUNT(*) AS reservas_en_staging
FROM stg_reservas_arriendos;
```

Y revisar cruces de FK:

```sql
SELECT
    COUNT(*) FILTER (WHERE p.run IS NULL) AS sin_persona,
    COUNT(*) FILTER (WHERE l.codigo_lugar IS NULL) AS sin_lugar,
    COUNT(*) FILTER (WHERE s.id_socio IS NULL) AS sin_socio
FROM stg_reservas_arriendos r
LEFT JOIN persona p ON p.run = r.run_reservante
LEFT JOIN lugar l ON l.nombre = r.lugar_nombre
LEFT JOIN socio s ON s.run_persona = r.run_reservante;
```

### 4.5 Diagnosticar beneficiarios que cumplirían 29 años

```sql
SELECT
    p.run,
    p.nombre_completo,
    p.fecha_nacimiento,
    m.fecha_fin AS fecha_renovacion,
    EXTRACT(YEAR FROM age(m.fecha_fin, p.fecha_nacimiento)) AS edad_en_renovacion
FROM relacion_socio rs
JOIN socio sd ON sd.id_socio = rs.id_socio_dependiente
JOIN persona p ON p.run = sd.run_persona
JOIN membresia m ON m.id_socio_titular = rs.id_socio_titular
WHERE LOWER(rs.parentesco) LIKE '%hij%'
ORDER BY edad_en_renovacion DESC, p.nombre_completo
LIMIT 30;
```

---

## 5. Estado final de archivos de salida

Los resultados observados al ejecutar `cat *.txt` fueron:

| Archivo esperado | Estado observado | Comentario |
|---|---|---|
| `agenda.txt` | 0 filas | Afectado por tabla `reserva` vacía. |
| `ingresomensual.txt` | 6 filas | Membresías con montos; reservas y eventos en 0. |
| `morosos.txt` | 46 filas | Reporte funcional con socios morosos. |
| `finbeneficiario.txt` | 0 filas | Requiere validación de condición etaria. |
| `ingresoporsucursal.txt` | 4 filas | Reporte funcional por sucursal. |
| `cargaLOG.txt` / `errores_carga` | 2790 errores registrados | Errores controlados; predominan reservas. |

---

## 6. Supuestos realizados

| Supuesto | Justificación |
|---|---|
| Los nombres de sucursales son únicos | Permite crear `sucursal` desde valores distintos del CSV. |
| Los nombres de lugares son únicos dentro de cada sucursal | Permite identificar lugares combinando sucursal y nombre de lugar. |
| RUN identifica personas naturales | Es la clave de negocio más estable para personas. |
| RUT identifica empresas | Permite evitar duplicación de empresas contratantes. |
| Las fechas `DD-MM-YY` corresponden al siglo XXI cuando aparecen en datos 2024-2026 | Los datos del proyecto son recientes y se asocian al contexto operacional del club. |
| Los campos opcionales vacíos pueden representarse como `NULL` | Evita inventar información no disponible. |
| Los invitados/asistentes de eventos pueden no existir en `persona` | El enunciado permite que invitados a eventos eventualmente no estén en tabla de personas. |
| Si no hay gerente cargado para una sucursal, se informa `Sin gerente registrado` | Permite mantener la sucursal en reportes agregados aunque falte el cargo administrativo. |
| Si una reserva no cumple FK o campos obligatorios, se rechaza en SQL | Se privilegia integridad referencial antes que cargar datos inconsistentes. |

---

## 7. Limitaciones y trabajo pendiente

1. **Corregir codificación UTF-8 de eventos.**  
   La letra `ñ` y otros caracteres especiales deben preservarse desde el CSV original hasta la tabla final. Se recomienda convertir los CSV a UTF-8 y asegurar `client_encoding = UTF8` antes de ejecutar `COPY`.

2. **Recuperar carga de reservas.**  
   La tabla `reserva` está vacía porque 2000 registros fueron rechazados. Esta es la mayor pérdida de información actual. Se debe revisar la generación de `codigo_reserva`, los joins contra `persona`, `socio`, `sucursal` y `lugar`, y el formato de fechas `timestamp`.

3. **Revisar `finbeneficiario.sql`.**  
   El resultado puede ser correcto, pero conviene verificar manualmente si existen hijos con edad cercana a 29 años al momento de renovación.

4. **Revisar eventos en `ingresomensual.sql`.**  
   Aunque existen `evento` y `pago_evento`, la consulta retorna 0 para eventos. Puede deberse al filtro de mes actual o sucursal; se recomienda validar contra fechas de pago y fecha de evento.

5. **Reducir errores de carga.**  
   La tabla `errores_carga` permite priorizar: primero reservas, luego pagos de membresía, luego cargos administrativos.

---

## 8. Referencias y bibliografía externa

- Enunciado general del proyecto IIC2413, Bases de Datos, primer semestre 2026.
- Enunciado de la Entrega 2 del proyecto IIC2413, Bases de Datos, primer semestre 2026.
- Material de clases del curso IIC2413 sobre modelo relacional, restricciones, llaves, diseño entidad-relación, transformación a modelo relacional y consultas SQL.
- Documentación oficial de PostgreSQL sobre `CREATE TABLE`, claves primarias, claves foráneas, `COPY`, `JOIN`, agregaciones y funciones de fecha.
- Documentación oficial de PHP sobre manejo de archivos CSV, strings, fechas, expresiones regulares y conversión de codificación.

### Declaración de uso de IA

Se utilizó asistencia de IA para apoyo en redacción, organización del informe, revisión conceptual de problemas de carga y formulación de comandos de diagnóstico.

| Parte | % IA estimado | Tecnología | Prompt utilizado |
|---|---:|---|---|
| README.md / informe | 50% | ChatGPT 5.5 | "Ya tengo todo para entregar el proyecto, y necesito redactar el README.md. De todas maneras, no funciona todo correctamente. Por ejemplo, la letra ñ en la tabla evento, no se procesó bien. O en reserva no hay datos. Necesito que revises eso, o al menos me digas que hacer para testear y darte el estado actual de la base de datos y su funcionamiento, y lo redactes en el README correspondiente." |
| Diagnóstico de estado de base de datos | 20% | ChatGPT 5.5 | Se solicitó revisar resultados de `cat *.txt`, interpretar tablas vacías y proponer pruebas SQL para verificar codificación, reservas, errores de carga y resultados de consultas. |
| Código PHP y SQL | 60% | ChatGPT 5.5 | Se creó un proyecto con Codex, ChatGPT y consultas de generación de códigos base y plantillas para trabajar a partir de estas. |
