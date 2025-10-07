<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
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
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $appUrl . '/robots.txt');

        $html = $browser->getResponse()->getContent();

        $appUri = parse_url($appUrl)['path'] ?? '';

        static::assertIsString($html);
        // Default rules block followed by domain-specific rules block, separated by double newline
        $expected = "User-agent: *\n\nAllow: /\nDisallow: /*?\nAllow: /*theme/\nAllow: /media/*?ts=\n\n\nDisallow: {$appUri}/account/\nDisallow: {$appUri}/checkout/\nDisallow: {$appUri}/widgets/\nAllow: {$appUri}/widgets/cms/\nAllow: {$appUri}/widgets/menu/offcanvas\n\nSitemap: {$appUrl}/sitemap.xml";
        static::assertSame($expected, $html);
    }
}
