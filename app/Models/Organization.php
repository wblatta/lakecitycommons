<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Organization extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const CATEGORIES = ['community', 'services', 'business', 'government'];

    protected $fillable = [
        'name', 'slug', 'category', 'description', 'website', 'email',
        'phone', 'address', 'is_sponsor', 'sponsor_tier', 'active',
    ];

    protected function casts(): array
    {
        return ['is_sponsor' => 'boolean', 'active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $org) {
            if (empty($org->slug)) {
                $base = Str::slug($org->name) ?: 'org';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }
                $org->slug = $slug;
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
