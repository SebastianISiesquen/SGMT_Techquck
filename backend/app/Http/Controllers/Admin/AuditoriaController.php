<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $registros = Auditoria::with('usuario')
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->modulo, fn ($q, $v) => $q->where('modulo', $v))
            ->when($request->accion, fn ($q, $v) => $q->where('accion', $v))
            ->when($request->desde, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->hasta, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.auditoria', [
            'registros' => $registros,
            'usuarios' => User::orderBy('name')->pluck('name', 'id'),
            'modulos' => Auditoria::distinct()->orderBy('modulo')->pluck('modulo'),
            'acciones' => Auditoria::distinct()->orderBy('accion')->pluck('accion'),
        ]);
    }
}
