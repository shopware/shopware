<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Authentication\AbstractAuthenticationProvider;
use Shopware\Core\Framework\Store\Exception\StoreLicenseDomainMissingException;
use Shopware\Core\Framework\Store\Services\ExtensionLoader;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class StoreClientTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SystemConfigService $systemConfigService;

    /**
     * @var Client|MockObject
     */
    private $mockClient;

    private StoreClient $storeClient;

    public function setUp(): void
    {
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->mockClient = $this->createMock(Client::class);

        $this->storeClient = new StoreClient(
            $this->getContainer()->getParameter('shopware.store_endpoints'),
            $this->getContainer()->get(StoreService::class),
            $this->getContainer()->get('plugin.repository'),
            $this->systemConfigService,
            $this->getContainer()->get(AbstractAuthenticationProvider::class),
            $this->getContainer()->get(ExtensionLoader::class),
            $this->mockClient
        );
    }

    public function testSignPayloadWithAppSecret(): void
    {
        $this->systemConfigService->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, 'test.com');
        $this->systemConfigService->set('core.store.shopSecret', 'secret');

        $this->mockClient->expects(static::once())
            ->method('post')
            ->with(
                '/swplatform/generatesignature',
                [
                    'query' => [
                        'shopwareVersion' => '___VERSION___',
                        'language' => 'en-GB',
                        'domain' => 'test.com',
                    ],
                    'headers' => [
                        'X-Shopware-Shop-Secret' => 'secret',
                    ],
                    'json' => [
                        'appName' => 'testApp',
                        'payload' => '[this can be anything]',
                    ],
                ],
            )
            ->willReturn(new Response(200, [], '{"signature": "signed"}'));

        $this->mockClient->method('getConfig')->willReturn([]);

        static::assertEquals('signed', $this->storeClient->signPayloadWithAppSecret('[this can be anything]', 'testApp'));
    }

    public function testItThrowsIfLicenseDomainIsNotSet(): void
    {
        static::expectException(StoreLicenseDomainMissingException::class);
        $this->storeClient->signPayloadWithAppSecret('[this can be anything]', 'testApp');
    }
}
