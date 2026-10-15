<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Services\Auditor;
use App\Support\Ajustes;
use Illuminate\Http\Request;

/** Puntos de control del mantenimiento por categoría de equipo. */
class ChecklistController extends Controller
{
    public function edit()
    {
        $guardados = Checklist::pluck('items', 'categoria');
        $categorias = collect(Ajustes::catalogo('categorias_equipo'))->merge($guardados->keys())->unique()->values();

        return view('checklists.edit', ['categorias' => $categorias, 'guardados' => $guardados]);
    }

    public function update(Request $request)
    {
        $datos = $request->validate([
            'checklists' => ['array'],
            'checklists.*' => ['nullable', 'string', 'max:3000'],
        ]);

        foreach ($datos['checklists'] ?? [] as $categoria => $items) {
            $lista = collect(preg_split('/\r\n|\r|\n/', (string) $items))->map(fn ($l) => trim($l))->filter();
            $lista->isEmpty()
                ? Checklist::where('categoria', $categoria)->delete()
                : Checklist::updateOrCreate(['categoria' => $categoria], ['items' => $lista->implode("\n")]);
        }

        Auditor::log('editar', 'configuracion', 'Actualizó los checklists de mantenimiento');

        return back()->with('ok', 'Checklists guardados.');
    }
}
