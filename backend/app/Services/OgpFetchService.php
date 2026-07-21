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

            // Shift_JIS / EUC-JP 等のページ由来の非 UTF-8 バイトがそのまま
            // 混入すると、後段の response()->json() で json_encode が
            // "Malformed UTF-8 characters" 例外を投げ 500 になる。
            // ここで UTF-8 に正規化してから抽出する。
            $html = $this->toUtf8($response->body(), $response->header('Content-Type'));

            return [
                'title'         => $this->clean($this->extractMeta($html, 'og:title')
                                ?? $this->extractTitle($html)),
                'description'   => $this->clean($this->extractMeta($html, 'og:description')
                                ?? $this->extractMetaName($html, 'description')),
                'thumbnail_url' => $this->clean($this->extractMeta($html, 'og:image')),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * ページの文字コードを検出して HTML 全体を UTF-8 に変換する。
     * 検出できない場合は UTF-8 とみなす。
     */
    private function toUtf8(string $html, ?string $contentTypeHeader): string
    {
        $charset = $this->detectCharset($html, $contentTypeHeader);

        if ($charset !== null && strcasecmp($charset, 'UTF-8') !== 0) {
            $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
            if ($converted !== false) {
                $html = $converted;
            }
        }

        return $html;
    }

    private function detectCharset(string $html, ?string $contentTypeHeader): ?string
    {
        // 1. HTTP レスポンスヘッダの charset を優先
        if ($contentTypeHeader && preg_match('/charset=["\']?([\w\-]+)/i', $contentTypeHeader, $m)) {
            return $this->normalizeCharset($m[1]);
        }

        // 2. HTML の <meta charset> / <meta http-equiv="Content-Type" content="...charset=...">
        if (preg_match('/<meta[^>]+charset=["\']?([\w\-]+)/i', $html, $m)) {
            return $this->normalizeCharset($m[1]);
        }

        return null;
    }

    private function normalizeCharset(string $charset): string
    {
        $c = strtolower($charset);

        return match (true) {
            str_contains($c, 'shift') || $c === 'sjis' || $c === 'x-sjis' => 'SJIS-win',
            str_contains($c, 'euc')                                       => 'eucJP-win',
            default                                                       => $charset,
        };
    }

    /**
     * 抽出値を有効な UTF-8 に保証する（変換漏れ・不正バイトの最終防御）。
     */
    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // 不正な UTF-8 バイトを置換して json_encode 失敗を防ぐ
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
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
