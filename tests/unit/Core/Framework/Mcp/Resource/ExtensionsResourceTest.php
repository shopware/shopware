<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Resource;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Resource\ExtensionsResource;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExtensionsResource::class)]
class ExtensionsResourceTest extends TestCase
{
    public function testReturnsCorrectUriAndMimeType(): void
    {
        $result = ($this->makeResource())();

        static::assertSame('shopware://extensions', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);
    }

    public function testPluginNotInstalled(): void
    {
        $data = $this->invoke($this->makeResource());

        $merchant = $this->findExtension($data, 'SwagMcpMerchantAssistant');
        static::assertSame('not_installed', $merchant['status']);
        static::assertSame('plugin', $merchant['type']);
        static::assertSame('bin/console plugin:install --activate SwagMcpMerchantAssistant', $merchant['install_command']);
    }

    public function testPluginInstalledButInactive(): void
    {
        $data = $this->invoke($this->makeResource(pluginRows: ['SwagMcpMerchantAssistant' => '0']));

        $merchant = $this->findExtension($data, 'SwagMcpMerchantAssistant');
        static::assertSame('installed', $merchant['status']);
        static::assertSame('bin/console plugin:activate SwagMcpMerchantAssistant', $merchant['install_command']);
    }

    public function testPluginActive(): void
    {
        $data = $this->invoke($this->makeResource(pluginRows: ['SwagMcpMerchantAssistant' => '1']));

        $merchant = $this->findExtension($data, 'SwagMcpMerchantAssistant');
        static::assertSame('active', $merchant['status']);
        static::assertNull($merchant['install_command']);
    }

    public function testAppNotInstalled(): void
    {
        $data = $this->invokeWithExtensions(
            [['name' => 'MyMcpApp', 'type' => 'app', 'tool_prefix' => 'my-', 'description' => 'Test app', 'install_command' => 'composer require my/app', 'documentation_url' => null]],
            appRows: [],
        );

        $app = $this->findExtension($data, 'MyMcpApp');
        static::assertSame('not_installed', $app['status']);
        static::assertSame('composer require my/app', $app['install_command']);
    }

    public function testAppInstalledButInactive(): void
    {
        $data = $this->invokeWithExtensions(
            [['name' => 'MyMcpApp', 'type' => 'app', 'tool_prefix' => 'my-', 'description' => 'Test app', 'install_command' => 'composer require my/app', 'documentation_url' => null]],
            appRows: ['MyMcpApp' => '0'],
        );

        $app = $this->findExtension($data, 'MyMcpApp');
        static::assertSame('installed', $app['status']);
        static::assertNull($app['install_command']);
    }

    public function testAppActive(): void
    {
        $data = $this->invokeWithExtensions(
            [['name' => 'MyMcpApp', 'type' => 'app', 'tool_prefix' => 'my-', 'description' => 'Test app', 'install_command' => 'composer require my/app', 'documentation_url' => null]],
            appRows: ['MyMcpApp' => '1'],
        );

        $app = $this->findExtension($data, 'MyMcpApp');
        static::assertSame('active', $app['status']);
        static::assertNull($app['install_command']);
    }

    public function testBundleNotRegistered(): void
    {
        $data = $this->invokeWithExtensions(
            [['name' => 'SwagMcpExampleBundle', 'type' => 'bundle', 'tool_prefix' => 'example-', 'description' => 'Test bundle', 'install_command' => 'composer require swag/example', 'documentation_url' => null]],
            registeredBundles: [],
        );

        $bundle = $this->findExtension($data, 'SwagMcpExampleBundle');
        static::assertSame('not_installed', $bundle['status']);
        static::assertSame('composer require swag/example', $bundle['install_command']);
    }

    public function testBundleRegisteredIsActive(): void
    {
        $bundleMock = $this->createMock(BundleInterface::class);
        $data = $this->invokeWithExtensions(
            [['name' => 'SwagMcpExampleBundle', 'type' => 'bundle', 'tool_prefix' => 'example-', 'description' => 'Test bundle', 'install_command' => 'composer require swag/example', 'documentation_url' => null]],
            registeredBundles: ['SwagMcpExampleBundle' => $bundleMock],
        );

        $bundle = $this->findExtension($data, 'SwagMcpExampleBundle');
        static::assertSame('active', $bundle['status']);
        static::assertNull($bundle['install_command']);
    }

