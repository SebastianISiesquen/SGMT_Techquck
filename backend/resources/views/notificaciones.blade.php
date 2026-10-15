<x-layouts.app title="Notificaciones">
    <x-page-header title="Notificaciones">
        <x-slot:actions>
            <form method="POST" action="{{ route('notificaciones.leer-todas') }}">@csrf<button class="btn btn-secondary">Marcar todas como leídas</button></form>
        </x-slot:actions>
    </x-page-header>

    <div class="card divide-y divide-slate-100">
        @forelse ($notificaciones as $n)
            <a href="{{ route('notificaciones.abrir', $n->id) }}" class="flex items-start gap-3 px-5 py-4 hover:bg-slate-50 {{ $n->read_at ? '' : 'bg-brand-50/40' }}">
                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full {{ ($n->data['tipo'] ?? '') === 'garantia' ? 'bg-amber-50 text-amber-600' : 'bg-brand-50 text-brand-600' }}">
                    <x-icon :name="($n->data['tipo'] ?? '') === 'garantia' ? 'shield' : 'alert'" class="size-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm {{ $n->read_at ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $n->data['titulo'] ?? '' }}</p>
                    <p class="text-sm text-slate-500">{{ $n->data['mensaje'] ?? '' }}</p>
                </div>
                <span class="shrink-0 text-xs text-slate-400">{{ $n->created_at->diffForHumans() }}</span>
            </a>
        @empty
            <x-empty icon="bell" text="No tiene notificaciones." />
        @endforelse
    </div>
    @if ($notificaciones->hasPages())<div class="mt-4">{{ $notificaciones->links() }}</div>@endif
</x-layouts.app>
