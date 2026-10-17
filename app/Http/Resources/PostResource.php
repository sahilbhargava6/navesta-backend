<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Blog post. The list view omits the body; the single-post view adds it as
 * Markdown and as sanitised HTML.
 *
 * @mixin Post
 */
class PostResource extends JsonResource
{
    private bool $withBody = false;

    public function withBody(): static
    {
        $this->withBody = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'coverImageUrl' => $this->coverImageUrl(),
            'authorName' => $this->author?->name,
            'publishedAt' => $this->published_at,
            $this->mergeWhen($this->withBody, fn () => [
                'metaDescription' => $this->meta_description ?? $this->excerpt,
                'bodyMarkdown' => $this->body,
                'bodyHtml' => $this->bodyHtml(),
                'updatedAt' => $this->updated_at,
            ]),
        ];
    }
}
