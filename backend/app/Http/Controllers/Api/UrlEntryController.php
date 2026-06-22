<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UrlEntry;
use App\Services\OgpFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UrlEntryController extends Controller
{
    public function __construct(private OgpFetchService $ogp) {}

    public function index(Request $request): JsonResponse
    {
        $entries = UrlEntry::forUser($request->user())
            ->byStatus($request->query('status'))
            ->where('status', '!=', 'deleted')
            ->latest()
            ->get();

        return response()->json(['data' => $entries]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url'    => ['required', 'url'],
            'status' => ['nullable', 'in:temporary,bookmarked'],
        ]);

        $existing = UrlEntry::forUser($request->user())
            ->where('url', $validated['url'])
            ->whereIn('status', ['temporary', 'bookmarked'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'URL already exists.'], 409);
        }

        $meta  = $this->ogp->fetch($validated['url']);

        $entry = $request->user()->urlEntries()->create([
            'url'           => $validated['url'],
            'status'        => $validated['status'] ?? 'temporary',
            'title'         => $meta['title'],
            'description'   => $meta['description'],
            'thumbnail_url' => $meta['thumbnail_url'],
        ]);

        return response()->json($entry, 201);
    }

    public function update(Request $request, UrlEntry $urlEntry): JsonResponse
    {
        if ($urlEntry->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:temporary,bookmarked,deleted'],
        ]);

        if (!$urlEntry->isValidTransition($validated['status'])) {
            return response()->json(['message' => 'Invalid status transition.'], 422);
        }

        $urlEntry->update($validated);

        return response()->json($urlEntry);
    }

    public function destroy(Request $request, UrlEntry $urlEntry): JsonResponse
    {
        if ($urlEntry->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $urlEntry->update(['status' => 'deleted']);

        return response()->json(['message' => 'Deleted.']);
    }
}
