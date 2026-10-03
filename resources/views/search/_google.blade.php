<section class="space-y-3">
    <h3 class="text-lg font-semibold text-gray-800">Google Books</h3>

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
                        <p class="text-xs text-gray-400 mt-1">
                            {{ $book['published_date'] }}
                            @if ($book['language'])
                                <span class="ms-1 px-1.5 py-0.5 bg-gray-100 rounded">{{ \App\Models\Book::languageLabel($book['language']) }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="p-4 pt-0">
                        @if (isset($shelf[$book['google_id']]))
                            <p class="text-sm text-green-700">
                                ✓ В „{{ \App\Enums\ReadingStatus::from($shelf[$book['google_id']])->label() }}“
                            </p>
                        @else
                            <form method="POST" action="{{ route('my-books.store') }}" class="flex gap-2">
                                @csrf
                                <input type="hidden" name="google_id" value="{{ $book['google_id'] }}">
                                <select name="status" class="flex-1 border-gray-300 rounded-md shadow-sm text-sm">
                                    @foreach ($statuses as $s)
                                        <option value="{{ $s->value }}" @selected($s === \App\Enums\ReadingStatus::Want)>{{ $s->label() }}</option>
                                    @endforeach
                                </select>
                                <x-primary-button>Добави</x-primary-button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
