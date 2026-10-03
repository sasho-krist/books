<?php

namespace App\Http\Controllers;

use App\Services\GoogleBooks;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, GoogleBooks $googleBooks): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $query = trim($data['q'] ?? '');
        $results = [];
        $error = null;

        if ($query !== '') {
            try {
                $results = $googleBooks->search($query);
            } catch (RequestException $e) {
                report($e);
                $error = 'Търсенето в Google Books не е достъпно в момента. Опитай отново след малко.';
            }
        }

        return view('search', [
            'query' => $query,
            'results' => $results,
            'error' => $error,
        ]);
    }
}
