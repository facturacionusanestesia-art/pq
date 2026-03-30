# Sistema de Planificación Quirúrgica - Documentación Integrada (v2)

**Versión actualizada a Marzo 2026**

---

## 1. Visión general

El sistema gestiona la planificación semanal de turnos quirúrgicos en distintas clínicas, permitiendo la asignación de cirujanos y anestesistas a cada reserva. Soporta perfiles diferenciados (Administrador, Cirujano y Anestesista) con reglas de visibilidad y edición específicas.

La base de datos está normalizada y permite la generación automática de turnos, manteniendo integridad referencial y escalabilidad.

---

## 2. Reglas de negocio principales

- Cada turno (fecha + franja horaria + clínica) puede tener como máximo **2 cirujanos** y **2 anestesistas**.
- El estado del turno se calcula automáticamente:
  - **empty** (rojo): sin profesionales asignados.
  - **partial** (amarillo): solo uno de los dos roles tiene al menos un profesional.
  - **full** (verde): al menos un cirujano y un anestesista asignados (máximo 2+2).
- Una vez alcanzado el límite de 2 cirujanos o 2 anestesistas, no se pueden agregar más profesionales de ese rol.

---

## 3. Arquitectura de la base de datos

El modelo relacional incluye las siguientes tablas:

### 3.1. `professionals`
| Campo     | Tipo                   | Descripción                     |
|-----------|------------------------|---------------------------------|
| id        | INT AUTO_INCREMENT PK  | Identificador único             |
| name      | VARCHAR(100) NOT NULL  | Nombre del profesional          |
| role      | ENUM('surgeon','anesthetist') | Rol: cirujano o anestesista |

### 3.2. `clinics`
| Campo | Tipo                   | Descripción             |
|-------|------------------------|-------------------------|
| id    | INT AUTO_INCREMENT PK  | Identificador único     |
| name  | VARCHAR(100) NOT NULL UNIQUE | Nombre de la clínica |

### 3.3. `shifts`
| Campo       | Tipo                        | Descripción                               |
|-------------|-----------------------------|-------------------------------------------|
| id          | INT AUTO_INCREMENT PK       | Identificador único                       |
| shift_date  | DATE NOT NULL               | Fecha del turno                           |
| slot        | ENUM('morning','afternoon') | Franja horaria (mañana/tarde)             |
| UNIQUE key  | (shift_date, slot)          | Evita duplicados por fecha y franja       |

### 3.4. `reservations`
| Campo      | Tipo                              | Descripción                                      |
|------------|-----------------------------------|--------------------------------------------------|
| id         | INT AUTO_INCREMENT PK             | Identificador único                              |
| shift_id   | INT NOT NULL (FK → shifts.id)     | Turno asociado (ON DELETE CASCADE)               |
| clinic_id  | INT NOT NULL (FK → clinics.id)    | Clínica asociada (ON DELETE CASCADE)             |
| status     | ENUM('empty','partial','full')    | Estado de la reserva (calculado automáticamente) |
| UNIQUE key | (shift_id, clinic_id)             | Evita duplicados por turno y clínica             |

### 3.5. `reservation_professionals`
| Campo           | Tipo                | Descripción                                 |
|-----------------|---------------------|---------------------------------------------|
| id              | INT AUTO_INCREMENT PK | Identificador único                         |
| reservation_id  | INT NOT NULL (FK → reservations.id) | Reserva asociada (ON DELETE CASCADE) |
| professional_id | INT NOT NULL (FK → professionals.id) | Profesional asignado (ON DELETE CASCADE) |

### 3.6. Relaciones
- `reservations` → `shifts` (N:1)
- `reservations` → `clinics` (N:1)
- `reservation_professionals` → `reservations` (N:1)
- `reservation_professionals` → `professionals` (N:1)

El límite de **2 cirujanos y 2 anestesistas** se aplica en el código (no mediante restricciones a nivel de base de datos).

---

