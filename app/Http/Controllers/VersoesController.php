<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class VersoesController extends Controller
{
    public function index(): View
    {
        return view('versoes.index');
    }
}
