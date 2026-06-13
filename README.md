# Informe Entrega 3 - Bases de datos IIC2413

## Datos del Alumno
| **Apellidos**       | **Nombres**          | **Número de Alumno** |
|---------------------|----------------------|----------------------|
| Navarro Aragon      | Antonio              | 25663259             |


## 1. Descripción y análisis del problema

  Esta etapa consistía en construir una aplicación web para el Club Social y Deportivo DCColo. Esta empresa ficticia requería de un sistema informático para procesamiento, consulta y manipulación de datos; entre ellos el manejo de sus socios, las reservas de sus espacios y las consultas y manejo de sus cuotas y pagos.
  
  Para comenzar a analizar el problema e idear una solución, y por consiguiente el proyecto, se tuvo en consideración (con apoyo de la estructura sugerida por el enunciado) la autenticación, autorización y login de distintos tipos de socios y usuarios; manipulación mediante transacciones para un manejo completo y minimizando fallas en el pago de cuotas, organización de eventos, arriendo de canchas o administracion de adicionales.
  La aplicación final se pensó como un sistema administrativo modular. Cada funcionalidad importante quedó separada en archivos PHP o SQL específicos, lo que facilita revisar, mantener y probar cada sección de forma independiente.

## 2. Solución aplicada

La solución corresponde a una aplicación web desarrollada en **PHP**, **HTML**, **CSS** y **SQL**, enlazada a una base de datos en **PostgreSQL** usando `PDO` mediante el archivo `utils.php` entregado. La aplicación permite consultar, insertar y modificar información del sistema DCColo desde una interfaz web, usando sesiones, formularios, validaciones, transacciones, vistas, procedimientos almacenados y triggers.

#### 2.1 Manejo de usuarios

Se implementó un sistema de inicio de sesión que valida usuario y contraseña contra las tablas correspondientes de la base de datos. Sólo pueden ingresar usuarios autorizados: Administrativos, Administradores y Socios Titulares.

Cada intento de acceso, exitoso o fallido, queda registrado en el log del sistema. Además, se creó una pantalla para registrar nuevos usuarios administrativos, insertando sus datos personales, laborales y credenciales dentro de una única transacción. Si ocurre un error, la transacción se revierte.

#### 2.2 Administración de socios, beneficiarios, adicionales y cuotas

Se desarrollaron formularios para registrar Socios Titulares y para asociarles Beneficiarios o Adicionales. Estas operaciones insertan información en las entidades correspondientes y mantienen la relación con el socio titular.

También se implementó un procedimiento almacenado encargado de generar el plan de pagos mensual para el año 2026. Este procedimiento considera la membresía del socio y los costos asociados a beneficiarios o adicionales. Además, se creó un trigger que ejecuta automáticamente la actualización del plan de pagos cuando se agregan beneficiarios o adicionales.

Finalmente, se agregó una opción para registrar el pago de la cuota impaga más antigua del socio.

#### 2.3 Arriendo de canchas

Se implementó una interfaz para seleccionar una cancha disponible, escoger fecha y horario, y registrar el arriendo. La operación valida que el horario esté disponible y que el socio pueda realizar la reserva.

Cuando el usuario conectado es Socio Titular, sus datos se completan automáticamente. Para usuarios Administrativos o Administradores, se despliega una lista de socios activos. El arriendo se registra dentro de una transacción y queda en estado reservado.

#### 2.4 Consultas similares a E2

Se crearon vistas SQL para resolver los reportes solicitados en el enunciado. Estas vistas permiten consultar:

* Agenda semanal de una sucursal.
* Ingresos mensuales por membresías, reservas y eventos.
* Socios con cuotas atrasadas.
* Beneficiarios hijos que deben pagar adicional al cumplir 29 años.
* Reporte anual 2025 de ingresos por sucursal.

Las consultas se integraron a la aplicación mediante un menú disponible sólo para usuarios Administrativos o Administradores.

