<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\ModuleLoader;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class ModuleLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository  $appRepository;

    private Context $context;

    private ModuleLoader $moduleLoader;

    private string $defaultSecret = 's3cr3t';

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->moduleLoader = $this->getContainer()->get(ModuleLoader::class);

        $this->context = Context::createDefaultContext();
    }

    public function testLoadModules(): void
    {
        $this->registerAppsWithModules();

        $loadedModules = $this->getSortedModules();

        $this->validateSources($loadedModules);

        static::assertEquals([
            [
                'name' => 'App1',
                'label' => [
                    'en-GB' => 'test App1',
                ],
                'modules' => [
                    [
                        'label' => [
                            'en-GB' => 'first App',
                            'de-DE' => 'Erste App',
                        ],
                        'name' => 'first-module',
                        'parent' => 'sw-catalogue',
                        'position' => 50,
                    ],
                    [
                        'label' => [
                            'en-GB' => 'first App second Module',
                        ],
                        'name' => 'second-module',
                        'parent' => null,
                        'position' => 1,
                    ],
                ],
                'mainModule' => null,
            ],
            [
                'name' => 'App2',
                'label' => [
                    'en-GB' => 'test App2',
                ],
                'modules' => [
                    [
                        'label' => [
                            'en-GB' => 'second App',
                        ],
                        'name' => 'second-app',
                        'parent' => 'sw-catalogue',
                        'position' => 50,
                    ],
                ],
                'mainModule' => null,
            ],
        ], $loadedModules);
    }

    public function testLoadModulesReturnsNothingIfAppUrlChangeWasDetected(): void
    {
        $this->registerAppsWithModules();

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => 'https://test.com',
            'value' => Uuid::randomHex(),
        ]);

        $loadedModules = $this->getSortedModules();

        static::assertEquals([], $loadedModules);
    }

    public function testMainModules(): void
    {
        $this->createApp('App1', [
            'mainModule' => [
                'source' => 'http://main-module-1',
            ],
        ]);
        $this->createApp('App2', [
            'modules' => [
                [
                    'label' => [
                        'en-GB' => 'test module',
                    ],
                    'name' => 'test-app',
                    'parent' => 'sw-catalogue',
                ],
            ],
        ]);

        $loadedModules = $this->getSortedModules();

        $this->validateSource($loadedModules[0]['mainModule']['source'], 'http://main-module-1', $this->defaultSecret);
        static::assertNull($loadedModules[1]['mainModule']);
    }

    public function testAppIsExcludedIfNeitherModulesNorMainModuleIsSet(): void
    {
        $this->createApp('App');

        $modules = $this->getSortedModules();
        static::assertEquals([], $modules);
    }

    /**
     * @param array<string, mixed> ...$params
     */
    private function createApp(string $name, array ...$params): void
    {
        $payload = [
            'name' => $name,
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => "test {$name}",
            'accessToken' => 'test',
            'appSecret' => $this->defaultSecret,
            'integration' => [
                'label' => $name,
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => $name,
            ],
        ];

        foreach ($params as $additionalParams) {
            $payload = [...$payload, ...$additionalParams];
        }

        $this->appRepository->create([$payload], $this->context);
    }

    private function registerAppsWithModules(): void
    {
        $this->createApp('App1', [
            'modules' => [
                [
                    'label' => [
                        'en-GB' => 'first App',
                        'de-DE' => 'Erste App',
                    ],
                    'source' => 'https://first.app.com',
                    'name' => 'first-module',
                    'parent' => 'sw-catalogue',
                    'position' => 50,
                ],
                [
                    'label' => [
                        'en-GB' => 'first App second Module',
                    ],
                    'source' => 'https://first.app.com/second',
                    'name' => 'second-module',
                    'parent' => null,
                    'position' => 1,
                ],
            ],
        ]);

        $this->createApp('App2', [
            'modules' => [
                [
                    'label' => [
                        'en-GB' => 'second App',
                    ],
                    'source' => null,
                    'name' => 'second-app',
                    'parent' => 'sw-catalogue',
                    'position' => 50,
                ],
            ],
        ]);

        $this->createApp('App3', [
            'active' => false,
            'modules' => [
                [
                    'label' => [
                        'en-GB' => 'third App',
                    ],
                    'source' => 'https://third.app.com',
                    'name' => 'third-app',
                ],
            ],
        ]);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getSortedModules(): array
    {
        $modules = $this->moduleLoader->loadModules($this->context);

        usort($modules, fn ($a, $b) => $a['name'] <=> $b['name']);

        return $modules;
    }

    /**
     * @param array<array<string, mixed>> $loadedModules
     */
    private function validateSources(array &$loadedModules): void
    {
        $this->validateSource($loadedModules[0]['modules'][0]['source'], 'https://first.app.com', $this->defaultSecret);
        unset($loadedModules[0]['modules'][0]['source']);

        $this->validateSource($loadedModules[0]['modules'][1]['source'], 'https://first.app.com/second', $this->defaultSecret);
        unset($loadedModules[0]['modules'][1]['source']);

        static::assertNull($loadedModules[1]['modules'][0]['source']);
        unset($loadedModules[1]['modules'][0]['source']);
    }

    private function validateSource(string $givenSource, string $urlPath, string $secret): void
    {
        $url = parse_url($givenSource);
        static::assertIsArray($url);
        static::assertArrayHasKey('query', $url);
        $queryString = $url['query'];
        unset($url['query']);

        $expectedUrl = parse_url($urlPath);
        static::assertEquals($expectedUrl, $url);

        /** @var array{"value": string} $shopId */
        $shopId = $this->getContainer()->get(SystemConfigService::class)->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);

        parse_str($queryString, $query);
        static::assertEquals($_SERVER['APP_URL'], $query['shop-url']);
        static::assertArrayHasKey('shop-id', $query);
        static::assertEquals($shopId['value'], $query['shop-id']);
        static::assertArrayHasKey('sw-version', $query);
        static::assertEquals($this->getContainer()->getParameter('kernel.shopware_version'), $query['sw-version']);
        static::assertArrayHasKey('sw-context-language', $query);
        static::assertEquals(Context::createDefaultContext()->getLanguageId(), $query['sw-context-language']);
        static::assertArrayHasKey('sw-user-language', $query);
        static::assertEquals('en-GB', $query['sw-user-language']);
        static::assertArrayHasKey('shopware-shop-signature', $query);

        $signature = $query['shopware-shop-signature'];

        static::assertIsString($signature);

        $signedQuery = str_replace('&shopware-shop-signature=' . $signature, '', $queryString);

        static::assertEquals(hash_hmac('sha256', $signedQuery, $secret), $signature);
    }
}
