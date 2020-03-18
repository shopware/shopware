<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Client;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClientFactory;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use function Flag\skipTestNext6050;

class GoogleShoppingClientFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    protected function setUp(): void
    {
        skipTestNext6050($this);
    }

    public function testCreateGoogleShoppingClient(): void
    {
        /** @var SystemConfigService $systemConfig */
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.googleShopping.clientId', 'clientId');
        $systemConfig->set('core.googleShopping.clientSecret', 'clientSecret');
        $systemConfig->set('core.googleShopping.redirectUri', 'redirectUri');

        $factory = new GoogleShoppingClientFactory($systemConfig);
        $googleShoppingClient = $factory->createClient();

        static::assertInstanceOf(GoogleShoppingClient::class, $googleShoppingClient);
    }
}
