<?php

namespace App\Http\Controllers;

use App\Enums\ReadingStatus;
use App\Services\Chitanka;
use App\Services\GoogleBooks;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, GoogleBooks $googleBooks, Chitanka $chitanka): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $query = trim($data['q'] ?? '');
        $results = [];
        $chitankaResults = [];
        $error = null;
        $chitankaError = null;

        if ($query !== '') {
            // The two sources are independent: one failing must not hide the other.
            try {
                $results = $googleBooks->search($query);
            } catch (RequestException $e) {
                report($e);
                $error = 'Търсенето в Google Books не е достъпно в момента. Опитай отново след малко.';
            }

            try {
                $chitankaResults = $chitanka->search($query);
            } catch (RequestException $e) {
                report($e);
                $chitankaError = 'Търсенето в Читанка не е достъпно в момента.';
            }
        }

        return view('search', [
            'query' => $query,
            'results' => $results,
            'error' => $error,
            'chitankaResults' => $chitankaResults,
            'chitankaError' => $chitankaError,
            'shelf' => $this->shelfStatuses($request, [...$results, ...$chitankaResults]),
            'statuses' => ReadingStatus::cases(),
        ]);
    }

    /**
     * Map google_id => status for results already on the user's shelves.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<string, string>
     */
    private function shelfStatuses(Request $request, array $results): array
    {
        if ($results === []) {
            return [];
        }

        return $request->user()->books()
            ->whereIn('books.google_id', array_column($results, 'google_id'))
            ->get()
            ->pluck('pivot.status', 'google_id')
            ->all();
    }
}
