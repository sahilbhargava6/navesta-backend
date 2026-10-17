<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $attributes): Post
    {
        return Post::create([
            'title' => 'How balance transfers work',
            'slug' => 'how-balance-transfers-work',
            'body' => "## Steps\n\nMove a **high-interest** balance.",
            'status' => PostStatus::Published,
            ...$attributes,
        ]);
    }

    public function test_only_published_posts_are_listed(): void
    {
        $this->makePost(['slug' => 'live', 'published_at' => now()->subDay()]);
        $this->makePost(['slug' => 'draft', 'status' => PostStatus::Draft]);
        $this->makePost(['slug' => 'scheduled', 'published_at' => now()->addWeek()]);

        $this->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'live')
            ->assertJsonMissingPath('data.0.bodyHtml')
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/posts/draft')->assertNotFound();
        $this->getJson('/api/v1/posts/scheduled')->assertNotFound();
    }

    public function test_publishing_without_a_date_publishes_now(): void
    {
        $post = $this->makePost([]);

        $this->assertNotNull($post->published_at);
        $this->getJson('/api/v1/posts/how-balance-transfers-work')
            ->assertOk()
            ->assertJsonPath('data.bodyHtml', "<h2>Steps</h2>\n<p>Move a <strong>high-interest</strong> balance.</p>\n");
    }

    public function test_html_and_script_links_in_markdown_are_neutralised(): void
    {
        $this->makePost(['body' => "<script>alert(1)</script>\n\n[click](javascript:alert(1))"]);

        $html = $this->getJson('/api/v1/posts/how-balance-transfers-work')->json('data.bodyHtml');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
