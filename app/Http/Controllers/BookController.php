<?php

namespace App\Http\Controllers;

use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Services\Chitanka;
use App\Services\GoogleBooks;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    public function show(Request $request, Book $book, GoogleBooks $googleBooks, Chitanka $chitanka): View
    {
        $this->backfillAccessInfo($book, $googleBooks);
        $this->backfillChitankaCover($book, $chitanka);

        $match = $book->isChitanka()
            ? ['items' => [], 'query' => null, 'exact' => true]
            : $this->chitankaMatch($book, $chitanka);

        return view('books.show', [
            'book' => $book,
            // The user's own copy (with pivot data), or null if not on a shelf.
            'shelfBook' => $request->user()->books()->whereKey($book->id)->first(),
            'statuses' => ReadingStatus::cases(),
            'chitankaMatches' => $match['items'],
            'chitankaExact' => $match['exact'],
            // Send the "search in Chitanka" button to a query that actually finds something.
            'chitankaSearchUrl' => $match['query'] ? Chitanka::searchUrl($match['query']) : $book->chitankaSearchUrl(),
        ]);
    }

    /**
     * Free Bulgarian editions of the same title (or related books), so a Google Books record
     * can be read in the site.
     *
     * @return array{items: array<int, array<string, mixed>>, query: ?string, exact: bool}
     */
    private function chitankaMatch(Book $book, Chitanka $chitanka): array
    {
        try {
            return $chitanka->matchTitle($book->title);
        } catch (RequestException $e) {
            report($e);

            return ['items' => [], 'query' => null, 'exact' => true];
        }
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

    /**
     * Chitanka books saved before covers were tracked have no thumbnail; fetch it once.
     */
    private function backfillChitankaCover(Book $book, Chitanka $chitanka): void
    {
        if (! $book->isChitanka() || $book->thumbnail !== null) {
            return;
        }

        $parsed = Chitanka::parseExternalId($book->google_id);

        if ($parsed === null) {
            return;
        }

        try {
            $item = $chitanka->find(...$parsed);
        } catch (RequestException $e) {
            report($e);

            return;
        }

        if ($item && $item['thumbnail']) {
            $book->update(['thumbnail' => $item['thumbnail']]);
        }
    }
}
