# SGGP – Sistema Web para la Gestión y Seguimiento de Garantías

Proyectos y servicios para clientes públicos y privados · **TECHQUK S.A.C.**

| Parte | Carpeta | Tecnología |
|---|---|---|
| Plataforma web (Administrador, Supervisor, Técnico) + API | `backend/` | Laravel 12 · PHP 8.2+ · MySQL/MariaDB · Blade + Tailwind CSS 4 + Alpine.js · Chart.js |
| App móvil del Técnico (Android / iOS) | `mobile/` | React Native · Expo SDK 57 · Expo Router · TypeScript |

Otras librerías: **Sanctum** (tokens de la app), **spatie/laravel-permission** (roles y permisos por módulo), **DomPDF** (PDF), **Laravel Excel** (Excel), **bacon-qr-code** (QR de los stickers), **expo-camera** (escáner en la app).

## Módulos y requerimientos

| Requerimiento | Dónde |
|---|---|
| RF01 Login por rol · RNF01 contraseñas con bcrypt | `AuthController`, `/login` · API `/api/login` |
| RF02 Usuarios, roles y permisos | *Administración → Usuarios / Roles y permisos*. El Administrador habilita módulos por rol **y** módulos adicionales por usuario |
| RF03 Clientes públicos y privados | *Clientes*: un solo módulo para entidades del Estado y empresas privadas. Tipo (público / privado) y **sector** configurable (municipal, salud, minería…). Correos adicionales que reciben los avisos de vencimiento |
| RF04 Proyectos y servicios | *Proyectos y servicios*: **implementaciones** (cámaras, servidores, cableado…) o **servicios** (mantenimiento, soporte) con fin de contrato. **Cliente final** para subcontratos (ABC contrata, el servicio es para el Ministerio), **orden de compra**. Los servicios vencidos pasan solos a *Finalizado* y dejan de listarse |
| Equipos (hoja de vida) | *Equipos*: inventario de equipos de cada cliente (marca, modelo, serie, ubicación, área, responsable) con **código interno EQ-000001 y sticker con QR**. **Historial automático**: fallas, diagnósticos, mantenimientos, cambios de componentes, entregas y traslados. Búsqueda por serie o código, **hoja de vida en PDF** con un clic, importación desde Excel. Un equipo nuevo con serie anotado en una hoja de servicio se registra solo |
| Mantenimientos programados | *Mantenimientos*: planes de N visitas cada X meses («Mantenimiento 2 de 4»), agenda de visitas próximas y atrasadas, aviso al técnico y al supervisor. La hoja de servicio de la visita la marca como realizada |
| Almacén | *Almacén*: consumibles (pasta térmica, limpia contactos, conectores) y repuestos (discos, fuentes, cámaras) con kardex de entradas, salidas y ajustes, stock mínimo con alerta diaria. La hoja de servicio **descuenta del stock** lo que el técnico usó |
| Checklists de mantenimiento | Puntos de control por categoría de equipo (laptop, cámara, NVR, servidor…). El técnico marca cada punto OK / observado / falla en la hoja; lo observado queda en el historial del equipo |
| Informe al cliente | PDF del mes por proyecto o servicio (mantenimientos, incidencias, equipos, garantías, materiales). Se envía con un clic o solo el día 1 de cada mes (`sggp:informes`) |
| Cotizaciones | *Cotizaciones*: para clientes sin garantía ni contrato que piden un diagnóstico. Ítems, IGV, PDF y seguimiento (enviada, aprobada, rechazada) |
| RF05–RF06 Garantías con estado automático | *Garantías*: **Vigente / Próxima a vencer / Vencida**. Duración libre (3 meses, 1 año, 5 años o manual), orden de compra y observaciones. **Carta de garantía en PDF** generada por el sistema |
| RF07–RF08 Incidencias y asignación | *Incidencias* por **equipo** y con **cobertura** (garantía, contrato o sin cobertura → cotización). El técnico ve los **antecedentes del equipo** antes de atender |
| RF09–RF10 Atención técnica y evidencias (hoja de servicio única) | Web: *Mis incidencias* · **App móvil**: fotos (cámara/galería), acta de conformidad, PDF, Word, Excel y ubicación GPS |
| RF11 Trazabilidad | Historial de cada incidencia: registro → asignación → atención → cierre |
| RF12 Dashboard | Indicadores y gráficos |
| RF13 Búsquedas y filtros | En todos los listados |
| RF14 Alertas | Comando diario `sggp:alertas`: **aviso de vencimiento por correo al supervisor y al cliente** a los 60, 30, 15 y 7 días (configurable) y el día del vencimiento, sin repetir avisos; mantenimientos próximos; incidencias sin atender |
| RF15 Reportes PDF / Excel | *Reportes* (8 reportes con filtros: proyectos, garantías, equipos, incidencias, atenciones, hojas, mantenimientos, cotizaciones) + informe técnico por incidencia + hoja de vida por equipo |
| RF16 / RNF07 Auditoría | *Administración → Auditoría* |
| RNF08 Archivos protegidos | Se guardan en `storage/app/private` y solo se descargan pasando el control de permisos |

