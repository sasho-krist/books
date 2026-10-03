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

                    @if ($book->description)
                        {{-- Google descriptions may contain HTML; show as plain text. --}}
                        <p class="mt-4 text-gray-700 whitespace-pre-line">{{ strip_tags($book->description) }}</p>
                    @endif
                </div>
            </div>

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
