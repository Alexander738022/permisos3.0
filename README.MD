# Proyecto de Despliegue de Infraestructura y Orquestación de Servicios Docker

## 🏫 Instituto Superior Tecnológico de Turismo y Patrimonio Yavirac
**Carrera:** Desarrollo de Software  
**Nivel / Paralelo:** 5to "B"  
**Integrantes:**
*   Alexander Villalva
*   Geremy Gálvez
*   Jonathan Chicaiza

---

## 📌 1. Introducción y Objetivo del Proyecto
Este documento constituye el manual técnico y de arquitectura para la configuración, despliegue y puesta en marcha de un entorno web multi-servicio orquestado mediante **Docker** y **Docker Compose**, centralizado bajo un proxy inverso seguro (**Traefik**) y administrado de forma visual a través de **Portainer EE/CE**.

El objetivo principal de esta infraestructura es albergar el sistema **TaskFlow (Gestión de Permisos)** de forma escalable, segura y bajo subdominios personalizados con certificados SSL de renovación automática mediante Let's Encrypt, garantizando una alta disponibilidad y facilidad de mantenimiento.

---

## 🏗️ 2. Arquitectura de la Infraestructura (Diagrama de Flujo)

```text
                  [ Petición HTTPS de Usuario ]
                               │
                               ▼
            ┌─────────────────────────────────────┐
            │       Traefik Reverse Proxy         │ (Puerto 80 -> HTTPS 443)
            │      (Manejo automático SSL)        │
            └──────────────────┬──────────────────┘
                               │
       ┌───────────────────────┼───────────────────────┐
       ▼                       ▼                       ▼
┌──────────────┐        ┌──────────────┐        ┌──────────────┐
│ permisos_web │        │  Portainer   │        │ phpMyAdmin   │
│  (Frontend/  │        │ (Gestión de  │        │ (Gestión de  │
│   Backend)   │        │ Contenedores)│        │Base de Datos)│
└──────┬───────┘        └──────────────┘        └──────┬───────┘
       │                                               │
       └───────────────────────┬───────────────────────┘
                               │ (Conexión Interna)
                               ▼
                        ┌──────────────┐
                        │ permisos_db  │
                        │ (MySQL 8.0)  │
                        └──────────────┘
```

---

## ⚙️ 3. Detalle de los Contenedores Desplegados

El entorno consta de **5 contenedores** interconectados dentro de una misma red virtual de Docker (`permisos_network` o similar), lo que les permite comunicarse de manera segura sin exponer puertos innecesarios al exterior:

1.  **`traefik` (Proxy Inverso):**
    *   **Función:** Recibe todo el tráfico HTTPS en el puerto `443` y HTTP en el `80`. Redirige las peticiones al contenedor correspondiente según el subdominio solicitado.
    *   **SSL:** Genera y renueva automáticamente certificados válidos y gratuitos provistos por Let's Encrypt.
2.  **`permisos_web` (Backend PHP Nativo + Frontend):**
    *   **Función:** Servidor web que procesa el código PHP del sistema de permisos y expone la API de backend, devolviendo las respuestas estructuradas en formato JSON.
3.  **`permisos_portainer` (Administrador Visual):**
    *   **Función:** Interfaz gráfica web para el control de Docker. Permite auditar recursos, ver logs en vivo y prender/apagar contenedores de forma visual en el puerto seguro.
4.  **`permisos_phpmyadmin` (Administrador de Base de Datos):**
    *   **Función:** Herramienta web para gestionar la base de datos de manera visual, realizar consultas SQL e importar respaldos.
5.  **`permisos_db` (Base de Datos MySQL 8.0):**
    *   **Función:** Motor de base de datos relacional donde se almacena de forma persistente la información de usuarios, roles, logs y solicitudes de permisos del sistema TaskFlow.

---

## 🌐 4. Asignación de Subdominios y Rutas

Para acceder de forma aislada e independiente a cada componente, se mapearon los siguientes subdominios apuntando a la IP pública del VPS:

