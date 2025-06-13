<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
#[Package('framework')]
class RobotsControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRobotsTxt(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $_SERVER['APP_URL'] . '/robots.txt');

        $html = $browser->getResponse()->getContent();

        static::assertIsString($html);
        static::assertStringContainsString("User-agent: *\n\nAllow: /\n\nDisallow: /*?\n\nAllow: /*theme/\n\nAllow: /media/*?ts=\n\n\nSitemap: {$_SERVER['APP_URL']}/sitemap.xml", $html);
    }
}