**Ciclo de una incidencia:** Registrada → Asignada → En proceso → Resuelta (por el técnico) → Cerrada (el supervisor da conformidad; por defecto exige un *Acta de conformidad* adjunta o una *Hoja de servicio* firmada con conformidad). En cualquier punto puede anularse, y desde Resuelta o Cerrada puede reabrirse.

---

## 1. Ejecutar en local (XAMPP)

Requisitos: XAMPP con PHP 8.2+ y MySQL, Composer y Node.js 20 o superior.

```bash
cd backend
composer install
npm install
cp .env.example .env          # ya configurado para MySQL de XAMPP (usuario root, sin clave)
php artisan key:generate
# Crear la BD "sgmt_techquk" en phpMyAdmin (utf8mb4_unicode_ci), luego:
php artisan migrate --seed     # crea roles, permisos, admin y datos de demostración
npm run build                  # o "npm run dev" mientras desarrolla
php artisan serve --host=0.0.0.0 --port=8000
```

Abrir http://localhost:8000

| Rol | Usuario | Contraseña |
|---|---|---|
| Administrador | admin@techquk.com.pe | `Admin2026*` |
| Supervisor | supervisor@techquk.com.pe | `Techquk2026*` |
| Técnico | tecnico1@techquk.com.pe · tecnico2@techquk.com.pe | `Techquk2026*` |

> Son usuarios de demostración. Cambie las contraseñas antes de usar el sistema con datos reales.

Para ver el sistema con datos de demostración completos sin tocar su base, cree la BD `sgmt_techquk_demo` y ejecute:
`DB_DATABASE=sgmt_techquk_demo php artisan migrate:fresh --seed`

Pruebas automáticas (usan la BD `sgmt_techquk_test`, que debe existir):

```bash
php artisan test
```

### App móvil en su celular

1. Instale **Expo Go** desde Play Store o App Store. El celular y la PC deben estar en la misma red WiFi.
2. En `mobile/.env.local` ponga la IP de su PC (`ipconfig` → Dirección IPv4):
   `EXPO_PUBLIC_API_URL=http://192.168.1.103:8000`
3. Ejecute:
   ```bash
   cd mobile
   npm install
   npx expo start
   ```
4. Escanee el código QR con Expo Go e ingrese con un usuario **Técnico**.

> `mobile/.npmrc` incluye `legacy-peer-deps=true` porque los paquetes internos de Expo SDK 57 tienen un conflicto de dependencias *peer* opcionales (`react-native-worklets`) que hace fallar `npm install`. Para agregar paquetes use siempre `npx expo install <paquete>`.

El servidor también puede cambiarse desde la pantalla de login (enlace «Servidor» al pie).

---

## 2. Subir a producción (online)

### Opción recomendada: VPS (≈ USD 6–12 / mes)

DigitalOcean, Vultr, Contabo o Hostinger VPS, con Ubuntu 24.04 y 1–2 GB de RAM. Permite subir archivos grandes, programar tareas (cron) y usar HTTPS gratis.

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

# 3. Código (suba el proyecto a GitHub, privado)
sudo mkdir -p /var/www && cd /var/www
sudo git clone https://github.com/SU_USUARIO/sggp.git && cd sggp/backend
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

