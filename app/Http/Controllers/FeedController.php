<?php

namespace App\Http\Controllers;

use App\Models\Post;

class FeedController extends Controller
{
    public function rss()
    {
        $posts = Post::published()->orderByDesc('published_at')->limit(20)->get();

        return response()
            ->view('feed.rss', compact('posts'))
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    public function sitemap()
    {
        $urls = collect(['/', '/news', '/events', '/directory', '/submit'])
            ->map(fn ($path) => ['loc' => url($path), 'lastmod' => null]);

        $posts = Post::published()->orderByDesc('published_at')->get()
            ->map(fn ($post) => [
                'loc' => route('news.show', $post),
                'lastmod' => $post->updated_at->toAtomString(),
            ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls->concat($posts) as $url) {
            $xml .= '  <url><loc>' . e($url['loc']) . '</loc>'
                . ($url['lastmod'] ? '<lastmod>' . $url['lastmod'] . '</lastmod>' : '')
                . "</url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