## 4. Perfiles y permisos

El sistema define tres perfiles:

| Perfil        | Permisos                                                                                     |
|---------------|----------------------------------------------------------------------------------------------|
| **Administrador** | Puede editar libremente la asignación de cirujanos y anestesistas en cualquier turno.        |
| **Cirujano**      | Solo puede confirmar o retirar su propia asistencia mediante la pregunta “¿Asistiré a este turno?” (botones Sí/No). No puede modificar a otros profesionales. |
| **Anestesista**   | Mismo comportamiento que el cirujano, limitado a su propio rol.                              |

- Los turnos donde el profesional está asignado se destacan visualmente (borde azul y sombra) para una identificación inmediata.
- La información mostrada en cada tarjeta de turno se adapta al perfil: los profesionales no administradores ven solo la cantidad de cirujanos/anestesistas asignados, a menos que ellos mismos formen parte de la asignación, en cuyo caso ven los nombres y el suyo aparece subrayado y en negrita.

---

## 5. Interfaz móvil

La interfaz es totalmente **responsive**:
- En pantallas pequeñas los turnos se apilan verticalmente.
- Los textos se ajustan y los botones del modal son más grandes y fáciles de pulsar.
- Se utilizan unidades relativas y media queries para garantizar usabilidad en dispositivos móviles.

---

## 6. Lógica de guardado

El estado (`empty`/`partial`/`full`) se calcula **siempre en el backend** según el número real de profesionales asignados en cada reserva.

- **Administrador**: envía una lista de IDs de cirujanos y anestesistas; el backend respeta el límite de 2 por rol (toma solo los dos primeros si se envían más).
- **Cirujano/Anestesista**: envía un campo `will_attend` (yes/no). El backend añade o elimina al profesional actual respetando los límites. Si el turno ya tiene 2 profesionales del mismo rol, no se permite añadir al actual (la acción se ignora silenciosamente).

La transacción se ejecuta dentro de `beginTransaction` / `commit` para garantizar atomicidad.

---

## 7. Flujo de usuario

1. Seleccionar el perfil en el desplegable superior (Administrador, Cirujano o Anestesista).
2. Visualizar la semana actual (colores según estado y resaltado personal si el usuario está asignado).
3. Pulsar sobre cualquier tarjeta de turno para abrir el modal de gestión.
4. **Administrador**: selecciona múltiples profesionales de las listas (máximo 2 por rol).
5. **Cirujano/Anestesista**: responde Sí/No a la pregunta “¿Asistiré a este turno?”.
6. Guardar los cambios; la página se recarga mostrando la nueva configuración.

---

## 8. Generación de turnos (población inicial)

La base de datos incluye un script de ejemplo que:
- Inserta profesionales y clínicas de muestra.
- Genera turnos para las próximas 8 semanas (de lunes a sábado, dos franjas por día).
- No crea reservas iniciales; estas se crean dinámicamente mediante la aplicación.

Para entornos de prueba se puede extender el script para crear reservas aleatorias respetando los límites de 2+2.

---

## 9. Escalabilidad y extensiones futuras

El diseño modular permite ampliaciones sin romper la estructura actual:

- Gestión de disponibilidad por profesional (vacaciones, bajas).
- Algoritmos de asignación inteligente basados en carga de trabajo.
- Estados adicionales (bloqueado, mantenimiento, urgencia).
- API REST para integración con sistemas hospitalarios (HL7, FHIR).
- Interfaz visual tipo calendario con arrastrar y soltar.

---

## 10. Conclusión

El sistema proporciona una base sólida para gestionar turnos quirúrgicos de forma estructurada, con reglas claras de negocio (máximo 2 cirujanos y 2 anestesistas, estados automáticos) y soporte para múltiples perfiles. Su diseño normalizado y su interfaz responsive lo hacen adecuado tanto para administradores como para profesionales clínicos, estando preparado para evolucionar hacia una solución completa de gestión hospitalaria.