#### 2.5 Consulta inestructurada

Se implementó un formulario que permite ingresar tres campos arbitrarios `A`, `B` y `C` para construir una consulta del tipo:

```sql
SELECT A FROM B WHERE C;
```

Para reducir el riesgo de inyección SQL, se aplicaron validaciones sobre los campos ingresados, restringiendo caracteres peligrosos y verificando que la estructura de la consulta sea válida antes de ejecutarla.

#### 2.6 Creación de eventos y lista de invitados

Se desarrolló una pantalla para crear eventos, seleccionar sucursal, lugar, fecha y horario disponible. Además, se registra el valor del evento, el pago inicial y la lista de invitados con sus datos respectivos.

Toda la operación se ejecuta como una transacción, de modo que el evento, la reserva del lugar, el pago y los invitados se registran de forma consistente. Si alguna parte falla, no se guardan cambios parciales.


## 3. Referencias y bibliografía externa
<!-- en cada sección indica %IA, Tecnología y Prompt -->

Se utilizó asistencia de IA como apoyo para generar borradores de código, revisar lógica SQL/PHP, proponer estructuras base de archivos y para mejorar la estética general del proyecto. Para estos efectos se usó **ChatGPT-5.5 Thinking** en modo proyecto, para que, de esta manera tuviese una retroalimentación integral en todos los chats anteriores y uso de los documentos entregados.


| Componente | Uso estimado de IA | Descripción del apoyo de IA| Revisión y modificaciones manuales |
| -------------------------- | -----------------: | ---------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| `index.php` / login          |                20% | Apoyo en la estructura estética del login, validación de credenciales y manejo de sesión. | Se ajustó la integración con la base de datos, navegación, mensajes de error y presentación general.   |
| Registro de usuarios administrativos |       30% | Apoyo en la creación del formulario, validaciones básicas e inserciones relacionadas en varias tablas.              | Se revisaron campos, nombres de variables, flujo de transacción y estructura visual.                      |
| Administración de socios             |                60% | Apoyo en formularios para registrar socios titulares, beneficiarios y adicionales.                                                 | Se ajustó el flujo de datos, relaciones entre entidades, orden del código y limpieza general.             |
| Procedimiento almacenado y triggers  |                70% | Apoyo en la lógica para generar planes de pago y activar la actualización al modificar beneficiarios o adicionales.                | Se revisó la lógica SQL, ejecución en PostgreSQL y consistencia con las tablas reales.                    |
| Arriendo de canchas                  |                60% | Apoyo en la validación de disponibilidad, selección de fecha/horario y registro de reserva.                                        | Se ajustaron condiciones, presentación de opciones y navegación según tipo de usuario.                    |
| Vistas y consultas tipo E2           |                40% | Apoyo en la construcción de vistas SQL para reportes de agenda, ingresos, cuotas atrasadas, beneficiarios e ingresos por sucursal. | Se probaron las consultas y se adaptaron a los nombres reales de tablas y columnas.                       |
| Interfaz de consultas tipo E2        |                25% | Apoyo en la creación del menú y visualización de resultados desde PHP.                 | Se ajustó la presentación de tablas, filtros y estructura del archivo.                                    |
| Consulta inestructurada              |                30% | Apoyo en la construcción controlada de consultas y restricciones anti-inyección.                         | Se revisaron validaciones, restricciones de entrada y manejo de errores.                                  |
| Creación de eventos e invitados      |                70% | Apoyo en la estructura transaccional para registrar evento, lugar, horario, pago inicial e invitados.                              | Se revisó el flujo completo, formularios, transacción y consistencia de inserciones.                      |
| Estilos y presentación visual        |                20% | Apoyo menor en ideas de orden visual, formularios y presentación general.                                                          | La mayor parte de los cambios estéticos, estructura visual y limpieza fueron realizados manualmente.      |
| Informe `README.md`                  |                1% | Apoyo en solo esta tabla de registro de uso de IA      |
