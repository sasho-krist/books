<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Моите книги
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-flash />

            <div class="flex gap-2 border-b border-gray-200">
                @foreach ($statuses as $tab)
                    <a href="{{ route('my-books.index', ['status' => $tab->value]) }}"
                        class="px-4 py-2 -mb-px border-b-2 text-sm font-medium {{ $tab === $status ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        {{ $tab->label() }}
                        <span class="ms-1 text-xs text-gray-400">{{ $counts[$tab->value] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>

            @forelse ($books as $book)
                <div class="bg-white shadow-sm sm:rounded-lg p-4 flex gap-4">
                    <a href="{{ route('books.show', $book) }}" class="w-24 shrink-0">
                        @if ($book->thumbnail)
                            <img src="{{ $book->thumbnail }}" alt="{{ $book->title }}" class="w-full rounded" loading="lazy">
                        @else
                            <div class="h-32 bg-gray-100 rounded flex items-center justify-center text-xs text-gray-400">Без корица</div>
                        @endif
                    </a>

                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900">
                            <a href="{{ route('books.show', $book) }}" class="hover:underline">{{ $book->title }}</a>
                        </h3>
                        @if ($book->authors)
                            <p class="text-sm text-gray-600">{{ implode(', ', $book->authors) }}</p>
                        @endif

                        @include('partials.shelf-form', ['book' => $book, 'statuses' => $statuses])
                    </div>
                </div>
            @empty
                <div class="p-6 bg-white shadow-sm sm:rounded-lg text-gray-600">
                    Все още нямаш книги в „{{ $status->label() }}“.
                    <a href="{{ route('search') }}" class="text-indigo-600 hover:underline">Потърси книга</a>.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
