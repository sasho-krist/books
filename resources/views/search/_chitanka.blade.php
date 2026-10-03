@if ($query !== '')
    <section class="space-y-3">
        <h3 class="text-lg font-semibold text-gray-800">
            Читанка <span class="text-sm font-normal text-gray-500">— безплатни книги на български</span>
        </h3>

        @if ($chitankaError)
            <div class="p-4 bg-red-50 text-red-700 rounded-lg">{{ $chitankaError }}</div>
        @elseif (mb_strlen($query) < \App\Services\Chitanka::MIN_QUERY_LENGTH)
            <div class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">
                Читанка търси при поне {{ \App\Services\Chitanka::MIN_QUERY_LENGTH }} символа.
            </div>
        @elseif (count($chitankaResults) === 0)
            <div class="p-4 bg-white shadow-sm sm:rounded-lg text-gray-600">
                Няма резултати в Читанка за „{{ $query }}“.
            </div>
        @else
            <ul class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
                @foreach ($chitankaResults as $item)
                    <li class="p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer"
                                class="font-semibold text-gray-900 hover:underline">{{ $item['title'] }}</a>
                            <p class="text-sm text-gray-600">
                                {{ implode(', ', $item['authors']) }}
                                @if ($item['year']) · {{ $item['year'] }} @endif
                                <span class="ms-1 text-xs text-gray-400">{{ $item['type'] === 'book' ? 'книга' : 'текст' }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            @if (isset($shelf[$item['google_id']]))
                                <span class="text-green-700">✓ В „{{ \App\Enums\ReadingStatus::from($shelf[$item['google_id']])->label() }}“</span>
                            @else
                                <form method="POST" action="{{ route('my-books.store') }}" class="flex gap-1">
                                    @csrf
                                    <input type="hidden" name="google_id" value="{{ $item['google_id'] }}">
                                    <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm py-1">
                                        @foreach ($statuses as $s)
                                            <option value="{{ $s->value }}" @selected($s === \App\Enums\ReadingStatus::Want)>{{ $s->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button class="px-3 py-1 bg-gray-800 text-white rounded-md hover:bg-gray-700">Добави</button>
                                </form>
                            @endif
                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer"
                                class="px-3 py-1 bg-indigo-600 text-white rounded-md hover:bg-indigo-500">Чети онлайн ↗</a>
                            @foreach (array_intersect_key($item['downloads'], array_flip(['epub', 'fb2.zip', 'txt.zip'])) as $format => $link)
                                <a href="{{ $link }}" target="_blank" rel="noopener noreferrer"
                                    class="px-3 py-1 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">{{ strtoupper(str_replace('.zip', '', $format)) }}</a>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
