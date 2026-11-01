# SGGP – Sistema Web para la Gestión y Seguimiento de Garantías

Proyectos y servicios para clientes públicos y privados · **TECHQUK S.A.C.**

| Parte | Carpeta | Tecnología |
|---|---|---|
| Plataforma web (Administrador, Supervisor, Técnico) + API REST | `backend/` | Laravel 12 · PHP 8.2+ · MySQL/MariaDB · Blade + Tailwind CSS 4 + Alpine.js · Chart.js |
| **App móvil del técnico** (Android) | `mobile_flutter/` | Flutter 3 · Dart |
| App móvil (versión anterior) | `mobile/` | React Native · Expo SDK 57 · TypeScript |

Otras librerías: **Sanctum** (tokens de la app), **spatie/laravel-permission** (roles y permisos por módulo), **DomPDF** (PDF), **Laravel Excel** (Excel), **bacon-qr-code** (QR de los stickers). En la app: `mobile_scanner` (QR), `image_picker` y `file_picker` (evidencias), `geolocator` (GPS), `flutter_secure_storage` (sesión) y `connectivity_plus` (trabajo sin señal).

## Módulos y requerimientos

| Requerimiento | Dónde |
|---|---|
| RF01 Login por rol · RNF01 contraseñas con bcrypt | `AuthController`, `/login` · API `/api/login`. Bloqueo temporal tras varios intentos fallidos |
| RF02 Usuarios, roles y permisos | *Administración → Usuarios / Roles y permisos*. El Administrador habilita módulos por rol **y** módulos adicionales por usuario |
| RF03 Clientes públicos y privados | *Clientes*: entidades del Estado y empresas privadas en un solo módulo, con tipo (público / privado) y **sector** configurable. RUC validado. Correos que reciben los avisos de vencimiento |
| RF04 Proyectos y servicios | **Proyectos de implementación** (cámaras, servidores, cableado…) y **contratos de servicio** (mantenimiento, soporte) con inicio y fin. **Cliente final** en subcontratos, **orden de compra**. Los **equipos se registran dentro del proyecto** (una fila por modelo con cantidad). Al crear un servicio se generan solos su garantía y su plan de mantenimientos |
| RF05–RF06 Garantías con estado automático | **Vigente / Próxima a vencer / Vencida**. Duración libre, orden de compra y observaciones. **Carta de garantía en PDF** generada por el sistema |
| Equipos (hoja de vida) | Buscador general por serie, código o cliente. **Código EQ-000001 y sticker con QR**: al escanearlo con cualquier celular se abre una **ficha pública** del equipo (datos, garantía e historial). **Historial automático** de fallas, diagnósticos, mantenimientos y cambios de componentes. Hoja de vida e informe por equipo en PDF. Importación desde Excel |
| RF07–RF08 Incidencias y asignación | Incidencias con **uno o varios equipos** y **cobertura** (garantía, contrato o sin cobertura → cotización). Asignación a técnicos con fecha límite. El técnico ve los **antecedentes de cada equipo** antes de atender |
| RF09–RF10 Hoja de servicio (registro único de la visita) | Diagnóstico, resultado, **evaluación del sitio** (ambiente, aire acondicionado, material inflamable, acceso), equipos intervenidos con **checklist**, materiales del almacén, fotos y GPS. La conformidad es el **acta firmada** que se sube (foto o PDF). Visitas de varios días: cada hoja indica lo pendiente y la próxima visita |
| Mantenimientos programados | Planes de N visitas cada X meses («Mantenimiento 2 de 4»), agenda de visitas próximas y atrasadas, aviso al técnico y al supervisor |
| Almacén | Consumibles y repuestos con kardex de entradas, salidas y ajustes; stock mínimo con alerta. La hoja de servicio **descuenta del stock** lo que el técnico usó |
| Checklists de mantenimiento | Puntos de control por tipo de equipo (laptop, cámara, NVR, servidor…): OK / observado / falla. Lo observado queda en el historial del equipo |
| Cotizaciones | Para clientes sin garantía ni contrato. Ítems, IGV y PDF. Una cotización aprobada genera la orden de trabajo o el contrato |
| Informe al cliente | PDF por proyecto o servicio (mantenimientos, incidencias, equipos, garantías, materiales). Se envía con un clic o el día 1 de cada mes (`sggp:informes`) |
| RF11 Trazabilidad | Historial de cada incidencia: registro → asignación → atención → cierre |
| RF12 Dashboard | Indicadores y gráficos: garantías, incidencias, mantenimientos, equipos con fallas repetidas, cotizaciones |
| RF13 Búsquedas y filtros | En todos los listados |
| RF14 Alertas | Comando diario `sggp:alertas`: **aviso de vencimiento por correo al supervisor y al cliente** (60, 30, 15 y 7 días, configurable), mantenimientos próximos, incidencias sin atender y stock bajo. El correo se configura en *Configuración → Correo* |
| RF15 Reportes PDF / Excel | 9 reportes con filtros: proyectos, garantías, equipos, incidencias, atenciones, hojas, mantenimientos, cotizaciones y almacén |
| RF16 / RNF07 Auditoría | *Administración → Auditoría* |
| RNF08 Archivos protegidos | Se guardan en `storage/app/private` y solo se descargan pasando el control de permisos |
| Validaciones y mensajes | Validación en pantalla y en el servidor (RUC, DNI, teléfono, montos), ventana de confirmación propia, avisos flotantes y bloqueo de doble envío |

