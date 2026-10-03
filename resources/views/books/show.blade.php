<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $book->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-col sm:flex-row gap-6">
                <div class="w-40 shrink-0">
                    @if ($book->thumbnail)
                        <img src="{{ $book->thumbnail }}" alt="{{ $book->title }}" class="w-full rounded shadow">
                    @else
                        <div class="h-56 bg-gray-100 rounded flex items-center justify-center text-sm text-gray-400">Без корица</div>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $book->title }}</h1>
                    @if ($book->authors)
                        <p class="text-gray-600 mt-1">{{ implode(', ', $book->authors) }}</p>
                    @endif

                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        @if ($book->published_date)
                            <dt class="text-gray-500">Издадена</dt>
                            <dd class="text-gray-900">{{ $book->published_date }}</dd>
                        @endif
                        @if ($book->page_count)
                            <dt class="text-gray-500">Страници</dt>
                            <dd class="text-gray-900">{{ $book->page_count }}</dd>
                        @endif
                        @if ($book->isbn)
                            <dt class="text-gray-500">ISBN</dt>
                            <dd class="text-gray-900">{{ $book->isbn }}</dd>
                        @endif
                    </dl>

                    @if ($book->isChitanka())
                        <a href="{{ route('books.read', $book) }}"
                            class="inline-block mt-4 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-500">
                            Чети тук
                        </a>
                        <a href="{{ $book->source_url }}" target="_blank" rel="noopener noreferrer"
                            class="inline-block mt-4 ms-2 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                            Читанка ↗
                        </a>
                        @foreach (array_intersect_key($book->downloads ?? [], array_flip(['epub', 'fb2.zip', 'txt.zip'])) as $format => $link)
                            <a href="{{ $link }}" target="_blank" rel="noopener noreferrer"
                                class="inline-block mt-4 ms-2 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                                {{ strtoupper(str_replace('.zip', '', $format)) }}
                            </a>
                        @endforeach
                    @else
                        <a href="{{ $book->googleBooksUrl() }}" target="_blank" rel="noopener noreferrer"
                            class="inline-block mt-4 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-500">
                            Отвори в Google Books ↗
                        </a>
                        <a href="{{ $book->chitankaSearchUrl() }}" target="_blank" rel="noopener noreferrer"
                            class="inline-block mt-4 ms-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                            Търси в Читанка (на български) ↗
                        </a>
                    @endif

                    @if ($book->description)
                        {{-- Google descriptions may contain HTML; show as plain text. --}}
                        <p class="mt-4 text-gray-700 whitespace-pre-line">{{ strip_tags($book->description) }}</p>
                    @endif
                </div>
            </div>

            @if ($book->hasPreview())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-1">Прочети</h3>
                    <p class="text-sm text-gray-500 mb-3">
                        {{ $book->viewability === 'ALL_PAGES' ? 'Пълен текст.' : 'Откъс, предоставен от издателя.' }}
                    </p>
                    <iframe src="https://books.google.com/books?id={{ urlencode($book->google_id) }}&amp;lpg=PP1&amp;pg=PP1&amp;output=embed"
                        title="Преглед на „{{ $book->title }}“" class="w-full h-[700px] rounded border border-gray-200"
                        loading="lazy" allowfullscreen></iframe>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if ($shelfBook)
                    <h3 class="font-semibold text-gray-900">На твоя рафт</h3>
                    @include('partials.shelf-form', ['book' => $shelfBook, 'statuses' => $statuses])
                @else
                    <h3 class="font-semibold text-gray-900">Добави към рафтовете си</h3>
                    <form method="POST" action="{{ route('my-books.store') }}" class="mt-3 flex gap-2 max-w-sm">
                        @csrf
                        <input type="hidden" name="google_id" value="{{ $book->google_id }}">
                        <select name="status" class="flex-1 border-gray-300 rounded-md shadow-sm text-sm">
                            @foreach ($statuses as $s)
                                <option value="{{ $s->value }}" @selected($s === \App\Enums\ReadingStatus::Want)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                        <x-primary-button>Добави</x-primary-button>
                    </form>
                @endif
            </div>

            <a href="{{ url()->previous() === url()->current() ? route('my-books.index') : url()->previous() }}"
                class="inline-block text-sm text-indigo-600 hover:underline">← Назад</a>
        </div>
    </div>
</x-app-layout>
