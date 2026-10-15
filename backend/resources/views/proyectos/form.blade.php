<x-layouts.app :title="$proyecto->exists ? 'Editar proyecto' : 'Nuevo proyecto o servicio'">
    <x-page-header :title="$proyecto->exists ? 'Editar '.$proyecto->codigo : 'Registrar proyecto o contrato de servicio'"
                   :back="$proyecto->exists ? route('proyectos.show', $proyecto) : route('proyectos.index')" />

    @php
        $plan = $proyecto->exists ? $proyecto->planesMantenimiento()->first() : null;
        $garantiaServicio = $proyecto->exists ? $proyecto->garantias()->first() : null;
    @endphp

    <form method="POST" action="{{ $proyecto->exists ? route('proyectos.update', $proyecto) : route('proyectos.store') }}" enctype="multipart/form-data" class="card"
          x-data="{ clase: '{{ old('clase', $proyecto->clase) }}', subcontrato: {{ old('cliente_final_id', $proyecto->cliente_final_id) ? 'true' : 'false' }},
                    visitas: '{{ old('visitas_total', '') }}', frecuencia: '{{ old('visitas_frecuencia', 3) }}' }">
        @csrf
        @if ($proyecto->exists) @method('PUT') @endif
        @if (! empty($cotizacion))
            <input type="hidden" name="cotizacion_id" value="{{ $cotizacion->id }}">
            <div class="mx-5 mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                A partir de la cotización aprobada <strong>{{ $cotizacion->numero }}</strong> · {{ $cotizacion->monto($cotizacion->total) }}
            </div>
        @endif
        <div class="card-body space-y-6">
            {{-- 1. Qué se registra --}}
            <div>
                <label class="label">¿Qué se registra? <span class="text-red-500">*</span></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'implementacion' => ['Proyecto de implementación', 'La empresa instala algo nuevo: cámaras, servidores, cableado, redes, radioenlaces… Al entregarlo empieza la garantía de los equipos (ej. 5 años).', 'folder'],
                        'servicio' => ['Contrato de servicio', 'La empresa atiende equipos que el cliente ya tiene, durante un periodo: mantenimiento preventivo, soporte técnico… (ej. 1 año, 4 mantenimientos).', 'wrench'],
                    ] as $valor => [$titulo, $desc, $icono])
                        <label class="flex cursor-pointer gap-3 rounded-lg border p-3 transition"
                               :class="clase === '{{ $valor }}' ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500' : 'border-slate-200 hover:border-slate-300'">
                            <input type="radio" name="clase" value="{{ $valor }}" x-model="clase" class="mt-1 text-brand-600">
                            <span>
                                <span class="flex items-center gap-1.5 text-sm font-medium text-slate-800"><x-icon :name="$icono" class="size-4" /> {{ $titulo }}</span>
                                <span class="block text-xs text-slate-500">{{ $desc }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('clase')<p class="error">{{ $message }}</p>@enderror
            </div>

            {{-- 2. Datos generales --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input name="nombre" label="Nombre" :value="$proyecto->nombre" required
                             x-bind:placeholder="clase === 'servicio' ? 'Ej. Mantenimiento preventivo de 20 laptops 2026' : 'Ej. Sistema de videovigilancia urbana – Centro de Chiclayo'" />
                </div>
                <x-select name="entidad_id" label="Cliente (quien contrata a la empresa)" :options="$entidades" :value="$proyecto->entidad_id" required />
                <div x-show="clase !== 'servicio'">
                    <x-select name="tipo" label="Rubro del proyecto" :options="$tipos" :list="true" :value="$proyecto->esServicio() ? null : $proyecto->tipo"
                              required x-bind:disabled="clase === 'servicio'" />
                </div>
                <div x-show="clase === 'servicio'" x-cloak>
                    <x-select name="tipo" label="Tipo de servicio" :options="$tiposServicio" :list="true" :value="$proyecto->esServicio() ? $proyecto->tipo : null"
                              required x-bind:disabled="clase !== 'servicio'" />
                </div>

                <div class="sm:col-span-2 rounded-lg border border-slate-200 p-3">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" x-model="subcontrato" class="rounded border-slate-300 text-brand-600">
                        Es un subcontrato: el trabajo se realiza para un cliente final distinto
                    </label>
                    <p class="help">Ej.: la empresa ABC ganó la licitación del Ministerio de Justicia y subcontrata a la empresa. Los informes y cartas se dirigen a ABC; el trabajo se realiza para el Ministerio.</p>
                    <div x-show="subcontrato" x-cloak class="mt-3">
                        <x-select name="cliente_final_id" label="Cliente final (quien recibe el trabajo)" :options="$entidades" :value="$proyecto->cliente_final_id"
                                  placeholder="Seleccione…" x-bind:disabled="!subcontrato" />
                    </div>
                </div>

                <x-input name="codigo" label="Código interno" :value="$proyecto->codigo" help="Si se deja vacío se genera automáticamente (PRY-AAAA-00001)." />
                <x-input name="orden_compra" label="Orden de compra / servicio" :value="$proyecto->orden_compra" placeholder="OC-2026-0135" />
                <x-input name="numero_contrato" label="N° de contrato" :value="$proyecto->numero_contrato" />
                <x-input name="monto_contrato" type="number" step="0.01" min="0" max="9999999999.99" label="Monto (S/)" :value="$proyecto->monto_contrato" />
                <x-input name="ubicacion" label="Ubicación / sede del cliente" :value="$proyecto->ubicacion" class="sm:col-span-2" />
                <x-select name="responsable_id" label="Responsable (supervisor)" :options="$responsables" :value="$proyecto->responsable_id" placeholder="Sin asignar" />
                <x-select name="estado" label="Estado" :options="\App\Models\Proyecto::ESTADOS" :value="$proyecto->estado" :placeholder="false" required />
            </div>

            {{-- 3a. Fechas de una implementación --}}
            <div x-show="clase !== 'servicio'" class="rounded-lg border border-slate-200 p-4">
                <h3 class="mb-3 text-sm font-semibold text-slate-800">Ejecución del proyecto</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="fecha_inicio" type="date" label="Inicio de la instalación" :value="$proyecto->fecha_inicio" />
                    <div>
                        <label class="label" for="f_fecha_entrega_impl">Fecha de entrega / conformidad <span class="text-red-500">*</span></label>
                        <input id="f_fecha_entrega_impl" name="fecha_entrega" type="date" class="input @error('fecha_entrega') input-error @enderror"
                               value="{{ old('fecha_entrega', $proyecto->fecha_entrega?->format('Y-m-d')) }}" x-bind:disabled="clase === 'servicio'" :required="clase !== 'servicio'">
                        <p class="help">Desde esta fecha corre la garantía. Se registran proyectos de los últimos {{ \App\Support\Ajustes::int('anios_historial') }} años.</p>
                        @error('fecha_entrega')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- 3b. Condiciones de un contrato de servicio --}}
            <div x-show="clase === 'servicio'" x-cloak class="rounded-lg border border-brand-200 bg-brand-50/30 p-4">
                <h3 class="mb-3 text-sm font-semibold text-slate-800">Condiciones del servicio</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="f_fecha_entrega_serv">Inicio del contrato <span class="text-red-500">*</span></label>
                        <input id="f_fecha_entrega_serv" name="fecha_entrega" type="date" class="input @error('fecha_entrega') input-error @enderror"
                               value="{{ old('fecha_entrega', $proyecto->fecha_entrega?->format('Y-m-d')) }}" x-bind:disabled="clase !== 'servicio'" :required="clase === 'servicio'">
                        @error('fecha_entrega')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <x-input name="fecha_fin" type="date" label="Fin del contrato" :value="$proyecto->fecha_fin" x-bind:required="clase === 'servicio'"
                                 help="Al pasar esta fecha el servicio queda Finalizado y deja de mostrarse en las listas." />
                    </div>

                    @if ($proyecto->exists)
                        <div class="sm:col-span-2 grid gap-3 text-sm sm:grid-cols-2">
                            <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                                <p class="text-xs font-medium text-slate-500">Mantenimientos incluidos</p>
                                @if ($plan)
                                    <p class="font-medium text-slate-800">{{ $plan->total_visitas }} visitas, cada {{ $plan->frecuencia_meses }} mes(es)</p>
                                    <a href="{{ route('planes.edit', $plan) }}" class="text-xs text-brand-600 hover:underline">Modificar el plan de mantenimiento</a>
                                @else
                                    <a href="{{ route('planes.create', ['proyecto_id' => $proyecto->id]) }}" class="text-xs text-brand-600 hover:underline">Programar mantenimientos</a>
                                @endif
                            </div>
                            <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                                <p class="text-xs font-medium text-slate-500">Garantía del servicio</p>
                                @if ($garantiaServicio)
                                    <p class="font-medium text-slate-800">{{ $garantiaServicio->codigo }} · vence {{ $garantiaServicio->fecha_vencimiento->format('d/m/Y') }}</p>
                                    <a href="{{ route('garantias.edit', $garantiaServicio) }}" class="text-xs text-brand-600 hover:underline">Modificar la garantía</a>
                                @else
                                    <a href="{{ route('garantias.create', ['proyecto_id' => $proyecto->id]) }}" class="text-xs text-brand-600 hover:underline">Registrar garantía</a>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="sm:col-span-2 rounded-lg bg-white p-3 ring-1 ring-slate-200">
                            <p class="text-sm font-medium text-slate-800">Mantenimientos preventivos incluidos</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-700">
                                <input name="visitas_total" type="number" min="1" max="52" x-model="visitas" placeholder="Ej. 4" class="input w-24" x-bind:disabled="clase !== 'servicio'">
                                <span>visitas, una cada</span>
                                <input name="visitas_frecuencia" type="number" min="1" max="24" x-model="frecuencia" class="input w-20" x-bind:disabled="clase !== 'servicio'">
                                <span>mes(es)</span>
                            </div>
                            <p class="help" x-show="visitas" x-text="'Se programarán ' + visitas + ' visitas («Mantenimiento 1 de ' + visitas + '», «2 de ' + visitas + '»…) desde el inicio del contrato, y se avisará al técnico antes de cada una.'"></p>
                            <p class="help" x-show="!visitas">Déjelo vacío si el servicio es solo soporte a pedido (sin visitas programadas).</p>
                            @error('visitas_total')<p class="error">{{ $message }}</p>@enderror
                            @error('visitas_frecuencia')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2 rounded-lg bg-white p-3 ring-1 ring-slate-200">
                            <p class="text-sm font-medium text-slate-800">Garantía de los trabajos</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-700">
                                <input name="garantia_meses" type="number" min="0" max="60" value="{{ old('garantia_meses', 3) }}" class="input w-20" x-bind:disabled="clase !== 'servicio'">
                                <span>meses después de terminado el contrato</span>
                            </div>
                            <p class="help">Se crea la garantía del servicio: cubre los trabajos desde el inicio del contrato hasta esos meses después de su fin. Ponga 0 si no ofrece garantía.</p>
                            @error('garantia_meses')<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                </div>
            </div>

            <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 text-sm">
                <input type="hidden" name="informe_mensual" value="0">
                <input type="checkbox" name="informe_mensual" value="1" @checked(old('informe_mensual', $proyecto->informe_mensual)) class="mt-0.5 rounded border-slate-300 text-brand-600">
                <span><span class="font-medium text-slate-800">Enviar informe mensual al cliente</span>
                    <span class="block text-xs text-slate-500">El día 1 de cada mes se envía por correo el informe del mes anterior: mantenimientos, incidencias, estado de los equipos y garantías.</span></span>
            </label>

            <x-textarea name="descripcion" label="Descripción / alcance" :value="$proyecto->descripcion" rows="3"
                        x-bind:placeholder="clase === 'servicio' ? 'Qué incluye cada visita: limpieza, actualización, revisión de discos y baterías…' : 'Qué se instaló y dónde.'" />

            {{-- 4. Equipos --}}
            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="text-sm font-semibold text-slate-800">
                    <span x-show="clase !== 'servicio'">{{ $proyecto->exists ? 'Agregar equipos instalados' : 'Equipos instalados' }}</span>
                    <span x-show="clase === 'servicio'" x-cloak>{{ $proyecto->exists ? 'Agregar equipos del cliente al servicio' : 'Equipos del cliente incluidos en el servicio' }}</span>
                </h3>
                <p class="mb-3 text-xs text-slate-500">
                    <span x-show="clase !== 'servicio'">Cámaras, servidores, switches, NVR… que la empresa instaló en este proyecto.</span>
                    <span x-show="clase === 'servicio'" x-cloak>Los equipos que el cliente ya tiene y que cubre el contrato (ej. sus 20 laptops y 2 servidores).</span>
                    @if ($proyecto->exists) Los {{ $proyecto->equipos()->count() }} equipos ya registrados se ven en la ficha. @endif
                </p>
                @include('proyectos._equipos-filas', ['iniciales' => []])
            </div>

            @unless ($proyecto->exists)
                <div>
                    <label class="label">Documentos (contrato, orden de compra, actas, planos…)</label>
                    <input type="file" name="archivos[]" multiple class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                    <p class="help">PDF, Word, Excel o imágenes. Podrá agregar más documentos después.</p>
                </div>
            @endunless
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <a href="{{ $proyecto->exists ? route('proyectos.show', $proyecto) : route('proyectos.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
