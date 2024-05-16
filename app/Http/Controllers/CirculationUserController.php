<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Book;


class CirculationUserController extends Controller
{
    public function getUser(Request $request, int $id) {
        return User::with('program')->findOrFail($id);
    }
    public function getBook(Request $request, int $id) {
        return Book::with('Book')->findOrFail($id);
    }
}
