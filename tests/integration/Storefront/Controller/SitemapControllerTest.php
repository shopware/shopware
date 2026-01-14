<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Sitemap\SitemapPageLoadedHook;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;

/**
 * @internal
 */
class SitemapControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testSitemapPageLoadedHookScriptsAreExecuted(): void
    {
        $browser = $this->createStorefrontBrowser();
        $browser->request('GET', EnvironmentHelper::getVariable('APP_URL') . '/sitemap.xml');

        static::assertSame(200, $browser->getResponse()->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(SitemapPageLoadedHook::HOOK_NAME, $traces);
    }
}
