<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Storefront\Page\Search\SearchPageLoadedHook;
use Shopware\Storefront\Page\Search\SearchWidgetLoadedHook;
use Shopware\Storefront\Page\Suggest\SuggestPageLoadedHook;

/**
 * @internal
 */
class SearchControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    /**
     * @dataProvider getProviderInvalidTerms
     */
    public function testSearchWithHtml(string $term): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $_SERVER['APP_URL'] . '/search?search=' . urlencode($term));

        $html = $browser->getResponse()->getContent();

        static::assertStringNotContainsString($term, $html);
        static::assertStringContainsString(htmlentities($term), $html);
    }

    public static function getProviderInvalidTerms(): iterable
    {
        yield ['<h1 style="color:red">Test</h1>'];
        yield ['<script\x20type="text/javascript">javascript:alert(1);</script>'];
        yield ['<img src=1 href=1 onerror="javascript:alert(1)"></img>'];
        yield ['<audio src=1 href=1 onerror="javascript:alert(1)"></audio>'];
        yield ['<video src=1 href=1 onerror="javascript:alert(1)"></video>'];
        yield ['<body src=1 href=1 onerror="javascript:alert(1)"></body>'];
        yield ['<object src=1 href=1 onerror="javascript:alert(1)"></object>'];
        yield ['<script src=1 href=1 onerror="javascript:alert(1)"></script>'];
        yield ['<svg onResize svg onResize="javascript:javascript:alert(1)"></svg onResize>'];
        yield ['"/><img/onerror=\x0Ajavascript:alert(1)\x0Asrc=xxx:x />'];
    }

    public function testSearchPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/search', ['search' => 'test']);
        static::assertEquals(200, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(SearchPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testSuggestPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/suggest', ['search' => 'test']);
        static::assertEquals(200, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(SuggestPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testSearchWidgetLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/widgets/search', ['search' => 'test']);
        static::assertEquals(200, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(SearchWidgetLoadedHook::HOOK_NAME, $traces);
    }
}
