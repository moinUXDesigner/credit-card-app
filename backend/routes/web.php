<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API-only app: no web login form exists. This named route only exists so
// Illuminate\Auth\Middleware\Authenticate::redirectTo() has somewhere to
// resolve to for non-JSON requests; our exception render() override in
// bootstrap/app.php returns JSON for all api/* requests before this is hit.
Route::get('/login', fn () => response()->json(['message' => 'Unauthenticated.'], 401))->name('login');
