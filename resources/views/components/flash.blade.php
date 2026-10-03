@if (session('status'))
    <div class="p-4 bg-green-50 text-green-700 rounded-lg">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="p-4 bg-red-50 text-red-700 rounded-lg">{{ session('error') }}</div>
@endif
