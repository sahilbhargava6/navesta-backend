<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class Controller
{
    /**
     * Success envelope used across the API: { success: true, message?, data?, meta? }.
     */
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200, ?array $meta = null): JsonResponse
    {
        $body = ['success' => true];

        if ($message !== null) {
            $body['message'] = $message;
        }

        if ($data !== null) {
            $body['data'] = $data;
        }

        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Paginated list: `data` stays a plain array (as before) and paging
     * details go in `meta`. Accepts ?page= and ?perPage= (max 100).
     *
     * @param  class-string<JsonResource>  $resource
     */
    protected function paginated(Request $request, Builder|Relation $query, string $resource): JsonResponse
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:'.config('navesta.pagination.max_per_page')],
        ]);

        $page = $query->paginate((int) $request->query('perPage', config('navesta.pagination.default_per_page')));

        return $this->ok($resource::collection($page->items()), meta: [
            'currentPage' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
            'lastPage' => $page->lastPage(),
        ]);
    }
}
