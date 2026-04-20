<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserProfileController extends Controller
{
    public function show(User $user)
    {
        $user->load([
            'skills' => fn($q) => $q->with('category')->where('is_available', true),
            'items'  => fn($q) => $q->with('category')->where('is_available', true),
        ]);

        $viewer = auth()->user();
        $canSeeCrossStreets = $viewer->id == $user->id || $viewer->isAdmin();
        $canMessage = auth()->id() !== $user->id;

        return view('users.show', compact('user', 'canMessage', 'canSeeCrossStreets'));
    }
}
