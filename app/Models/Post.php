<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cast attributes (column types come from the migrations).
 *
 * @property PostStatus $status
 * @property Carbon|null $published_at
 */
class Post extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'cover_image_path',
        'meta_description',
        'status',
        'published_at',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Publishing without a date means "now"
        static::saving(function (Post $post) {
            if ($post->status === PostStatus::Published && $post->published_at === null) {
                $post->published_at = now();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Published and not scheduled for later.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)->where('published_at', '<=', now());
    }

    /**
     * Body rendered to HTML. Raw HTML in the Markdown is escaped and unsafe
     * links (javascript:, data:) are dropped, so posts cannot inject scripts.
     */
    public function bodyHtml(): string
    {
        return Str::markdown($this->body, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    public function coverImageUrl(): ?string
    {
        return $this->cover_image_path ? Storage::disk(config('navesta.media_disk'))->url($this->cover_image_path) : null;
    }
}
