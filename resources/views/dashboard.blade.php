<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Препоръки за теб</h3>
                        <p class="text-sm text-gray-500">
                            Генерират се с Claude според прочетените от теб книги и оценките ти.
                        </p>
                    </div>

                    @if ($pending)
                        <span class="text-sm text-indigo-600">Генерирам препоръки…</span>
                    @elseif ($readCount > 0)
                        <form method="POST" action="{{ route('recommendations.store') }}">
                            @csrf
                            <x-primary-button>
                                {{ $recommendations->isEmpty() ? 'Генерирай препоръки' : 'Нови препоръки' }}
                            </x-primary-button>
                        </form>
                    @endif
                </div>

                @if ($error)
                    <div class="mt-4 p-4 bg-red-50 text-red-700 rounded-lg">{{ $error }}</div>
                @endif

                @if ($readCount === 0)
                    <p class="mt-4 text-gray-600">
                        Маркирай поне една книга като „Прочетени“ и ще получиш препоръки.
                        <a href="{{ route('search') }}" class="text-indigo-600 hover:underline">Потърси книга</a>.
                    </p>
                @elseif ($recommendations->isEmpty() && ! $pending && ! $error)
                    <p class="mt-4 text-gray-600">Все още нямаш препоръки. Натисни бутона, за да ги генерираш.</p>
                @endif

                @if ($recommendations->isNotEmpty())
                    <ul class="mt-4 divide-y divide-gray-100">
                        @foreach ($recommendations as $recommendation)
                            <li class="py-4 flex flex-col sm:flex-row sm:items-start gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-900">{{ $recommendation->title }}</p>
                                    @if ($recommendation->author)
                                        <p class="text-sm text-gray-600">{{ $recommendation->author }}</p>
                                    @endif
                                    @if ($recommendation->reason)
                                        <p class="mt-1 text-sm text-gray-700">{{ $recommendation->reason }}</p>
                                    @endif
                                </div>
                                <a href="{{ $recommendation->searchUrl() }}"
                                    class="shrink-0 px-3 py-1 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                                    Търси книгата
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    @if ($pending)
        <script>
            // Reload until the queue worker has finished generating.
            setTimeout(() => window.location.reload(), 4000);
        </script>
    @endif
</x-app-layout>
