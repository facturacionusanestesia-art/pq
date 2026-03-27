# Sistema de Planificación Quirúrgica  
**Documentación técnica del proyecto**

---

## 1. Visión general del sistema

El sistema implementa una plataforma de planificación quirúrgica orientada a gestionar turnos semanales en distintas clínicas, asignando profesionales (anestesistas y cirujanos) a cada reserva. La base de datos está diseñada para soportar tanto la gestión manual como la generación automática de turnos, manteniendo integridad referencial y una estructura escalable.

El objetivo principal es modelar de forma precisa la actividad quirúrgica semanal, permitiendo:

- Crear turnos por día y franja horaria.
- Generar reservas por clínica y turno.
- Asignar profesionales a cada reserva.
- Controlar el estado de ocupación de cada turno.
- Simular semanas completas con asignación aleatoria del 80% de los turnos.

---

## 2. Arquitectura de la base de datos

La arquitectura se basa en un modelo relacional normalizado, con claves externas que garantizan la integridad entre entidades.

### 2.1 Diagrama conceptual (descripción)

- **roles** (1:N) **usuarios**
- **turnos** (1:N) **reservas**
- **clinicas** (1:N) **reservas**
- **profesionales** (N:N) **reservas** mediante **reserva_profesionales**

### 2.2 Descripción de tablas

#### **roles**
Define los tipos de usuario del sistema.
- id (PK)
- nombre

#### **usuarios**
Representa a los usuarios que interactúan con la plataforma.
- id (PK)
- nombre
- rol_id (FK → roles.id)

#### **clinicas**
Centros donde se realizan intervenciones.
- id (PK)
- codigo
- nombre

#### **turnos**
Estructura temporal de la semana.
- id (PK)
- dia (Lunes–Domingo)
- franja (mañana/tarde)

#### **reservas**
Unidad fundamental de planificación: un turno en una clínica.
- id (PK)
- turno_id (FK → turnos.id)
- clinica_id (FK → clinicas.id)
- estado (`empty`, `partial`, `full`)

#### **profesionales**
Profesionales disponibles para asignación.
- id (PK)
- nombre
- tipo (`cirujano`, `anestesista`)

#### **reserva_profesionales**
Relación N:N entre reservas y profesionales.
- id (PK)
- reserva_id (FK → reservas.id)
- profesional_id (FK → profesionales.id)

---

## 3. Flujo de generación de turnos

El sistema genera automáticamente una semana completa de actividad quirúrgica siguiendo estos pasos:

### 3.1 Creación de reservas base
Se generan todas las combinaciones posibles entre:
- 14 turnos semanales (7 días × 2 franjas)
- 5 clínicas

Total: **70 reservas por semana**.

### 3.2 Selección aleatoria del 80% de reservas
Se eligen de forma probabilística mediante `RAND() < 0.8`, lo que garantiza una distribución aleatoria sin necesidad de límites dinámicos.

### 3.3 Actualización del estado
Las reservas seleccionadas se marcan como `full`.

### 3.4 Asignación de profesionales
Cada reserva ocupada recibe:
- 1 anestesista aleatorio
- 1 cirujano aleatorio

Esto garantiza que cada turno ocupado tenga un equipo mínimo.

---

## 4. Lógica de negocio

### 4.1 Estados de reserva
- **empty**: sin profesionales asignados.
- **partial**: reservado parcialmente (no usado en la generación automática actual).
- **full**: turno completo con equipo asignado.

### 4.2 Reglas de asignación
- Cada reserva ocupada debe tener al menos un anestesista y un cirujano.
- La asignación es aleatoria, pero puede ampliarse con reglas adicionales:
  - Máximo de turnos por profesional.
  - Evitar asignaciones consecutivas.
  - Preferencias por clínica.
  - Disponibilidad horaria.

---

## 5. Codificación y compatibilidad

Toda la base de datos utiliza:
- **UTF8MB4** como charset.
- **utf8mb4_unicode_ci** como collation.

Esto garantiza compatibilidad con caracteres españoles y nombres propios.

---

## 6. Escalabilidad y extensiones futuras

El diseño permite ampliaciones sin romper la estructura actual:

### 6.1 Posibles mejoras
- Gestión de disponibilidad por profesional.
- Control de vacaciones y bajas.
- Estados adicionales (bloqueado, mantenimiento, urgencia).
- Algoritmos de asignación inteligente basados en carga de trabajo.
- API REST para integración con sistemas hospitalarios.
- Interfaz visual tipo calendario.

### 6.2 Integración con estándares médicos
El sistema puede evolucionar hacia compatibilidad con:
- HL7
- FHIR
- Sistemas de historia clínica electrónica

---

## 7. Conclusión

El proyecto proporciona una base sólida para gestionar turnos quirúrgicos de forma estructurada y automatizada. Su diseño modular y normalizado permite tanto la simulación aleatoria como la planificación real, y está preparado para crecer hacia un sistema completo de gestión hospitalaria.
