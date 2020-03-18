<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Client;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentAccountResource;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingContentFactory;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use function Flag\skipTestNext6050;

class GoogleShoppingContentFactoryTest extends TestCase
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

        $factory = new GoogleShoppingContentFactory($googleShoppingClient);

        $googleContentAccountResource = $factory->createShoppingContentAccountResource();

        static::assertInstanceOf(GoogleShoppingContentAccountResource::class, $googleContentAccountResource);
    }
}
