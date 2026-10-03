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
                    <div class="w-24 shrink-0">
                        @if ($book->thumbnail)
                            <img src="{{ $book->thumbnail }}" alt="{{ $book->title }}" class="w-full rounded" loading="lazy">
                        @else
                            <div class="h-32 bg-gray-100 rounded flex items-center justify-center text-xs text-gray-400">Без корица</div>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900">{{ $book->title }}</h3>
                        @if ($book->authors)
                            <p class="text-sm text-gray-600">{{ implode(', ', $book->authors) }}</p>
                        @endif

                        <form method="POST" action="{{ route('my-books.update', $book) }}" class="mt-3 grid sm:grid-cols-3 gap-3">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Статус</label>
                                <select name="status" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    @foreach ($statuses as $s)
                                        <option value="{{ $s->value }}" @selected($book->pivot->status === $s->value)>{{ $s->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Рейтинг</label>
                                <select name="rating" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="">Без рейтинг</option>
                                    @for ($i = 1; $i <= 5; $i++)
                                        <option value="{{ $i }}" @selected($book->pivot->rating === $i)>{{ str_repeat('★', $i) }}</option>
                                    @endfor
                                </select>
                            </div>

                            <div class="sm:col-span-3">
                                <label class="block text-xs text-gray-500 mb-1">Бележки</label>
                                <textarea name="notes" rows="2" maxlength="2000"
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm">{{ $book->pivot->notes }}</textarea>
                            </div>

                            <div class="sm:col-span-3 flex items-center gap-3">
                                <x-primary-button>Запази</x-primary-button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('my-books.destroy', $book) }}" class="mt-2"
                            onsubmit="return confirm('Да премахна ли книгата от рафтовете?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-sm text-red-600 hover:underline">Премахни</button>
                        </form>
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
