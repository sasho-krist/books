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
                <select name="lang" aria-label="Език" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="">Всички езици</option>
                    @foreach (['bg' => 'Български', 'en' => 'Английски', 'ru' => 'Руски'] as $code => $label)
                        <option value="{{ $code }}" @selected($lang === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-primary-button>Търси</x-primary-button>
            </form>
            <x-input-error :messages="$errors->get('q')" />
            <x-flash />

            @if ($query !== '')
                {{-- With Bulgarian selected, the free Bulgarian library comes first. --}}
                @if ($lang === 'bg')
                    @include('search._chitanka')
                    @include('search._google')
                @else
                    @include('search._google')
                    @include('search._chitanka')
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
