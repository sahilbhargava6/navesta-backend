<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public blog: published posts only, newest first.
 */
class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($request, Post::published()->with('author')->latest('published_at'), PostResource::class);
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::published()->with('author')->where('slug', $slug)->first() ?? abort(404, 'Post not found');

        return $this->ok((new PostResource($post))->withBody());
    }
}
