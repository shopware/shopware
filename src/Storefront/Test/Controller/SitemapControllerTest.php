<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Sitemap\SitemapPageLoadedHook;

/**
 * @internal
 */
class SitemapControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testSitemapPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/sitemap.xml', []);
        static::assertEquals(200, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(SitemapPageLoadedHook::HOOK_NAME, $traces);
    }
}
