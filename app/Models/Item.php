<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Item extends Model implements HasMedia
{
    use HasSlug, InteractsWithMedia;

    protected $fillable = [
        'user_id', 'title', 'description', 'category_id',
        'condition', 'offer_type', 'credit_type', 'custom_credit_value',
        'is_available', 'is_archived', 'slug',
    ];

    protected function casts(): array
    {
        return [
            'is_available'       => 'boolean',
            'is_archived'        => 'boolean',
            'custom_credit_value' => 'decimal:2',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()->generateSlugsFrom('title')->saveSlugsTo('slug');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function availabilitySchedules()
    {
        return $this->morphMany(AvailabilitySchedule::class, 'resource');
    }

    public function requests()
    {
        return $this->morphMany(ExchangeRequest::class, 'resource');
    }

    public function waitlistEntries()
    {
        return $this->hasMany(WaitlistEntry::class, 'resource_id')
                    ->where('resource_type', 'item');
    }
}
