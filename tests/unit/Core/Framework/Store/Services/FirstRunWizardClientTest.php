<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(FirstRunWizardClient::class)]
class FirstRunWizardClientTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context(new AdminApiSource(Uuid::randomHex()));
    }

    public function testFrwLogin(): void
    {
        $firstRunWizardUserToken = [
            'firstRunWizardUserToken' => [
                'token' => 'frw-us3r-t0k3n',
                'expirationDate' => (new \DateTimeImmutable('2021-01-01 00:00:00'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        ];

        $frwClient = $this->createFrwClient(
            [
                Request::METHOD_POST,
                '/swplatform/firstrunwizard/login',
                [
                    'json' => [
                        'shopwareId' => 'j.doe@shopware.com',
                        'password' => 'p4ssw0rd',
                    ],
                    'query' => [],
                ],
            ],
            $firstRunWizardUserToken
        );

        static::assertEquals(
            $firstRunWizardUserToken,
            $frwClient->frwLogin('j.doe@shopware.com', 'p4ssw0rd', $this->context)
        );
    }

    public function testFrwLoginFailsIfContextSourceIsNotAdminApi(): void
    {
        $context = Context::createDefaultContext();

        $client = $this->createMock(ClientInterface::class);
        $client->expects(static::never())
            ->method('request');

        $frwClient = new FirstRunWizardClient(
            $client,
            $this->createMock(AbstractStoreRequestOptionsProvider::class),
            $this->createMock(InstanceService::class)
        );

        $this->expectException(\RuntimeException::class);
        $frwClient->frwLogin('shopwareId', 'password', $context);
    }

    public function testFrwLoginFailsIfAdminApiSourceHasNoUserId(): void
    {
        $context = Context::createDefaultContext(
            new AdminApiSource(null),
        );

        $client = $this->createMock(ClientInterface::class);
        $client->expects(static::never())
            ->method('request');

        $frwClient = new FirstRunWizardClient(
            $client,
            $this->createMock(AbstractStoreRequestOptionsProvider::class),
            $this->createMock(InstanceService::class)
        );

        $this->expectException(\RuntimeException::class);
        $frwClient->frwLogin('shopwareId', 'password', $context);
    }

    public function testUpgradeAccessTokenFailsIfUserIsNotLoggedIn(): void
    {
        $context = Context::createDefaultContext();

        $client = $this->createMock(ClientInterface::class);
        $client->expects(static::never())
            ->method('request');

        $frwClient = new FirstRunWizardClient(
            $client,
            $this->createMock(AbstractStoreRequestOptionsProvider::class),
            $this->createMock(InstanceService::class)
        );

        $this->expectException(\RuntimeException::class);
        $frwClient->upgradeAccessToken($context);
    }

    public function testUpgradeAccessToken(): void
    {
        $shopUserToken = [
            'shopUserToken' => [
                'token' => 'store-us3r-t0k3n',
                'expirationDate' => (new \DateTimeImmutable('2021-01-01 00:00:00'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        ];

        static::assertInstanceOf(AdminApiSource::class, $this->context->getSource());

        $frwClient = $this->createFrwClient(
            [
                Request::METHOD_POST,
                '/swplatform/login/upgrade',
                [
                    'json' => [
                        'shopwareUserId' => $this->context->getSource()->getUserId(),
                    ],
                    'query' => [],
                    'headers' => [],
                ],
            ],
            $shopUserToken
        );

        static::assertEquals(
            $shopUserToken,
            $frwClient->upgradeAccessToken($this->context)
        );
    }

    public function testGetRecommendationRegions(): void
    {
        $regions = [
            'regions' => [
                'DE',
                'US',
            ],
        ];

        $frwClient = $this->createFrwClient(
            [
                Request::METHOD_GET,
                '/swplatform/firstrunwizard/categories',
                [
                    'query' => [],
                ],
            ],
            $regions
        );

        static::assertEquals(
            $regions,
            $frwClient->getRecommendationRegions($this->context)
        );
    }

    public function testGetRecommendations(): void
    {
        $recommendations = [
            'iconPath' => 'https://icon.path',
            'id' => 123456,
            'isCategoryLead' => false,
            'localizedInfo' => [
                'name' => 'SwagLanguagePack',
                'label' => 'Shopware Language Pack',
            ],
            'name' => 'SwagLanguagePack',
            'priority' => 1,
            'producer' => [
                'name' => 'shopware AG',
            ],
        ];

        $frwClient = $this->createFrwClient(
            [
                Request::METHOD_GET,
                '/swplatform/firstrunwizard/plugins',
                [
                    'query' => [
                        'market' => 'us-west',
                        'category' => 'payment',
                    ],
                ],
            ],
            $recommendations
        );

        static::assertEquals(
            $recommendations,
            $frwClient->getRecommendations('us-west', 'payment', $this->context)
        );
    }

    public function testGetLanguagePlugins(): void
    {
        $languagePlugins = [
            [
                'id' => 123456,
                'name' => 'SwagLanguagePack',
            ],
        ];

        $frwClient = $this->createFrwClient(
            [
                Request::METHOD_GET,
                '/swplatform/firstrunwizard/localizations',
                [
                    'query' => [],
                ],
            ],
            $languagePlugins
        );

        static::assertEquals(
            $languagePlugins,
            $frwClient->getLanguagePlugins($this->context)
        );
    }

    public function testGetDemodataPlugins(): void
    {
        $languagePlugins = [
            [
                'id' => 123456,
                'name' => 'SwagLanguagePack',
            ],
        ];

        $frwClient = $this->createFrwClient(
            [
                Request::METHOD_GET,
                '/swplatform/firstrunwizard/demodataplugins',
                [
                    'query' => [],
                ],
            ],
            $languagePlugins
        );

        static::assertEquals(
            $languagePlugins,
            $frwClient->getDemoDataPlugins($this->context)
        );
    }

    public function testGetLicenseDomains(): void
    {
        $licenseDomains = [
            [
                'id' => 123456,
                'domain' => 'shopware.swag',
            ],
        ];

        $frwClient = $this->createFrwClient(
            [
                Request::METHOD_GET,
                '/swplatform/firstrunwizard/shops',
                [
                    'query' => [],
                    'headers' => [],
                ],
            ],
            $licenseDomains
        );

        static::assertEquals(
            $licenseDomains,
            $frwClient->getLicenseDomains($this->context)
        );
    }

    public function testCheckVerificationSecret(): void
    {
        $frwClient = $this->createFrwClient(
            [
                Request::METHOD_POST,
                '/swplatform/firstrunwizard/shops',
                [
                    'headers' => [],
                    'json' => [
                        'domain' => 'shopware.swag',
                        'shopwareVersion' => '',
                        'testEnvironment' => true,
                    ],
                ],
            ],
            []
        );

        $frwClient->checkVerificationSecret('shopware.swag', $this->context, true);
    }

    public function testFetchVerificationInfo(): void
    {
        $verificationInfo = [
            [
                'content' => 'sw-v3rific4t0n-h4sh',
                'fileName' => 'sw-domain-hash.html',
            ],
        ];

        $frwClient = $this->createFrwClient(
            [
                Request::METHOD_POST,
                '/swplatform/firstrunwizard/shopdomainverificationhash',
                [
                    'headers' => [],
                    'json' => [
                        'domain' => 'shopware.swag',
                    ],
                    'query' => [],
                ],
            ],
            $verificationInfo
        );

        static::assertEquals(
            $verificationInfo,
            $frwClient->fetchVerificationInfo('shopware.swag', $this->context)
        );
    }

    /**
     * @param array{string, string, array{headers?: array<string, string>, query?: array<string, string>, json?: array<mixed>}} $requestParams
     * @param array<mixed> $responseBody
     */
    private function createFrwClient(array $requestParams, array $responseBody): FirstRunWizardClient
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(static::once())
            ->method('request')
            ->with(...$requestParams)
            ->willReturn(new Response(body: json_encode($responseBody, \JSON_THROW_ON_ERROR)));

        return new FirstRunWizardClient(
            $client,
            $this->createMock(AbstractStoreRequestOptionsProvider::class),
            $this->createMock(InstanceService::class),
        );
    }
}
