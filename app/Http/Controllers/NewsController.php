<?php

namespace App\Http\Controllers;

use App\Models\Post;

class NewsController extends Controller
{
    public function index()
    {
        $posts = Post::published()->orderByDesc('published_at')->paginate(10);

        return view('news.index', compact('posts'));
    }

    public function show(Post $post)
    {
        abort_unless($post->isPublished(), 404);

        return view('news.show', compact('post'));
    }
}
