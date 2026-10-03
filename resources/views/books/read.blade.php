<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight truncate">{{ $book->title }}</h2>
            <a href="{{ route('books.show', $book) }}" class="text-sm text-indigo-600 hover:underline shrink-0">← Към книгата</a>
        </div>
    </x-slot>

    @php
        $prev = $page > 1 ? route('books.read', [$book, 'page' => $page - 1]) : null;
        $next = $page < $total ? route('books.read', [$book, 'page' => $page + 1]) : null;
    @endphp

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <article class="bg-white shadow-sm sm:rounded-lg px-6 py-8 font-serif text-lg leading-relaxed text-gray-900 space-y-4">
                @foreach ($lines as $line)
                    @continue(trim($line) === '')
                    @if (\App\Services\Chitanka::looksLikeHeading($line))
                        <h3 class="font-sans font-semibold text-xl text-gray-800 pt-4">{{ $line }}</h3>
                    @else
                        <p>{{ $line }}</p>
                    @endif
                @endforeach
            </article>

            <nav class="flex items-center justify-between gap-3" aria-label="Страници">
                @if ($prev)
                    <a id="prev-page" href="{{ $prev }}" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm hover:bg-gray-50">← Назад</a>
                @else
                    <span class="px-4 py-2 text-sm text-gray-300">← Назад</span>
                @endif

                <form method="GET" action="{{ route('books.read', $book) }}" class="flex items-center gap-2 text-sm text-gray-600">
                    <label for="page" class="sr-only">Страница</label>
                    <input id="page" name="page" type="number" min="1" max="{{ $total }}" value="{{ $page }}"
                        class="w-20 border-gray-300 rounded-md shadow-sm text-sm py-1">
                    <span>от {{ $total }}</span>
                    <button class="px-3 py-1 bg-gray-800 text-white rounded-md">Отиди</button>
                </form>

                @if ($next)
                    <a id="next-page" href="{{ $next }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-500">Напред →</a>
                @else
                    <span class="px-4 py-2 text-sm text-gray-300">Напред →</span>
                @endif
            </nav>

            <p class="text-xs text-gray-400 text-center">
                Текстът е от <a href="{{ $book->source_url }}" target="_blank" rel="noopener noreferrer" class="underline">Читанка</a>.
            </p>
        </div>
    </div>

    <script>
        // Arrow keys turn pages (ignored while typing in the page box).
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.altKey || e.ctrlKey || e.metaKey) return;
            const link = document.getElementById(e.key === 'ArrowRight' ? 'next-page' : e.key === 'ArrowLeft' ? 'prev-page' : '');
            if (link) window.location = link.href;
        });
    </script>
</x-app-layout>