# 6. Tareas programadas: alertas diarias (07:00) y respaldo (02:30)
sudo crontab -e
#   * * * * * cd /var/www/sggp/backend && php artisan schedule:run >> /dev/null 2>&1
#   30 2 * * * /var/www/sggp/backend/deploy/respaldo.sh >> /var/log/sggp-respaldo.log 2>&1
```

En el proveedor de dominio, cree un registro **A** que apunte `sggp.techquk.com.pe` a la IP del VPS.

Para publicar cambios después: `bash deploy/actualizar.sh`.

**Correo (avisos a supervisores y clientes):** complete `MAIL_*` en el `.env`. Con Gmail use una *contraseña de aplicación* (cuenta Google → Seguridad → Verificación en 2 pasos → Contraseñas de aplicación); con el correo del dominio use los datos SMTP del hosting. Mientras `MAIL_MAILER=log`, los correos se escriben en `storage/logs/laravel.log` en lugar de enviarse.

**Archivos en la nube (opcional):** para guardar evidencias en S3, Cloudflare R2 o DigitalOcean Spaces en lugar del disco del servidor, ejecute `composer require league/flysystem-aws-s3-v3`, ponga `ARCHIVOS_DISK=s3` y complete las variables `AWS_*` del `.env`.

### Alternativa: hosting compartido con SSH (Hostinger Business, etc.)

Laravel funciona si el plan ofrece PHP 8.2+, MySQL, SSH y Composer:

1. Ejecute `npm run build` en su PC y suba la carpeta `backend` completa, incluida `public/build`.
2. Apunte el dominio a `backend/public`.
3. Ejecute `composer install --no-dev`, `php artisan migrate --force` y `php artisan db:seed --force`.
4. En el panel de hosting, cree el cron `php artisan schedule:run` cada minuto.

### App móvil para producción (APK)

```bash
cd mobile
npm install -g eas-cli        # o use: npx eas-cli@latest <comando>
eas login                     # cuenta gratuita en expo.dev
# Edite eas.json → EXPO_PUBLIC_API_URL con su dominio https
eas build -p android --profile preview      # genera un APK instalable para los técnicos
eas build -p android --profile production   # .aab para publicar en Google Play
```

EAS compila en la nube, así que no necesita Android Studio. Al terminar entrega un enlace para descargar el APK.

---

## Estructura

```
backend/
  app/Enums/            Estados de incidencia y garantía, prioridad, resultado, categoría de archivo
  app/Models/           Entidad (cliente), Proyecto, Garantia, Equipo + EquipoEvento (hoja de vida), PlanMantenimiento + Mantenimiento,
                        Cotizacion, Incidencia, Atencion, HojaServicio (+ equipos y materiales), Archivo, IncidenciaHistorial, Auditoria
  app/Services/         IncidenciaService (ciclo de vida), EquipoService (historial automático), AlertaGarantiaService (avisos),
                        HojaServicioService, FirmaService (firma → PNG), ArchivoService (almacenamiento privado), Auditor
  app/Http/Controllers/ Web, Api/ (app móvil) y Admin/
  app/Console/Commands/ EnviarAlertas (sggp:alertas)
  config/sggp.php       Módulos/permisos, permisos por rol y valores por defecto
  resources/views/      Interfaz Blade
  deploy/               Nginx, respaldo, actualización y .env de producción
  tests/Feature/        Pruebas del flujo completo
mobile/
  src/app/              Pantallas (login, bandeja, detalle, registrar atención, hojas de servicio, equipos + escáner QR, mantenimientos, perfil)
  src/components/       FirmaPad (firma con el dedo), Evidencias, componentes de interfaz
  src/lib/              API, sesión, selección y compresión de evidencias
```

## Protección de datos (Ley N° 29733)

- Contraseñas cifradas con bcrypt. Tokens de la app con vencimiento de 30 días y guardados en el almacenamiento seguro del teléfono.
- Control de acceso por rol y permiso en cada ruta y en cada descarga de archivo.
- HTTPS obligatorio en producción y cookies de sesión seguras y cifradas.
- Auditoría de inicios de sesión, cambios, asignaciones, archivos adjuntados y reportes exportados.
- Respaldo diario de la base de datos y los archivos (`deploy/respaldo.sh`).
- Los usuarios desactivados pierden el acceso de inmediato, también en la app.
