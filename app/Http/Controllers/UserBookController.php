<?php

namespace App\Http\Controllers;

use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Services\Chitanka;
use App\Services\GoogleBooks;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserBookController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(ReadingStatus::class)],
        ]);

        $status = isset($data['status']) ? ReadingStatus::from($data['status']) : ReadingStatus::Reading;

        $user = $request->user();

        $counts = $user->books()
            ->selectRaw('book_user.status as shelf_status, count(*) as total')
            ->groupBy('book_user.status')
            ->pluck('total', 'shelf_status');

        $books = $user->books()
            ->wherePivot('status', $status->value)
            ->orderByPivot('updated_at', 'desc')
            ->get();

        return view('my-books', [
            'status' => $status,
            'statuses' => ReadingStatus::cases(),
            'counts' => $counts,
            'books' => $books,
        ]);
    }

    public function store(Request $request, GoogleBooks $googleBooks, Chitanka $chitanka): RedirectResponse
    {
        $data = $request->validate([
            'google_id' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'status' => ['required', Rule::enum(ReadingStatus::class)],
        ]);

        $book = Book::where('google_id', $data['google_id'])->first();

        if (! $book) {
            $parsed = Chitanka::parseExternalId($data['google_id']);

            try {
                $volume = $parsed
                    ? $chitanka->find(...$parsed)
                    : $googleBooks->find($data['google_id']);
            } catch (RequestException $e) {
                report($e);

                return back()->with('error', 'Източникът на книгата не е достъпен в момента. Опитай отново след малко.');
            }

            if (! $volume) {
                return back()->with('error', 'Книгата не беше намерена.');
            }

            // Only persist columns of the books table; normalized results also carry display data.
            $book = Book::firstOrCreate(
                ['google_id' => $volume['google_id']],
                Arr::only($volume, (new Book)->getFillable()),
            );
        }

        $request->user()->books()->syncWithoutDetaching([
            $book->id => ['status' => $data['status']],
        ]);

        return back()->with('status', '„'.$book->title.'“ е добавена в „'.ReadingStatus::from($data['status'])->label().'“.');
    }

    public function update(Request $request, Book $book): RedirectResponse
    {
        abort_unless($request->user()->books()->whereKey($book->id)->exists(), 404);

        $data = $request->validate([
            'status' => ['sometimes', 'required', Rule::enum(ReadingStatus::class)],
            'rating' => ['sometimes', 'nullable', 'integer', 'between:1,5'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $request->user()->books()->updateExistingPivot($book->id, $data);

        return back()->with('status', 'Промените са запазени.');
    }

    public function destroy(Request $request, Book $book): RedirectResponse
    {
        $request->user()->books()->detach($book->id);

        return back()->with('status', 'Книгата е премахната от рафтовете ти.');
    }
}
