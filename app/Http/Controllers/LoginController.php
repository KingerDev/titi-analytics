<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function show()
    {
        if (session('analytics_authed')) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (
            $request->username === config('analytics.username') &&
            $request->password === config('analytics.password')
        ) {
            session(['analytics_authed' => true]);
            return redirect()->route('dashboard');
        }

        return back()->withErrors(['username' => 'Nesprávne prihlasovacie údaje.']);
    }

    public function logout()
    {
        session()->forget('analytics_authed');
        return redirect()->route('login');
    }
}
