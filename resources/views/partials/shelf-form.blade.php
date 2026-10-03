{{-- Expects $book (with pivot) and $statuses. --}}
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
