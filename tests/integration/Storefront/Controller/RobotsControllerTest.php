<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class RobotsControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRobotsTxtOnTopLevelDomainWithMatchingSalesChannelUrl(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $appUrl . '/robots.txt');

        $html = $browser->getResponse()->getContent();

        $appUri = parse_url($appUrl)['path'] ?? '';

        static::assertIsString($html);
        $this->assertRobotsTxt($html, $appUri, $appUrl);
    }

    // google scraps the robots.txt file always on the TLD, so even when we don't have a sales channel url matching,
    // we should still serve the robots.txt file, see https://github.com/FriendsOfShopware/FroshRobotsTxt/issues/3
    public function testRobotsTxtOnTopLevelDomainWithoutMatchingSalesChannelUrl(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $subUrl = $appUrl . '/en';

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement(
            'UPDATE sales_channel_domain SET url = :subdomain WHERE url = :tld',
            [
                'subdomain' => $subUrl,
                'tld' => $appUrl,
            ]
        );

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $appUrl . '/robots.txt');

        $html = $browser->getResponse()->getContent();

        // even if we request over the tld we expect the robots.txt file
        // to include the paths for the sub url of the sales channel domain
        $appUri = parse_url($subUrl)['path'] ?? '';

        static::assertIsString($html);
        $this->assertRobotsTxt($html, $appUri, $subUrl);
    }

    public function testRobotsTxtOnSubDomainWithMatchingSalesChannelUrl(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $subUrl = $appUrl . '/en';

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement(
            'UPDATE sales_channel_domain SET url = :subdomain WHERE url = :tld',
            [
                'subdomain' => $subUrl,
                'tld' => $appUrl,
            ]
        );

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $subUrl . '/robots.txt');

        $html = $browser->getResponse()->getContent();

        $appUri = parse_url($subUrl)['path'] ?? '';

        static::assertIsString($html);
        $this->assertRobotsTxt($html, $appUri, $subUrl);
    }

    public function testRobotsTxtOnSubDomainWithoutMatchingSalesChannelUrlReturns404(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $appUrl . '/en/robots.txt');

        static::assertSame(
            Response::HTTP_NOT_FOUND,
            $browser->getResponse()->getStatusCode()
        );
    }

    private function assertRobotsTxt(string $html, string $domainPath, string $domainUrl): void
    {
        // plugins may append their own User-agent blocks via RobotsPageLoadedEvent, so assert
        // core's rules and the sitemap are present rather than matching the exact output
        $expectedCoreRules = <<<TXT
        User-agent: *

        Allow: /

        Disallow: /*?

        Allow: /*referringSalesChannel=

        Allow: /*theme/

        Allow: /media/*?ts=

        Disallow: {$domainPath}/account/
        Disallow: {$domainPath}/checkout/
        Disallow: {$domainPath}/widgets/
        Allow: {$domainPath}/widgets/cms/
        Allow: {$domainPath}/widgets/menu/offcanvas
        TXT;

        static::assertStringStartsWith($expectedCoreRules, $html);
        static::assertStringContainsString("Sitemap: {$domainUrl}/sitemap.xml", $html);
    }
}
