<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with('user:id,name')->latest()->paginate(20);
        return view('admin.posts.index', compact('posts'));
    }

    public function create()
    {
        return view('admin.posts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'  => 'required|string|max:255',
            'body'   => 'required|string',
            'status' => 'required|in:' . implode(',', Post::STATUSES),
        ]);

        $post = Post::create([
            'user_id'      => $request->user()->id,
            'title'        => $data['title'],
            'body'         => $data['body'],
            'status'       => $data['status'],
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);

        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => 'post_create',
            'payload'    => ['post_id' => $post->id, 'title' => $post->title],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.posts.index')
            ->with('success', 'Post ' . ($data['status'] === 'published' ? 'published' : 'saved as ' . $data['status']) . '.');
    }

    public function edit(Post $post)
    {
        return view('admin.posts.edit', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        $data = $request->validate([
            'title'  => 'required|string|max:255',
            'body'   => 'required|string',
            'status' => 'required|in:' . implode(',', Post::STATUSES),
        ]);

        $post->update([
            'title'        => $data['title'],
            'body'         => $data['body'],
            'status'       => $data['status'],
            'published_at' => $data['status'] === 'published' ? ($post->published_at ?? now()) : null,
        ]);

        return redirect()->route('admin.posts.index')->with('success', 'Post updated.');
    }

    public function destroy(Request $request, Post $post)
    {
        $title = $post->title;
        $postId = $post->id;
        $post->delete();

        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => 'post_delete',
            'payload'    => ['post_id' => $postId, 'title' => $title],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Post deleted.');
    }

    public function publish(Request $request, Post $post)
    {
        $post->update([
            'status' => 'published',
            'published_at' => $post->published_at ?? now(),
        ]);

        AdminAuditLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'post_publish',
            'payload' => ['post_id' => $post->id, 'title' => $post->title],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.posts.index')
            ->with('success', 'Post published. Use "Email version" to copy it into your mail client.');
    }

    public function email(Post $post)
    {
        $html = Str::markdown($post->body, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return view('admin.posts.email', compact('post', 'html'));
    }
}
