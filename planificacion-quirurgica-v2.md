**Documentación técnica del proyecto**
# Sistema de Planificación Quirúrgica v2 - CiruCall

**Documentación técnica completa (versión actualizada Marzo 2026)**

## 1. Visión general

El sistema gestiona la planificación semanal de turnos quirúrgicos con asignación de cirujanos y anestesistas. Soporta perfiles diferenciados (Administrador, Cirujano y Anestesista) con reglas de visibilidad y edición específicas.

### Reglas de negocio principales

- Un turno tiene máximo **2 cirujanos** y **2 anestesistas**.
- Cuando se alcanza 2 cirujanos + 2 anestesistas → el turno pasa automáticamente a **full** y nadie más puede adherirse.
- Estado del turno se calcula automáticamente:
  - **empty** (rojo): sin profesionales
  - **partial** (amarillo): solo uno de los roles
  - **full** (verde): al menos un cirujano y un anestesista (máximo 2+2)

## 2. Perfiles y permisos

- **Administrador**: Puede editar libremente cirujanos y anestesistas en cualquier turno.
- **Cirujano / Anestesista**: Solo puede confirmar o retirar su propia asistencia mediante la pregunta “¿Asistiré a este turno?” con botones Sí/No. No puede modificar otros profesionales.
- Los turnos donde el profesional está asignado se destacan visualmente con borde azul y sombra para una identificación inmediata.

## 3. Interfaz móvil

La interfaz es totalmente responsive. En pantallas pequeñas los turnos se apilan verticalmente, los textos se ajustan y los botones del modal son más grandes y fáciles de pulsar.

## 4. Lógica de guardado

- El estado (`empty`/`partial`/`full`) se calcula siempre en el backend según el número real de profesionales asignados.
- Cuando un profesional no-admin intenta unirse y ya hay 2 en su rol, la acción se ignora silenciosamente (no se permite superar el límite).

## 5. Base de datos y población inicial

La población inicial de la base de datos debe generar turnos con límite de 2 cirujanos y 2 anestesistas por reserva. Se recomienda modificar los scripts de inserción para respetar este máximo.

## 6. Flujo de usuario

1. Seleccionar perfil en el desplegable superior.
2. Visualizar la semana (colores + resaltado personal).
3. Pulsar sobre cualquier tarjeta para abrir el modal de gestión.
4. Administrador → selecciona múltiples profesionales.  
   Cirujano/Anestesista → responde Sí/No a su asistencia.

---

**Estado actual del proyecto:**  
Todas las funcionalidades solicitadas (resaltado personal, edición restringida, límite 2+2, interfaz móvil y documentación actualizada) están implementadas y funcionales.

© 2026 CiruCall - Planificación quirúrgica