<?php

namespace App\Http\Controllers;

use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Services\GoogleBooks;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    public function show(Request $request, Book $book, GoogleBooks $googleBooks): View
    {
        $this->backfillAccessInfo($book, $googleBooks);

        return view('books.show', [
            'book' => $book,
            // The user's own copy (with pivot data), or null if not on a shelf.
            'shelfBook' => $request->user()->books()->whereKey($book->id)->first(),
            'statuses' => ReadingStatus::cases(),
        ]);
    }

    /**
     * Books saved before access info was tracked have a null viewability; fetch it once.
     */
    private function backfillAccessInfo(Book $book, GoogleBooks $googleBooks): void
    {
        if ($book->isChitanka() || $book->viewability !== null) {
            return;
        }

        try {
            $volume = $googleBooks->find($book->google_id);
        } catch (RequestException $e) {
            report($e);

            return;
        }

        if ($volume) {
            $book->update([
                'viewability' => $volume['viewability'],
                'embeddable' => $volume['embeddable'],
            ]);
        }
    }
}