**Ciclo de una incidencia:** Registrada → Asignada → En proceso → Resuelta (por el técnico) → Cerrada (el supervisor da conformidad con el acta firmada). En cualquier punto puede anularse, y desde Resuelta o Cerrada puede reabrirse.

---

## 1. Backend y plataforma web en local (XAMPP)

Requisitos: XAMPP con PHP 8.2+ y MySQL, Composer y Node.js 20 o superior.

```bash
cd backend
composer install
npm install
cp .env.example .env          # ya configurado para MySQL de XAMPP (usuario root, sin clave)
php artisan key:generate
# Crear la BD "sgmt_techquk" en phpMyAdmin (utf8mb4_unicode_ci), luego:
php artisan migrate --seed     # crea roles, permisos, checklists, admin y datos de demostración
npm run build                  # compila estilos y JavaScript
php artisan serve --host=0.0.0.0 --port=8000
```

Abrir http://localhost:8000

| Rol | Usuario | Contraseña |
|---|---|---|
| Administrador | admin@techquk.com.pe | `Admin2026*` |
| Supervisor | supervisor@techquk.com.pe | `Techquk2026*` |
| Técnico | tecnico1@techquk.com.pe · tecnico2@techquk.com.pe | `Techquk2026*` |

> Son usuarios de demostración. Cambie las contraseñas antes de usar el sistema con datos reales.

> Para compartir el sistema por un túnel o probarlo desde el celular use `npm run build` y no deje `npm run dev` abierto: con Vite en modo desarrollo los estilos solo cargan en la propia PC.

**Tareas programadas en Windows:** `backend/scripts/programador.vbs` ejecuta `php artisan schedule:run` sin abrir ventanas. Programe la tarea de Windows «SGGP Programador» para que lo lance cada minuto.

**Pruebas automáticas** (usan la BD `sgmt_techquk_test`, que debe existir): 94 casos, incluidos los de cada sprint (`Sprint01Test` a `Sprint04Test`).

```bash
php artisan test
```

## 2. App móvil del técnico (Flutter)

Requisitos: Flutter SDK 3 (`flutter doctor` sin errores), VS Code con las extensiones **Dart** y **Flutter**, y el backend encendido.

```bash
cd mobile_flutter
flutter pub get
```

**Desde VS Code:** abra la carpeta `mobile_flutter`, vaya a *Ejecutar y depurar* (Ctrl + Shift + D), elija una configuración y pulse **F5**:

| Configuración | Uso | Servidor |
|---|---|---|
| SGGP Técnico · Chrome | Ver la app en la PC (cámara y GPS limitados) | `http://localhost:8000` |
| SGGP Técnico · emulador | Emulador de Android Studio | `http://10.0.2.2:8000` |
| SGGP Técnico · celular (WiFi) | Celular por USB, en la misma WiFi que la PC | IP de la PC (`ipconfig`) |

**Desde la terminal:**

```bash
flutter run -d chrome --dart-define=API_URL=http://localhost:8000
flutter run --dart-define=API_URL=http://192.168.1.103:8000     # celular conectado por USB
```

El servidor también puede cambiarse desde la pantalla de inicio de sesión. Ingrese con un usuario **Técnico**.

Funciones de la app: bandeja de incidencias, hoja de servicio con equipos, checklist, materiales, fotos y acta, **borradores guardados en el teléfono y envío diferido cuando no hay señal**, escáner del QR de los equipos, hoja de vida de cada equipo y agenda de mantenimientos.

**APK para instalar en los celulares de los técnicos:**

```bash
flutter build apk --release --dart-define=API_URL=https://sggp.techquk.com.pe
# Resultado: build/app/outputs/flutter-apk/app-release.apk
```

---

## 3. Subir a producción (online)

### Opción recomendada: VPS (≈ USD 6–12 / mes)

DigitalOcean, Vultr, Contabo o Hostinger VPS, con Ubuntu 24.04 y 1–2 GB de RAM.

