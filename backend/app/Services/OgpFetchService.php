<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OgpFetchService
{
    public function fetch(string $url): array
    {
        $empty = ['title' => null, 'description' => null, 'thumbnail_url' => null];

        try {
            $response = Http::timeout(5)->get($url);

            if ($response->failed()) {
                return $empty;
            }

            $html = $response->body();

            return [
                'title'         => $this->extractMeta($html, 'og:title')
                                ?? $this->extractTitle($html),
                'description'   => $this->extractMeta($html, 'og:description')
                                ?? $this->extractMetaName($html, 'description'),
                'thumbnail_url' => $this->extractMeta($html, 'og:image'),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    private function decode(string $value): string
    {
        return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function extractMeta(string $html, string $property): ?string
    {
        if (preg_match(
            '/<meta[^>]+property=["\']' . preg_quote($property, '/') . '["\'][^>]+content=["\']([^"\']+)["\']/',
            $html,
            $m
        )) {
            return $this->decode($m[1]);
        }

        if (preg_match(
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']' . preg_quote($property, '/') . '["\']/',
            $html,
            $m
        )) {
            return $this->decode($m[1]);
        }

        return null;
    }

    private function extractMetaName(string $html, string $name): ?string
    {
        if (preg_match(
            '/<meta[^>]+name=["\']' . preg_quote($name, '/') . '["\'][^>]+content=["\']([^"\']+)["\']/',
            $html,
            $m
        )) {
            return $this->decode($m[1]);
        }

        return null;
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            return $this->decode($m[1]);
        }

        return null;
    }
}
