<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
            $table->string('status')->default('draft')->after('body'); // draft|review|published
            $table->timestamp('newsletter_sent_at')->nullable()->after('published_at');
        });

        DB::table('posts')->whereNotNull('published_at')->update(['status' => 'published']);

        foreach (DB::table('posts')->whereNull('slug')->get() as $post) {
            $base = Str::slug($post->title) ?: 'post';
            $slug = $base;
            $i = 2;
            while (DB::table('posts')->where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            DB::table('posts')->where('id', $post->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['slug', 'status', 'newsletter_sent_at']);
        });
    }
};
