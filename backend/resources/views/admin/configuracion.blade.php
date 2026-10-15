<x-layouts.app title="Configuración">
    <x-page-header title="Configuración general" subtitle="Datos de la empresa, alertas, catálogos y parámetros del sistema." />

    <form method="POST" action="{{ route('configuracion.update') }}" class="space-y-6">
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
                </div>
            </div>
            <div class="space-y-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Alertas</h3></div>
                    <div class="card-body grid gap-4 sm:grid-cols-2">
                        <x-input name="avisos_garantia" label="Avisar vencimiento de garantías a los (días)" :value="$ajustes['avisos_garantia']" required
                                 help="Separados por comas. También se avisa el día del vencimiento." />
                        <x-input name="dias_alerta_garantia" type="number" min="1" label="Días para marcar «próxima a vencer»" :value="$ajustes['dias_alerta_garantia']" required />
                        <x-input name="dias_alerta_mantenimiento" type="number" min="1" label="Avisar mantenimientos con (días) de anticipación" :value="$ajustes['dias_alerta_mantenimiento']" required />
                        <x-input name="dias_incidencia_sin_atender" type="number" min="1" label="Alertar incidencias sin atender tras (días)" :value="$ajustes['dias_incidencia_sin_atender']" required />
                        <label class="flex items-start gap-2 text-sm sm:col-span-2">
                            <input type="checkbox" name="avisar_cliente_garantia" value="1" @checked($ajustes['avisar_cliente_garantia']) class="mt-0.5 rounded border-slate-300 text-brand-600">
                            <span>Enviar también el aviso de vencimiento al correo del cliente
                                <span class="block text-xs text-slate-500">Se puede desactivar por garantía. Requiere configurar el servidor de correo (SMTP) en el archivo .env.</span></span>
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
                            Exigir Acta de conformidad (o Hoja de servicio firmada) para cerrar una incidencia
                        </label>
                    </div>
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
