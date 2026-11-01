<?php

namespace App\Http\Requests;

use App\Enums\ResultadoAtencion;
use App\Enums\TipoServicio;
use App\Models\Equipo;
use App\Models\HojaServicioEquipo;
use App\Models\Incidencia;
use App\Services\ArchivoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Datos de la hoja de servicio en campo (web y app móvil). */
class HojaServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Identificador de la app: un reenvío tras cortarse la señal no duplica la hoja
            'uuid_cliente' => ['nullable', 'string', 'max:40'],
            'incidencia_id' => ['nullable', 'exists:incidencias,id'],
            'proyecto_id' => ['required_without:incidencia_id', 'nullable', 'exists:proyectos,id'],
            'garantia_id' => ['nullable', 'exists:garantias,id'],
            'mantenimiento_id' => ['nullable', 'exists:mantenimientos,id'],
            'tipo_servicio' => ['required', Rule::enum(TipoServicio::class)],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'hora_llegada' => ['required', 'date_format:H:i'],
            'hora_salida' => ['nullable', 'date_format:H:i', 'after:hora_llegada'],
            // Visita por una incidencia: la hoja registra la atención técnica (diagnóstico y resultado)
            'diagnostico' => ['required_with:incidencia_id', 'nullable', 'string', 'max:5000'],
            'resultado' => ['required_with:incidencia_id', 'nullable', Rule::enum(ResultadoAtencion::class)],
            // Si no se terminó: qué falta y cuándo se vuelve
            'pendientes' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $this->requiereVisita())],
            'proxima_visita' => ['nullable', 'date', 'after_or_equal:today', Rule::requiredIf(fn () => $this->requiereVisita())],
            'verificaciones' => ['nullable', 'array', 'max:30'],
            'verificaciones.*.item' => ['nullable', 'string', 'max:200'],
            'verificaciones.*.resultado' => ['nullable', Rule::in(['ok', 'no', 'na'])],
            // Evaluación preliminar del sitio: ambiente, aire acondicionado, material inflamable, acceso
            'evaluacion' => ['nullable', 'array', 'max:10'],
            'evaluacion.*.item' => ['nullable', 'string', 'max:80'],
            'evaluacion.*.observacion' => ['nullable', 'string', 'max:500'],
            'mediciones' => ['nullable', 'array', 'max:40'],
            'mediciones.*.parametro' => ['nullable', 'string', 'max:80'],
            'mediciones.*.valor' => ['nullable', 'string', 'max:40'],
            'mediciones.*.unidad' => ['nullable', 'string', 'max:20'],
            'trabajo_realizado' => ['required', 'string', 'max:5000'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
            'recomendaciones' => ['nullable', 'string', 'max:3000'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],

            'equipos' => ['nullable', 'array', 'max:50'],
            'equipos.*.equipo_id' => ['nullable', 'integer', 'exists:equipos,id'],
            'equipos.*.equipo' => ['nullable', 'string', 'max:255'],
            'equipos.*.marca' => ['nullable', 'string', 'max:80'],
            'equipos.*.modelo' => ['nullable', 'string', 'max:80'],
            'equipos.*.serie' => ['nullable', 'string', 'max:80'],
            'equipos.*.ubicacion' => ['nullable', 'string', 'max:255'],
            'equipos.*.accion' => ['required_with:equipos.*.equipo,equipos.*.equipo_id', 'nullable', Rule::in(array_keys(HojaServicioEquipo::ACCIONES))],
            'equipos.*.estado_final' => ['required_with:equipos.*.equipo,equipos.*.equipo_id', 'nullable', Rule::in(array_keys(HojaServicioEquipo::ESTADOS))],
            'equipos.*.observacion' => ['nullable', 'string', 'max:255'],
            // En una incidencia con varios equipos, cada uno tiene su diagnóstico, su resultado y sus fotos
            'equipos.*.diagnostico' => ['nullable', 'string', 'max:3000'],
            'equipos.*.resultado' => ['nullable', Rule::enum(ResultadoAtencion::class)],
            'equipos.*.fotos' => ['nullable', 'array', 'max:10'],
            'equipos.*.fotos.*' => ArchivoService::reglas(),
            'equipos.*.checklist' => ['nullable', 'array', 'max:40'],
            'equipos.*.checklist.*.item' => ['nullable', 'string', 'max:150'],
            'equipos.*.checklist.*.resultado' => ['nullable', Rule::in(['ok', 'observado', 'falla', 'na'])],
            'equipos.*.checklist.*.nota' => ['nullable', 'string', 'max:255'],

            'materiales' => ['nullable', 'array', 'max:50'],
            'materiales.*.articulo_id' => ['nullable', 'integer', 'exists:almacen_articulos,id'],
            'materiales.*.descripcion' => ['nullable', 'string', 'max:255'],
            'materiales.*.cantidad' => ['nullable', 'numeric', 'min:0'],
            'materiales.*.unidad' => ['nullable', 'string', 'max:20'],

            'fotos' => ['nullable', 'array', 'max:15'],
            'fotos.*' => ArchivoService::reglas(),
            'fotos_descripcion' => ['nullable', 'array'],
            'fotos_descripcion.*' => ['nullable', 'string', 'max:120'],

            // Acta de conformidad ya firmada por el cliente (foto o PDF); también se puede subir después
            'actas' => ['nullable', 'array', 'max:5'],
            'actas.*' => ArchivoService::reglas(),

            // Quién recibe el servicio (su firma va en el acta impresa)
            'receptor_nombre' => ['nullable', 'string', 'max:255'],
            'receptor_dni' => \App\Support\Reglas::documento(),
            'receptor_cargo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'La fecha del servicio no puede ser futura.',
            'hora_salida.after' => 'La hora de salida debe ser posterior a la de llegada.',
            'proyecto_id.required_without' => 'Seleccione el proyecto atendido.',
            'diagnostico.required_with' => 'Indique el diagnóstico: qué encontró y cuál es la causa de la falla.',
            'resultado.required_with' => 'Indique el resultado de la atención.',
            'pendientes.required' => 'Indique qué quedó pendiente para la próxima visita.',
            'proxima_visita.required' => 'Indique la fecha de la próxima visita.',
            'proxima_visita.after_or_equal' => 'La próxima visita no puede ser una fecha pasada.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_servicio' => 'tipo de servicio',
            'hora_llegada' => 'hora de llegada',
            'hora_salida' => 'hora de salida',
            'trabajo_realizado' => 'trabajo realizado',
            'equipos.*.accion' => 'acción sobre el equipo',
            'equipos.*.estado_final' => 'estado final del equipo',
            'equipos.*.diagnostico' => 'diagnóstico del equipo',
            'equipos.*.resultado' => 'resultado del equipo',
            'equipos.*.fotos.*' => 'foto del equipo',
            'fotos.*' => 'foto',
            'actas.*' => 'acta de conformidad',
            'proxima_visita' => 'próxima visita',
        ];
    }

    public function datosHoja(): array
    {
        return $this->safe()->only([
            'uuid_cliente', 'incidencia_id', 'proyecto_id', 'garantia_id', 'mantenimiento_id', 'tipo_servicio', 'fecha', 'hora_llegada', 'hora_salida',
            'diagnostico', 'resultado', 'pendientes', 'proxima_visita', 'trabajo_realizado', 'observaciones', 'recomendaciones',
            'verificaciones', 'evaluacion', 'mediciones', 'receptor_nombre', 'receptor_dni', 'receptor_cargo', 'latitud', 'longitud',
        ]);
    }

    /** El resultado indica que el trabajo no se terminó y hay que volver (en todos o en algunos equipos). */
    public function requiereVisita(): bool
    {
        return (bool) ResultadoAtencion::tryFrom((string) $this->input('resultado'))?->requiereVisita()
            || $this->resultadosDeEquipos()->contains(fn (ResultadoAtencion $r) => $r->requiereVisita());
    }

    /** Fotos de cada fila de equipos, con el mismo índice que equipos[]. */
    public function fotosDeEquipos(): array
    {
        return collect($this->file('equipos', []))->map(fn ($fila) => $fila['fotos'] ?? [])->filter()->all();
    }

    /**
     * Si cada equipo trae su resultado, el resultado de la visita sale de ellos:
     * todos reparados → resuelto; todos pendientes → continúa; mezcla → resuelto y los pendientes
     * pasan a otra incidencia. El diagnóstico general, si no se escribió, junta el de cada equipo.
     */
    protected function prepareForValidation(): void
    {
        $resultados = $this->resultadosDeEquipos();
        if (! $this->input('incidencia_id') || $resultados->isEmpty()) {
            return;
        }

        $pendientes = $resultados->filter(fn (ResultadoAtencion $r) => $r->requiereVisita());
        $resultado = match (true) {
            $pendientes->count() === $resultados->count() => $pendientes->contains(ResultadoAtencion::PendienteRepuesto) ? 'pendiente_repuesto' : 'parcial',
            $pendientes->isEmpty() => $resultados->contains(ResultadoAtencion::Resuelto) ? 'resuelto' : 'no_procede',
            default => 'resuelto',
        };

        $merge = ['resultado' => $resultado];
        if (blank($this->input('diagnostico'))) {
            $codigos = Equipo::whereIn('id', collect($this->input('equipos'))->pluck('equipo_id')->filter())->pluck('codigo', 'id');
            $merge['diagnostico'] = collect($this->input('equipos'))->filter(fn ($e) => filled($e['diagnostico'] ?? null))
                ->map(fn ($e) => ($codigos[$e['equipo_id'] ?? 0] ?? $e['equipo'] ?? 'Equipo').': '.$e['diagnostico'])->implode("\n") ?: null;
        }
        $this->merge($merge);
    }

    /** Cada equipo de la incidencia debe tener qué se encontró (diagnóstico) y cómo quedó (resultado). */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $incidencia = $this->input('incidencia_id') ? Incidencia::with('equipos')->find($this->input('incidencia_id')) : null;
            if (! $incidencia || $incidencia->equipos->isEmpty()) {
                return;
            }

            $filas = collect($this->input('equipos', []))->keyBy(fn ($e) => (int) ($e['equipo_id'] ?? 0));
            foreach ($incidencia->equipos as $equipo) {
                $fila = $filas->get($equipo->id);
                if (! $fila) {
                    $validator->errors()->add('equipos', "Falta el equipo {$equipo->codigo} de la incidencia: indique qué se hizo en él.");
                } elseif (blank($fila['resultado'] ?? null) || blank($fila['diagnostico'] ?? null)) {
                    $validator->errors()->add('equipos', "Indique el diagnóstico y el resultado del equipo {$equipo->codigo}.");
                }
            }
        });
    }

    /** @return \Illuminate\Support\Collection<int, ResultadoAtencion> */
    private function resultadosDeEquipos()
    {
        return collect($this->input('equipos', []))->map(fn ($e) => ResultadoAtencion::tryFrom((string) ($e['resultado'] ?? '')))->filter()->values();
    }
}
