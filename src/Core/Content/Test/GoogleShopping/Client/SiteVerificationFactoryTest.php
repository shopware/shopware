<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Client;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\SiteVerificationResource;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingSiteVerificationFactory;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use function Flag\skipTestNext6050;

class SiteVerificationFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    protected function setUp(): void
    {
        skipTestNext6050($this);
    }

    public function testCreateShoppingContentAccountResource(): void
    {
        /** @var GoogleShoppingClient $googleShoppingClient */
        $googleShoppingClient = $this->getContainer()->get('google_shopping_client');

        $factory = new GoogleShoppingSiteVerificationFactory($googleShoppingClient);

        $siteVerificationResource = $factory->createSiteVerificationResource();

        static::assertInstanceOf(SiteVerificationResource::class, $siteVerificationResource);
    }
}
