<?php

namespace Tests\Unit\Services;

use App\Services\OgpFetchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OgpFetchServiceTest extends TestCase
{
    private OgpFetchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OgpFetchService();
    }

    public function test_ogタグからタイトル概要画像を取得できる(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response(
                '<html><head>
                    <meta property="og:title" content="テスト記事タイトル">
                    <meta property="og:description" content="テスト概要文">
                    <meta property="og:image" content="https://example.com/image.png">
                </head></html>',
                200
            ),
        ]);

        $result = $this->service->fetch('https://example.com/article');

        $this->assertEquals('テスト記事タイトル', $result['title']);
        $this->assertEquals('テスト概要文', $result['description']);
        $this->assertEquals('https://example.com/image.png', $result['thumbnail_url']);
    }

    public function test_ogタグがない場合はtitleタグにフォールバックする(): void
    {
        Http::fake([
            '*' => Http::response(
                '<html><head><title>フォールバックタイトル</title></head></html>',
                200
            ),
        ]);

        $result = $this->service->fetch('https://example.com/');

        $this->assertEquals('フォールバックタイトル', $result['title']);
        $this->assertNull($result['description']);
        $this->assertNull($result['thumbnail_url']);
    }

    public function test_ogdescriptionがない場合はmetaのdescriptionにフォールバックする(): void
    {
        Http::fake([
            '*' => Http::response(
                '<html><head>
                    <meta property="og:title" content="タイトル">
                    <meta name="description" content="メタ概要">
                </head></html>',
                200
            ),
        ]);

        $result = $this->service->fetch('https://example.com/');

        $this->assertEquals('メタ概要', $result['description']);
    }

    public function test_タイムアウト時はnullを返す(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        });

        $result = $this->service->fetch('https://unreachable.example.com/');

        $this->assertNull($result['title']);
        $this->assertNull($result['description']);
        $this->assertNull($result['thumbnail_url']);
    }

    public function test_HTTP500エラー時はnullを返す(): void
    {
        Http::fake(['*' => Http::response('Server Error', 500)]);

        $result = $this->service->fetch('https://example.com/');

        $this->assertNull($result['title']);
        $this->assertNull($result['description']);
        $this->assertNull($result['thumbnail_url']);
    }
}
