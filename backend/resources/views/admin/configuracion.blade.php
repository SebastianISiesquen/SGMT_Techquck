<x-layouts.app title="Configuración">
    <x-page-header title="Configuración general" subtitle="Datos de la empresa, alertas, catálogos y parámetros del sistema." />

    {{-- Estado del correo: lo primero que hay que saber si los avisos no llegan --}}
    <div class="mb-6 flex flex-wrap items-center gap-3 rounded-xl border p-4 {{ $correoActivo ? 'border-emerald-200 bg-emerald-50' : 'border-amber-300 bg-amber-50' }}">
        <x-icon name="mail" class="size-5 {{ $correoActivo ? 'text-emerald-600' : 'text-amber-600' }}" />
        <div class="min-w-0 flex-1 text-sm">
            @if ($correoActivo)
                <p class="font-semibold text-emerald-900">El envío de correos está activo</p>
                <p class="text-emerald-800">Los avisos de vencimiento e informes salen por {{ $ajustes['correo_host'] ?: config('mail.mailers.smtp.host') }}.</p>
            @else
                <p class="font-semibold text-amber-900">Los correos NO se están enviando</p>
                <p class="text-amber-800">Falta configurar el servidor de correo (abajo, en «Correo saliente»). Mientras tanto, los correos solo se guardan en el registro del sistema.</p>
            @endif
        </div>
        <form method="POST" action="{{ route('configuracion.correo-prueba') }}" class="flex flex-wrap items-center gap-2">
            @csrf
            <input type="email" name="destino" required value="{{ auth()->user()->email }}" class="input w-64 py-1.5 text-sm" aria-label="Correo para la prueba">
            <button class="btn btn-secondary btn-sm"><x-icon name="send" class="size-4" /> Enviar correo de prueba</button>
        </form>
    </div>

    <form method="POST" action="{{ route('configuracion.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Empresa</h3><span class="text-xs text-slate-500">Aparece en cartas, cotizaciones e informes</span></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <x-input name="empresa_nombre" label="Razón social" :value="$ajustes['empresa_nombre']" required class="sm:col-span-2" />
                    <x-input name="empresa_ruc" label="RUC" :value="$ajustes['empresa_ruc']" pattern="(10|15|16|17|20)[0-9]{9}" inputmode="numeric" maxlength="11" data-mensaje="El RUC debe tener 11 dígitos y empezar en 10, 15, 16, 17 o 20." />
                    <x-input name="empresa_telefono" label="Teléfono" :value="$ajustes['empresa_telefono']" pattern="[0-9+\(\)\s\-]{6,20}" inputmode="tel" maxlength="20" data-mensaje="Ingrese un teléfono válido (solo números, espacios, + o guiones)." />
                    <x-input name="empresa_direccion" label="Dirección" :value="$ajustes['empresa_direccion']" class="sm:col-span-2" />
                    <x-input name="empresa_email" type="email" label="Correo de contacto" :value="$ajustes['empresa_email']" class="sm:col-span-2" />
                    <x-input name="empresa_representante" label="Firma de cartas: nombre" :value="$ajustes['empresa_representante']" />
                    <x-input name="empresa_cargo_representante" label="Firma de cartas: cargo" :value="$ajustes['empresa_cargo_representante']" />
                    <div class="sm:col-span-2">
                        <label class="label" for="f_empresa_logo">Logo (aparece en todos los PDF)</label>
                        <div class="flex items-center gap-4">
                            @if ($logo)<img src="{{ $logo }}" alt="Logo actual" class="h-14 max-w-32 rounded border border-slate-200 bg-white object-contain p-1">@endif
                            <input id="f_empresa_logo" type="file" name="empresa_logo" accept="image/png,image/jpeg"
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                        </div>
                        @if ($logo)<label class="mt-1 flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="quitar_logo" value="1" class="rounded border-slate-300"> Quitar el logo</label>@endif
                        <p class="help">PNG o JPG, de preferencia con fondo blanco o transparente.</p>
                        @error('empresa_logo')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
            <div class="space-y-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Alertas</h3></div>
                    <div class="card-body grid gap-4 sm:grid-cols-2">
                        <x-input name="avisos_garantia" label="Avisar vencimiento de garantías a los (días)" :value="$ajustes['avisos_garantia']" required
                                 help="Separados por comas. También se avisa el día del vencimiento." />
                        <x-input name="hora_alertas" type="time" label="Hora de envío de las alertas (todos los días)" :value="$ajustes['hora_alertas']" required
                                 help="Garantías por vencer, mantenimientos próximos e incidencias sin atender." />
                        <x-input name="hora_informes" type="time" label="Hora de envío del informe mensual (día 1)" :value="$ajustes['hora_informes']" required
                                 help="Informe del mes anterior a los clientes que lo tienen activado." />
                        <x-input name="dias_alerta_garantia" type="number" min="1" label="Días para marcar «próxima a vencer»" :value="$ajustes['dias_alerta_garantia']" required />
                        <x-input name="dias_alerta_mantenimiento" type="number" min="1" label="Avisar mantenimientos con (días) de anticipación" :value="$ajustes['dias_alerta_mantenimiento']" required />
                        <x-input name="dias_incidencia_sin_atender" type="number" min="1" label="Alertar incidencias sin atender tras (días)" :value="$ajustes['dias_incidencia_sin_atender']" required />
                        <label class="flex items-start gap-2 text-sm sm:col-span-2">
                            <input type="checkbox" name="avisar_cliente_garantia" value="1" @checked($ajustes['avisar_cliente_garantia']) class="mt-0.5 rounded border-slate-300 text-brand-600">
                            <span>Enviar también el aviso de vencimiento al correo del cliente
                                <span class="block text-xs text-slate-500">Se puede desactivar por garantía. Requiere configurar el correo saliente (abajo).</span></span>
                        </label>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Parámetros</h3></div>
                    <div class="card-body grid gap-4 sm:grid-cols-2">
                        <x-input name="anios_historial" type="number" min="1" label="Años de historial gestionado" :value="$ajustes['anios_historial']" required />
                        <x-input name="igv" type="number" min="0" max="50" label="IGV (%) en cotizaciones" :value="$ajustes['igv']" required />
                        <x-input name="max_archivo_mb" type="number" min="1" label="Tamaño máximo por archivo (MB)" :value="$ajustes['max_archivo_mb']" required
                                 :help="'Límite del servidor PHP: '.ini_get('upload_max_filesize')" />
                        <label class="flex items-center gap-2 text-sm sm:col-span-2">
                            <input type="checkbox" name="requerir_acta_cierre" value="1" @checked($ajustes['requerir_acta_cierre']) class="rounded border-slate-300 text-brand-600">
                            Exigir el acta de conformidad firmada por el cliente para cerrar una incidencia
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card" x-data="{ servicio: '' }">
                <div class="card-header"><h3 class="card-title flex items-center gap-2"><x-icon name="mail" class="size-4" /> Correo saliente (SMTP)</h3></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2 flex flex-wrap gap-2 text-xs">
                        <span class="text-slate-500">Rellenar con:</span>
                        <button type="button" class="rounded bg-brand-50 px-2 py-0.5 font-semibold text-brand-700 hover:bg-brand-100" @click="$refs.host.value = 'smtp-relay.brevo.com'; $refs.puerto.value = 587; $refs.cifrado.value = 'tls'">Brevo (recomendado)</button>
                        <button type="button" class="rounded bg-slate-100 px-2 py-0.5 hover:bg-brand-50" @click="$refs.host.value = 'smtp.resend.com'; $refs.puerto.value = 465; $refs.cifrado.value = 'ssl'; $refs.usuario.value = 'resend'">Resend</button>
                        <button type="button" class="rounded bg-slate-100 px-2 py-0.5 hover:bg-brand-50" @click="$refs.host.value = 'smtp.office365.com'; $refs.puerto.value = 587; $refs.cifrado.value = 'tls'">Outlook / Microsoft 365</button>
                        <button type="button" class="rounded bg-slate-100 px-2 py-0.5 hover:bg-brand-50" @click="$refs.host.value = 'smtp.hostinger.com'; $refs.puerto.value = 465; $refs.cifrado.value = 'ssl'">Hostinger</button>
                    </div>
                    <x-input name="correo_host" label="Servidor SMTP" :value="$ajustes['correo_host']" placeholder="smtp-relay.brevo.com" x-ref="host" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-input name="correo_puerto" type="number" label="Puerto" :value="$ajustes['correo_puerto']" x-ref="puerto" />
                        <x-select name="correo_cifrado" label="Seguridad" :options="['tls' => 'TLS (587)', 'ssl' => 'SSL (465)']" :value="$ajustes['correo_cifrado']" :placeholder="false" x-ref="cifrado" />
                    </div>
                    <x-input name="correo_usuario" label="Usuario / login SMTP" :value="$ajustes['correo_usuario']" placeholder="xxxxxx@smtp-brevo.com" autocomplete="off" x-ref="usuario" />
                    <x-input name="correo_clave" type="password" label="Contraseña / clave SMTP" autocomplete="new-password"
                             :placeholder="$ajustes['correo_clave'] ? '•••••••• (guardada; escriba para cambiarla)' : ''" />
                    <x-input name="correo_remitente" type="email" label="Remitente" :value="$ajustes['correo_remitente']" placeholder="notificaciones@techquk.com.pe"
                             help="La dirección que verán los destinatarios. Debe estar verificada en el servicio de envío." />
                    <x-input name="correo_nombre" label="Nombre del remitente" :value="$ajustes['correo_nombre']" :placeholder="$ajustes['empresa_nombre']" />
                    <div class="sm:col-span-2 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                        <strong>Sin cuentas personales:</strong> use un servicio de envío como <strong>Brevo</strong> (gratis, 300 correos al día).
                        Cree una cuenta de la empresa en brevo.com, verifique el remitente (ideal: el dominio de la empresa) y en
                        <em>SMTP &amp; API → SMTP</em> copie el <em>login</em> y genere una <em>clave SMTP</em> para pegarlos aquí.
                        Los correos saldrán a nombre de la empresa, no de una persona.
                        @if ($mailerEnv === 'log' && ! $ajustes['correo_host'])<br><span class="text-amber-700">Hoy el archivo .env tiene MAIL_MAILER=log: por eso los correos no salían.</span>@endif
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title flex items-center gap-2"><x-icon name="clipboard" class="size-4" /> Hoja de servicio y QR</h3></div>
                <div class="card-body space-y-4">
                    <x-textarea name="verificaciones_previas" label="Verificaciones previas de seguridad (una por línea)" :value="$ajustes['verificaciones_previas']" rows="5"
                                help="El técnico las marca antes de empezar el servicio; aparecen en la hoja impresa." />
                    <x-textarea name="recomendaciones_generales" label="Recomendaciones generales al pie de la hoja (una por línea)" :value="$ajustes['recomendaciones_generales']" rows="4" />
                    <x-input name="url_publica" label="Dirección pública del sistema (para el QR de los stickers)" :value="$ajustes['url_publica']"
                             :placeholder="config('app.url')"
                             help="La que se abre desde cualquier celular, por ejemplo https://sggp.techquk.com.pe. En pruebas locales use la IP de la PC: http://192.168.1.103:8000" />
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Catálogos</h3><span class="text-xs text-slate-500">Una opción por línea</span></div>
            <div class="card-body grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <x-textarea name="sectores" label="Sectores de clientes" :value="$ajustes['sectores']" rows="9" required />
                <x-textarea name="tipos_proyecto" label="Rubros de proyectos de implementación" :value="$ajustes['tipos_proyecto']" rows="9" required />
                <x-textarea name="tipos_servicio" label="Tipos de contrato de servicio" :value="$ajustes['tipos_servicio']" rows="9" required />
                <x-textarea name="categorias_equipo" label="Categorías de equipos" :value="$ajustes['categorias_equipo']" rows="9" required />
                <x-textarea name="tipos_garantia" label="Tipos de garantía" :value="$ajustes['tipos_garantia']" rows="7" required />
                <x-textarea name="canales_reporte" label="Canales de reporte" :value="$ajustes['canales_reporte']" rows="7" required />
                <x-textarea name="condiciones_cotizacion" label="Condiciones por defecto de las cotizaciones" :value="$ajustes['condiciones_cotizacion']" rows="7" />
            </div>
        </div>

        <div class="flex justify-end"><button class="btn btn-primary">Guardar configuración</button></div>
    </form>
</x-layouts.app>
