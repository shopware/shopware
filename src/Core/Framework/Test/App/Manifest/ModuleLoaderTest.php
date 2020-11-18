<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\ModuleLoader;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ModuleLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SystemConfigTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var ModuleLoader
     */
    private $moduleLoader;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->moduleLoader = $this->getContainer()->get(ModuleLoader::class);
        $this->context = Context::createDefaultContext();
    }

    public function testLoadActionButtonsForView(): void
    {
        $this->registerModules();

        $loadedModules = $this->moduleLoader->loadModules($this->context);

        usort($loadedModules, function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });

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
                    ],
                    [
                        'label' => [
                            'en-GB' => 'first App second Module',
                        ],
                        'name' => 'second-module',
                    ],
                ],
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
                    ],
                ],
            ],
        ], $loadedModules);
    }

    public function testLoadActionButtonsForViewRetunrsNothingIfAppUrlChangeWasDetected(): void
    {
        $this->registerModules();

        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => 'https://test.com',
            'value' => Uuid::randomHex(),
        ]);

        $loadedModules = $this->moduleLoader->loadModules($this->context);

        static::assertEquals([], $loadedModules);
    }

    private function registerModules(): void
    {
        $this->appRepository->create([[
            'name' => 'App1',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test App1',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'modules' => [
                [
                    'label' => [
                        'en-GB' => 'first App',
                        'de-DE' => 'Erste App',
                    ],
                    'source' => 'https://first.app.com',
                    'name' => 'first-module',
                ],
                [
                    'label' => [
                        'en-GB' => 'first App second Module',
                    ],
                    'source' => 'https://first.app.com/second',
                    'name' => 'second-module',
                ],
            ],
            'integration' => [
                'label' => 'App1',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'App1',
            ],
        ], [
            'name' => 'App2',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test App2',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t2',
            'modules' => [
                [
                    'label' => [
                        'en-GB' => 'second App',
                    ],
                    'source' => 'https://second.app.com',
                    'name' => 'second-app',
                ],
            ],
            'integration' => [
                'label' => 'App2',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'App2',
            ],
        ], [
            'name' => 'App3',
            'active' => false,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test App3',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t2',
            'modules' => [
                [
                    'label' => [
                        'en-GB' => 'third App',
                    ],
                    'source' => 'https://third.app.com',
                    'name' => 'third-app',
                ],
            ],
            'integration' => [
                'label' => 'App3',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'App3',
            ],
        ]], $this->context);
    }

    private function validateSources(array &$loadedModules): void
    {
        $this->validateSource($loadedModules[0]['modules'][0]['source'], 'https://first.app.com', 's3cr3t');
        unset($loadedModules[0]['modules'][0]['source']);

        $this->validateSource($loadedModules[0]['modules'][1]['source'], 'https://first.app.com/second', 's3cr3t');
        unset($loadedModules[0]['modules'][1]['source']);

        $this->validateSource($loadedModules[1]['modules'][0]['source'], 'https://second.app.com', 's3cr3t2');
        unset($loadedModules[1]['modules'][0]['source']);
    }

    private function validateSource(string $givenSource, string $urlPath, string $secret): void
    {
        $url = \parse_url($givenSource);
        $queryString = $url['query'];
        unset($url['query']);

        $expectedUrl = \parse_url($urlPath);
        static::assertEquals($expectedUrl, $url);

        \parse_str($queryString, $query);
        static::assertEquals($_SERVER['APP_URL'], $query['shop-url']);
        static::assertArrayHasKey('shop-id', $query);

        $hmac = $query['shopware-shop-signature'];
        $content = \str_replace('&shopware-shop-signature=' . $hmac, '', $queryString);

        static::assertEquals(hash_hmac('sha256', $content, $secret), $hmac);
    }
}
