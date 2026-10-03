<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Търсене на книги
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('search') }}" class="flex gap-3">
                <x-text-input name="q" type="search" class="flex-1" :value="$query"
                    placeholder="Заглавие, автор или ISBN" maxlength="200" autofocus />
                <x-primary-button>Търси</x-primary-button>
            </form>
            <x-input-error :messages="$errors->get('q')" />

            @if ($error)
                <div class="p-4 bg-red-50 text-red-700 rounded-lg">{{ $error }}</div>
            @elseif ($query !== '' && count($results) === 0)
                <div class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">
                    Няма намерени книги за „{{ $query }}“.
                </div>
            @endif

            @if (count($results) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach ($results as $book)
                        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden flex flex-col">
                            <div class="h-56 bg-gray-100 flex items-center justify-center">
                                @if ($book['thumbnail'])
                                    <img src="{{ $book['thumbnail'] }}" alt="{{ $book['title'] }}"
                                        class="h-full object-contain" loading="lazy">
                                @else
                                    <span class="text-gray-400 text-sm">Без корица</span>
                                @endif
                            </div>
                            <div class="p-4 flex-1">
                                <h3 class="font-semibold text-gray-900 leading-snug">{{ $book['title'] }}</h3>
                                @if ($book['authors'])
                                    <p class="text-sm text-gray-600 mt-1">{{ implode(', ', $book['authors']) }}</p>
                                @endif
                                @if ($book['published_date'])
                                    <p class="text-xs text-gray-400 mt-1">{{ $book['published_date'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