| Servicio | Subdominio de Acceso | Protocolo | Descripción |
| :--- | :--- | :--- | :--- |
| **Portainer** | `https://alexportainer.byronrm.com` | HTTPS (SSL) | Consola de administración de Docker ||
| **Frontend/Web** | `https://permisos.byronrm.com` | HTTPS (SSL) | Interfaz de usuario para la solicitud de permisos |
| **phpMyAdmin** | `https://phpmyadmin.byronrm.com` | HTTPS (SSL) | Gestor de Base de Datos relacional |

---

## 🛠️ 5. Pasos de Configuración Realizados

### Paso 1: Instalación de Docker y Configuración Base del Servidor
*   Actualización de paquetes del sistema operativo base (Ubuntu Server LTS).
*   Instalación de `docker-ce`, `docker-ce-cli` y `containerd.io`.
*   Configuración de permisos de usuario del sistema para ejecutar comandos de Docker sin privilegios root directos.

### Paso 2: Creación de la Red Común de Contenedores
Se configuró una red tipo bridge para aislar el tráfico:
```bash
docker network create permisos30_network
```

### Paso 3: Despliegue de la Pila de Servicios (Stack)
Se configuró el archivo orquestador `docker-compose.yml` conteniendo los 5 servicios, especificando las variables de entorno, volúmenes para almacenamiento persistente y las etiquetas (*labels*) requeridas por Traefik para el ruteo dinámico.

### Paso 4: Inicialización Segura de Portainer (Setup Token)
Por motivos de seguridad informática en las versiones modernas de Portainer, el primer inicio de sesión requiere validar la propiedad del servidor mediante un token de un solo uso generado en los registros del contenedor.
1.  Se accedió por consola al servidor VPS para extraer el token en caliente:
    ```bash
    docker logs permisos_portainer 2>&1 | grep -i "token"
    ```
2.  Se extrajo el hash seguro de inicialización de administrador.
3.  Se ingresó al panel de configuración inicial para registrar las credenciales administrativas de 12 caracteres junto con el token extraído.
4.  Se vinculó el entorno local (`/var/run/docker.sock`) para heredar el control total del demonio de Docker.

---

## 📜 6. Plantilla Base de Configuración (`docker-compose.yml`)

A modo de referencia técnica, la estructura del archivo de configuración para enlazar un contenedor al proxy inverso Traefik implementa el siguiente formato de etiquetas:

```yaml
version: '3.8'

services:
  permisos_web:
    image: permisos30-web:latest
    container_name: permisos_web
    restart: always
    networks:
      - permisos_network
    labels:
      - "traefik.enable=true"
      # Configuración para el Backend
      - "traefik.http.routers.backalexis.rule=Host(`backalexis.byronrm.com`)"
      - "traefik.http.routers.backalexis.entrypoints=websecure"
      - "traefik.http.routers.backalexis.tls.certresolver=myresolver"
      - "traefik.http.services.backalexis.loadbalancer.server.port=80"
```

---

## 🔒 7. Seguridad e Higiene del Servidor
*   **Acceso SSL Forzado:** Todo tráfico entrante por el puerto HTTP ordinario (`80`) es redirigido automáticamente mediante regla global en Traefik hacia HTTPS (`443`) con algoritmos de cifrado TLS modernos.
*   **Encapsulamiento de Base de Datos:** El puerto `3306` de MySQL no está abierto al internet público. Únicamente se puede acceder a la base de datos de manera interna desde los contenedores autorizados (`permisos_web` y `permisos_phpmyadmin`).
*   **Contraseñas Robustas:** Implementación de políticas de contraseñas complejas de longitud mínima de 12 caracteres para el usuario administrador del sistema.

---

## 📝 8. Comandos Útiles de Mantenimiento

Para realizar labores de soporte o depuración rápida, los siguientes comandos en consola son de vital importancia:

*   **Verificar que todos los servicios estén corriendo:**
    ```bash
    docker ps
    ```
*   **Verificar logs en tiempo real (ej. del backend):**
    ```bash
    docker logs -f permisos_web
    ```
*   **Reiniciar la pila completa de servicios de forma limpia:**
    ```bash
    docker compose down && docker compose up -d
    ```
*   **Monitorear el uso de hardware de cada contenedor:**
    ```bash
    docker stats
    ```