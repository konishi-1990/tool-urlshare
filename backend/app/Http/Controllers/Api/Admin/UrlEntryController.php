<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UrlEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UrlEntryController extends Controller
{
    public function index(): JsonResponse
    {
        $entries = UrlEntry::with('user')->where('status', '!=', 'deleted')->latest()->get();

        return response()->json(['data' => $entries]);
    }

    public function destroy(UrlEntry $urlEntry): JsonResponse
    {
        $urlEntry->update(['status' => 'deleted']);

        return response()->json(['message' => 'Deleted.']);
    }

    public function exportBookmarks(): Response
    {
        $entries = UrlEntry::where('status', 'bookmarked')->latest()->get();

        $html = "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n";
        $html .= "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">\n";
        $html .= "<TITLE>Bookmarks</TITLE>\n<H1>Bookmarks</H1>\n<DL><p>\n";

        foreach ($entries as $entry) {
            $title = htmlspecialchars($entry->title ?? $entry->url, ENT_QUOTES);
            $url   = htmlspecialchars($entry->url, ENT_QUOTES);
            $html .= "    <DT><A HREF=\"{$url}\">{$title}</A>\n";
        }

        $html .= "</DL><p>";

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
