<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Post;

class HomeController extends Controller
{
    public function __invoke()
    {
        return view('home', [
            'posts' => Post::published()->orderByDesc('published_at')->limit(3)->get(),
            'events' => Event::approved()->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
        ]);
    }
}
