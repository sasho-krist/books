<?php

namespace App\Http\Controllers;

use App\Enums\ReadingStatus;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    public function show(Request $request, Book $book): View
    {
        return view('books.show', [
            'book' => $book,
            // The user's own copy (with pivot data), or null if not on a shelf.
            'shelfBook' => $request->user()->books()->whereKey($book->id)->first(),
            'statuses' => ReadingStatus::cases(),
        ]);
    }
}
