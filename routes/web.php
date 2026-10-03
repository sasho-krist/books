<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReaderController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserBookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
    Route::get('/books/{book}/read', [ReaderController::class, 'show'])->name('books.read');

    Route::get('/my-books', [UserBookController::class, 'index'])->name('my-books.index');
    Route::post('/my-books', [UserBookController::class, 'store'])->name('my-books.store');
    Route::patch('/my-books/{book}', [UserBookController::class, 'update'])->name('my-books.update');
    Route::delete('/my-books/{book}', [UserBookController::class, 'destroy'])->name('my-books.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