```bash
# 1. Paquetes
sudo apt update && sudo apt install -y nginx mysql-server git unzip certbot python3-certbot-nginx \
  php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-curl php8.3-intl php8.3-bcmath
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash - && sudo apt install -y nodejs

# 2. Base de datos
sudo mysql -e "CREATE DATABASE sgmt_techquk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'sggp'@'localhost' IDENTIFIED BY 'CLAVE_SEGURA';
  GRANT ALL ON sgmt_techquk.* TO 'sggp'@'localhost';"

# 3. Código
sudo mkdir -p /var/www && cd /var/www
sudo git clone https://github.com/SebastianISiesquen/SGMT_Techquck.git sggp && cd sggp/backend
sudo cp deploy/env.produccion.example .env     # edite dominio, claves, correo
sudo composer install --no-dev --optimize-autoloader
sudo npm ci && sudo npm run build
sudo php artisan key:generate
sudo php artisan migrate --force
sudo php artisan db:seed --force               # en producción NO crea datos de demostración
sudo php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache

# 4. Límite de subida de archivos (fotos y actas)
sudo sed -i 's/^upload_max_filesize.*/upload_max_filesize = 50M/; s/^post_max_size.*/post_max_size = 100M/' /etc/php/8.3/fpm/php.ini
sudo systemctl restart php8.3-fpm

# 5. Nginx + HTTPS
sudo cp deploy/nginx-sggp.conf /etc/nginx/sites-available/sggp
sudo ln -s /etc/nginx/sites-available/sggp /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d sggp.techquk.com.pe     # certificado SSL gratuito (Let's Encrypt)

# 6. Tareas programadas: alertas, informes y respaldo diario (02:30)
sudo crontab -e
#   * * * * * cd /var/www/sggp/backend && php artisan schedule:run >> /dev/null 2>&1
#   30 2 * * * /var/www/sggp/backend/deploy/respaldo.sh >> /var/log/sggp-respaldo.log 2>&1
```

En el proveedor de dominio, cree un registro **A** que apunte `sggp.techquk.com.pe` a la IP del VPS. Para publicar cambios después: `bash deploy/actualizar.sh`.

**Correo:** configure el servidor SMTP en *Configuración → Correo* (o en `MAIL_*` del `.env`) y use «Enviar correo de prueba». Con Gmail use una *contraseña de aplicación*.

**Archivos en la nube (opcional):** para guardar evidencias en S3, Cloudflare R2 o DigitalOcean Spaces, ejecute `composer require league/flysystem-aws-s3-v3`, ponga `ARCHIVOS_DISK=s3` y complete las variables `AWS_*` del `.env`.

---

## Estructura

```
backend/
  app/Models/           Entidad (cliente), Proyecto, Garantia, Equipo + EquipoEvento (hoja de vida), PlanMantenimiento + Mantenimiento,
                        Incidencia, Atencion, HojaServicio (+ equipos, materiales y checklist), AlmacenArticulo + AlmacenMovimiento,
                        Checklist, Cotizacion, Archivo, IncidenciaHistorial, Auditoria
  app/Services/         IncidenciaService, HojaServicioService, EquipoService (historial automático), AlmacenService (kardex),
                        AlertaGarantiaService, InformeClienteService, InformeEquipoService, ArchivoService, Auditor
  app/Http/Controllers/ Web, Api/ (app móvil) y Admin/
  app/Console/Commands/ EnviarAlertas (sggp:alertas) y EnviarInformes (sggp:informes)
  config/sggp.php       Módulos/permisos, permisos por rol y valores por defecto
  resources/views/      Interfaz Blade y plantillas PDF
  routes/api.php        API REST para la app móvil
  deploy/               Nginx, respaldo, actualización y .env de producción
  scripts/              programador.vbs (tareas programadas en Windows)
  tests/Feature/        Pruebas automáticas por módulo y por sprint
mobile_flutter/
  lib/core/             API, sesión, modelos, archivos, borradores sin conexión y tema
  lib/pantallas/        Login, bandeja, incidencia, hojas, nueva hoja, equipos, escáner QR, mantenimientos, perfil
  lib/widgets/          Componentes de interfaz
mobile/                 Versión anterior de la app (React Native / Expo)
```

## Protección de datos (Ley N° 29733)

- Contraseñas cifradas con bcrypt. Tokens de la app con vencimiento de 30 días, guardados en el almacenamiento seguro del teléfono.
- Control de acceso por rol y permiso en cada ruta y en cada descarga de archivo.
- La ficha pública del QR usa un código aleatorio: no se pueden recorrer los demás equipos.
- HTTPS obligatorio en producción y cookies de sesión seguras y cifradas.
- Auditoría de inicios de sesión, cambios, asignaciones, archivos adjuntados y reportes exportados.
- Respaldo diario de la base de datos y los archivos (`deploy/respaldo.sh`). Los respaldos `.sql` no se suben al repositorio.
- Los usuarios desactivados pierden el acceso de inmediato, también en la app.

---

**Autor:** Sebastian Inoñan Siesquen (SebastianInoSies) · Prácticas preprofesionales – Universidad Señor de Sipán · TECHQUK S.A.C., 2026
