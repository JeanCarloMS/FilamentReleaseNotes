<x-filament-panels::page>
    @php
        $summary = $releaseNotes['summary'] ?? ['total' => 0, 'features' => 0, 'fixes' => 0, 'docs' => 0, 'chores' => 0];
        $groupedCommits = $releaseNotes['groupedCommits'] ?? [];
        $branch = $releaseNotes['branch'] ?? null;
        $headSha = $releaseNotes['headSha'] ?? null;
        $hasError = $releaseNotes['hasError'] ?? false;
        $errorMessage = $releaseNotes['errorMessage'] ?? null;
        $filters = $releaseNotes['filters'] ?? ['search' => null, 'page' => 1, 'perPage' => 100];
        $pagination = $releaseNotes['pagination'] ?? ['currentPage' => 1, 'perPage' => 100, 'total' => 0, 'totalPages' => 1, 'from' => 0, 'to' => 0, 'hasPreviousPage' => false, 'hasNextPage' => false];
    @endphp

    <div class="space-y-6 text-slate-800" style="font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
        <section class="rounded-[1.75rem] border border-slate-200 bg-linear-to-br from-white to-slate-50 p-8 shadow-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                    <div class="max-w-3xl space-y-4">
                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                            Release Notes
                        </span>

                        <div class="space-y-3">
                            <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                                Historial de cambios del proyecto
                            </h2>

                            <p class="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                                Consulta los commits m&aacute;s recientes del repositorio con una presentaci&oacute;n limpia,
                                pensada para revisar avances, correcciones y mantenimiento del sistema.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-slate-400">Branch</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $branch ?? 'N/A' }}</p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-slate-400">Head</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $headSha ? \Illuminate\Support\Str::limit($headSha, 12, '') : 'N/A' }}</p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-slate-400">Total</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $summary['total'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-600">Features</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $summary['features'] }}</p>
                    </div>

                    <div class="rounded-2xl border border-amber-100 bg-amber-50 p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-600">Fixes</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $summary['fixes'] }}</p>
                    </div>

                    <div class="rounded-2xl border border-sky-100 bg-sky-50 p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-sky-600">Docs</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $summary['docs'] }}</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Chores</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $summary['chores'] }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ request()->url() }}" class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="grid flex-1 gap-4 md:grid-cols-[minmax(0,1fr)_180px]">
                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-700">Buscar en commits</span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] }}"
                            placeholder="Ej. TrackModelChanges, fix, export, factura..."
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-hidden transition focus:border-orange-300 focus:ring-2 focus:ring-orange-100"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-700">Commits por p&aacute;gina</span>
                        <select
                            name="per_page"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-hidden transition focus:border-orange-300 focus:ring-2 focus:ring-orange-100"
                        >
                            @foreach ($this->perPageOptions as $option)
                                <option value="{{ $option }}" @selected($filters['perPage'] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <input type="hidden" name="page" value="1">

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Buscar
                    </button>

                    @if (filled($filters['search']) || $filters['perPage'] !== $this->defaultPerPage)
                        <a
                            href="{{ request()->url() }}"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                        >
                            Limpiar filtJeanCarloMS
                        </a>
                    @endif
                </div>
            </form>

            <div class="mt-4 flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                <p>
                    Mostrando <span class="font-semibold text-slate-700">{{ $pagination['from'] }}</span>
                    a <span class="font-semibold text-slate-700">{{ $pagination['to'] }}</span>
                    de <span class="font-semibold text-slate-700">{{ $pagination['total'] }}</span> commits
                    @if (filled($filters['search']))
                        para <span class="font-semibold text-slate-700">"{{ $filters['search'] }}"</span>
                    @endif
                </p>

                <p>
                    P&aacute;gina <span class="font-semibold text-slate-700">{{ $pagination['currentPage'] }}</span>
                    de <span class="font-semibold text-slate-700">{{ $pagination['totalPages'] }}</span>
                </p>
            </div>
        </section>

        @if ($hasError)
            <section class="rounded-[1.5rem] border border-rose-200 bg-rose-50 p-6 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-100 text-rose-700">
                        <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold text-rose-900">
                            No fue posible leer el historial Git
                        </h3>

                        <p class="text-sm leading-6 text-rose-800">
                            {{ $errorMessage }}
                        </p>

                        <p class="text-sm leading-6 text-rose-700">
                            Verifica que la ruta configurada apunte a un repositorio Git v&aacute;lido y que el usuario del proceso PHP tenga acceso.
                        </p>
                    </div>
                </div>
            </section>
        @elseif (blank($groupedCommits))
            <section class="rounded-[1.5rem] border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
                <div class="mx-auto max-w-xl space-y-3">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                        <x-heroicon-o-document-text class="h-7 w-7" />
                    </div>

                    <h3 class="text-xl font-semibold text-slate-900">
                        No se encontraron commits para mostrar
                    </h3>

                    <p class="text-sm leading-6 text-slate-500">
                        No hay commits para los filtros seleccionados o el repositorio todav&iacute;a no tiene historial disponible.
                    </p>
                </div>
            </section>
        @endif

        @foreach ($groupedCommits as $group)
            <section class="grid gap-4 xl:grid-cols-[240px_minmax(0,1fr)]">
                <div class="xl:sticky xl:top-24 xl:self-start">
                    <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">
                            Fecha
                        </p>
                        <h3 class="mt-3 text-lg font-semibold leading-7 text-slate-900">
                            {{ $group['label'] }}
                        </h3>
                        <p class="mt-2 text-sm text-slate-500">
                            {{ count($group['commits']) }} cambios registrados
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach ($group['commits'] as $commit)
                        <article class="rounded-[1.5rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex flex-col gap-5">
                                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                    <div class="space-y-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $commit['badge']['classes'] }}">
                                                {{ $commit['badge']['label'] }}
                                            </span>

                                            @if (filled($commit['scope']))
                                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-600">
                                                    {{ $commit['scope'] }}
                                                </span>
                                            @endif

                                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500">
                                                {{ $commit['short_sha'] }}
                                            </span>
                                        </div>

                                        <div class="space-y-2">
                                            <h4 class="max-w-4xl text-lg font-semibold leading-8 text-slate-900">
                                                {{ $commit['subject'] }}
                                            </h4>

                                            @if (filled($commit['body']))
                                                <p class="max-w-3xl text-md leading-7 text-slate-900">
                                                    {!! nl2br(e(\Illuminate\Support\Str::limit($commit['body'], config('filament-release-notes.max_body_length', 1500)))) !!}
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    @if (filled($commit['commit_url']))
                                        <a
                                            href="{{ $commit['commit_url'] }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                        >
                                            Ver commit
                                            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                                        </a>
                                    @endif
                                </div>

                                <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex flex-wrap items-center gap-4">
                                        <span class="inline-flex items-center gap-2">
                                            <x-heroicon-o-user-circle class="h-4 w-4" />
                                            {{ $commit['author_name'] }}
                                        </span>

                                        <span class="inline-flex items-center gap-2">
                                            <x-heroicon-o-clock class="h-4 w-4" />
                                            {{ $commit['committed_at_label'] }}
                                        </span>
                                    </div>

                                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                        {{ $commit['committed_at_human'] }}
                                    </span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($pagination['totalPages'] > 1)
            <section class="flex flex-col gap-3 rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <a
                    href="{{ $pagination['hasPreviousPage'] ? request()->fullUrlWithQuery(['page' => $pagination['currentPage'] - 1]) : '#' }}"
                    @class([
                        'inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition',
                        'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' => $pagination['hasPreviousPage'],
                        'cursor-not-allowed border border-slate-100 bg-slate-50 text-slate-300' => ! $pagination['hasPreviousPage'],
                    ])
                >
                    Anterior
                </a>

                <div class="flex flex-wrap items-center justify-center gap-2">
                    @foreach (range(max(1, $pagination['currentPage'] - 2), min($pagination['totalPages'], $pagination['currentPage'] + 2)) as $pageNumber)
                        <a
                            href="{{ request()->fullUrlWithQuery(['page' => $pageNumber]) }}"
                            @class([
                                'inline-flex min-w-10 items-center justify-center rounded-xl px-3 py-2 text-sm font-semibold transition',
                                'bg-slate-900 text-white' => $pageNumber === $pagination['currentPage'],
                                'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' => $pageNumber !== $pagination['currentPage'],
                            ])
                        >
                            {{ $pageNumber }}
                        </a>
                    @endforeach
                </div>

                <a
                    href="{{ $pagination['hasNextPage'] ? request()->fullUrlWithQuery(['page' => $pagination['currentPage'] + 1]) : '#' }}"
                    @class([
                        'inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition',
                        'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' => $pagination['hasNextPage'],
                        'cursor-not-allowed border border-slate-100 bg-slate-50 text-slate-300' => ! $pagination['hasNextPage'],
                    ])
                >
                    Siguiente
                </a>
            </section>
        @endif
    </div>
</x-filament-panels::page>
