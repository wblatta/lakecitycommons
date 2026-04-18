<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with('user:id,name')
            ->latest()
            ->paginate(20);

        return view('admin.posts.index', compact('posts'));
    }

    public function create()
    {
        return view('admin.posts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'body'      => 'required|string',
            'published' => 'boolean',
        ]);

        Post::create([
            'user_id'      => $request->user()->id,
            'title'        => $data['title'],
            'body'         => $data['body'],
            'published_at' => $request->boolean('published') ? now() : null,
        ]);

        return redirect()->route('admin.posts.index')
            ->with('success', 'Post ' . ($request->boolean('published') ? 'published' : 'saved as draft') . '.');
    }

    public function edit(Post $post)
    {
        return view('admin.posts.edit', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'body'      => 'required|string',
            'published' => 'boolean',
        ]);

        $post->update([
            'title'        => $data['title'],
            'body'         => $data['body'],
            'published_at' => $request->boolean('published')
                ? ($post->published_at ?? now())
                : null,
        ]);

        return redirect()->route('admin.posts.index')->with('success', 'Post updated.');
    }

    public function destroy(Post $post)
    {
        $post->delete();
        return back()->with('success', 'Post deleted.');
    }
}