    public function testKnownExtensionsIncludeMerchantAssistant(): void
    {
        $data = $this->invoke($this->makeResource());

        static::assertContains('SwagMcpMerchantAssistant', array_column($data, 'name'));
    }

    public function testMerchantAssistantHasExpectedFields(): void
    {
        $data = $this->invoke($this->makeResource());

        $merchant = $this->findExtension($data, 'SwagMcpMerchantAssistant');
        static::assertSame('plugin', $merchant['type']);
        static::assertSame('merchant-', $merchant['tool_prefix']);
        static::assertArrayHasKey('description', $merchant);
        static::assertArrayHasKey('documentation_url', $merchant);
    }

    /**
     * @param list<array{name: string, type: 'plugin'|'bundle'|'app', tool_prefix: string, description: string, install_command: string, documentation_url: string|null}> $extensions
     * @param array<string, string> $pluginRows
     * @param array<string, string> $appRows
     * @param array<string, BundleInterface> $registeredBundles
     *
     * @return list<array<string, mixed>>
     */
    private function invokeWithExtensions(array $extensions, array $pluginRows = [], array $appRows = [], array $registeredBundles = []): array
    {
        $resource = $this->makeResourceWithExtensions($extensions, $pluginRows, $appRows, $registeredBundles);

        return $this->invoke($resource);
    }

    /**
     * @param list<array{name: string, type: 'plugin'|'bundle'|'app', tool_prefix: string, description: string, install_command: string, documentation_url: string|null}> $extensions
     * @param array<string, string> $pluginRows
     * @param array<string, string> $appRows
     * @param array<string, BundleInterface> $registeredBundles
     */
    private function makeResourceWithExtensions(array $extensions, array $pluginRows = [], array $appRows = [], array $registeredBundles = []): ExtensionsResource
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturnCallback(
            static function (string $sql) use ($pluginRows, $appRows): array {
                if (str_contains($sql, '`plugin`')) {
                    return $pluginRows;
                }
                if (str_contains($sql, '`app`')) {
                    return $appRows;
                }

                return [];
            }
        );

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getBundles')->willReturn($registeredBundles);

        return new class($connection, $kernel, $extensions) extends ExtensionsResource {
            /**
             * @param list<array{name: string, type: 'plugin'|'bundle'|'app', tool_prefix: string, description: string, install_command: string, documentation_url: string|null}> $testExtensions
             */
            public function __construct(
                Connection $connection,
                KernelInterface $kernel,
                private readonly array $testExtensions,
            ) {
                parent::__construct($connection, $kernel);
            }

            protected function getKnownExtensions(): array
            {
                return $this->testExtensions;
            }
        };
    }

    /**
     * @param array<string, string> $pluginRows plugin name → active flag ('0'|'1')
     * @param array<string, string> $appRows app name → active flag ('0'|'1')
     * @param array<string, BundleInterface> $registeredBundles bundle name → instance
     */
    private function makeResource(array $pluginRows = [], array $appRows = [], array $registeredBundles = []): ExtensionsResource
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturnCallback(
            static function (string $sql) use ($pluginRows, $appRows): array {
                if (str_contains($sql, '`plugin`')) {
                    return $pluginRows;
                }
                if (str_contains($sql, '`app`')) {
                    return $appRows;
                }

                return [];
            }
        );

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getBundles')->willReturn($registeredBundles);

        return new ExtensionsResource($connection, $kernel);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoke(ExtensionsResource $resource): array
    {
        $result = ($resource)();

        return json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array<string, mixed>> $data
     *
     * @return array<string, mixed>
     */
    private function findExtension(array $data, string $name): array
    {
        foreach ($data as $entry) {
            if ($entry['name'] === $name) {
                return $entry;
            }
        }

        static::fail(\sprintf('Extension "%s" not found in resource output', $name));
    }
}
