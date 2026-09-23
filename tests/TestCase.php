<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // 画面の JavaScript はビルドせずに確かめる
        $this->withoutVite();
    }

    /**
     * Vue の画面ファイルの中身。
     * 画面の中のリンク先は Vue 側に書いているため、遷移先の確認はファイルの中身で行う。
     */
    protected function pageSource(string $component): string
    {
        $path = resource_path('js/Pages/'.$component.'.vue');
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    /** 画面に、指定したルートへのリンクがあること */
    protected function assertPageLinksTo(string $component, string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        $this->assertStringContainsString(
            'href="'.$path.'"',
            $this->pageSource($component),
            $component.' に '.$path.' へのリンクがありません'
        );
    }
}
