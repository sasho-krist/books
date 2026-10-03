<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\Chitanka;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReaderController extends Controller
{
    public function show(Request $request, Book $book, Chitanka $chitanka): View|RedirectResponse
    {
        abort_unless($book->isChitanka(), 404);

        $parsed = Chitanka::parseExternalId($book->google_id);
        abort_if($parsed === null || ! preg_match('~/(?:book|text)/\d+-([a-z0-9-]+)$~i', (string) $book->source_url, $m), 404);

        [$type, $id] = $parsed;

        try {
            $pages = Chitanka::paginate($chitanka->fullText($type, $id, $m[1]));
        } catch (RequestException $e) {
            report($e);

            return redirect()->route('books.show', $book)
                ->with('error', 'Текстът не може да бъде зареден от Читанка в момента. Опитай отново след малко.');
        }

        abort_if($pages === [], 404);

        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $page = min((int) ($data['page'] ?? 1), count($pages));

        return view('books.read', [
            'book' => $book,
            'page' => $page,
            'total' => count($pages),
            'lines' => preg_split('/\R/u', trim($pages[$page - 1])),
        ]);
    }
}
