<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Client;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentAccountResource;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentDatafeedsResource;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentProductResource;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentShippingSettingResource;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingContentFactory;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use function Flag\skipTestNext6050;

class GoogleShoppingContentFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    /**
     * @var GoogleShoppingClient
     */
    private $client;

    protected function setUp(): void
    {
        skipTestNext6050($this);
        $this->client = $this->getContainer()->get('google_shopping_client');
    }

    public function testCreateContentAccountResource(): void
    {
        $factory = new GoogleShoppingContentFactory($this->client);

        $googleContentAccountResource = $factory->createContentAccountResource();

        static::assertInstanceOf(GoogleShoppingContentAccountResource::class, $googleContentAccountResource);
    }

    public function testCreateContentProductResource(): void
    {
        $factory = new GoogleShoppingContentFactory($this->client);

        $googleContentProductResource = $factory->createContentProductResource();

        static::assertInstanceOf(GoogleShoppingContentProductResource::class, $googleContentProductResource);
    }

    public function testCreateShoppingContentShippingSettingResource(): void
    {
        $factory = new GoogleShoppingContentFactory($this->client);

        $googleContentProductResource = $factory->createShoppingContentShippingSettingResource();

        static::assertInstanceOf(GoogleShoppingContentShippingSettingResource::class, $googleContentProductResource);
    }

    public function testCreateShoppingContentDatafeedsResource(): void
    {
        $factory = new GoogleShoppingContentFactory($this->client);

        $googleContentProductResource = $factory->createShoppingContentDatafeedsResource();

        static::assertInstanceOf(GoogleShoppingContentDatafeedsResource::class, $googleContentProductResource);
    }

    public function testCreateShoppingContentDatafeedResource(): void
    {
        $factory = new GoogleShoppingContentFactory($this->client);

        $googleContentDatafeedResource = $factory->createShoppingContentDatafeedsResource();

        static::assertInstanceOf(GoogleShoppingContentDatafeedsResource::class, $googleContentDatafeedResource);
    }
}
