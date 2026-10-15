<?php

namespace App\Http\Requests;

use App\Enums\CategoriaArchivo;
use App\Enums\ResultadoAtencion;
use App\Services\ArchivoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro de atención técnica, compartido por la web y la app móvil.
 * Las evidencias llegan como archivos[] con categorias[] y descripciones[] en paralelo.
 */
class AtencionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['required', 'date', 'before_or_equal:now'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'diagnostico' => ['required', 'string', 'max:5000'],
            'actividades' => ['required', 'string', 'max:5000'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'resultado' => ['required', Rule::enum(ResultadoAtencion::class)],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'archivos' => ['nullable', 'array', 'max:15'],
            'archivos.*' => ArchivoService::reglas(),
            'categorias' => ['nullable', 'array'],
            'categorias.*' => ['nullable', Rule::enum(CategoriaArchivo::class)],
            'descripciones' => ['nullable', 'array'],
            'descripciones.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_inicio.before_or_equal' => 'La fecha de inicio no puede ser posterior a la hora actual.',
        ];
    }

    public function datosAtencion(): array
    {
        return $this->safe()->only(['fecha_inicio', 'fecha_fin', 'diagnostico', 'actividades', 'observaciones', 'resultado', 'latitud', 'longitud']);
    }

    public function archivosAdjuntos(): array
    {
        return collect($this->file('archivos', []))
            ->map(fn ($file, $i) => [
                'file' => $file,
                'categoria' => $this->input("categorias.$i"),
                'descripcion' => $this->input("descripciones.$i"),
            ])
            ->values()
            ->all();
    }
}
