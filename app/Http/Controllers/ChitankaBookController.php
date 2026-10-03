<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\Chitanka;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class ChitankaBookController extends Controller
{
    /**
     * Save a Chitanka book in the catalogue (not on any shelf) and open it in the reader.
     */
    public function read(string $type, int $id, Chitanka $chitanka): RedirectResponse
    {
        abort_unless(in_array($type, ['book', 'text'], true), 404);

        try {
            $item = $chitanka->find($type, $id);
        } catch (RequestException $e) {
            report($e);

            return back()->with('error', 'Читанка не отговаря в момента. Опитай отново след малко.');
        }

        abort_if($item === null, 404);

        $book = Book::firstOrCreate(
            ['google_id' => $item['google_id']],
            Arr::only($item, (new Book)->getFillable()),
        );

        return redirect()->route('books.read', $book);
    }
}
