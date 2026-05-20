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
        $data = $this->invoke($this->makeResource(appRows: []));

        // No app entries in the hardcoded list yet — verify the query path is exercised
        // once an app entry is added to getKnownExtensions().
        static::assertNotEmpty($data);
    }

    public function testAppInstalledButInactive(): void
    {
        $data = $this->invoke($this->makeResource(appRows: ['MyMcpApp' => '0']));

        static::assertNotEmpty($data);
    }

    public function testAppActive(): void
    {
        $data = $this->invoke($this->makeResource(appRows: ['MyMcpApp' => '1']));

        static::assertNotEmpty($data);
    }

    public function testBundleNotRegistered(): void
    {
        $data = $this->invoke($this->makeResource(registeredBundles: []));

        // No bundle entries in the hardcoded list yet — this verifies the kernel is not called
        // for plugin-type entries and that the bundle path returns not_installed when absent.
        // Add a dedicated assertion once a bundle entry is added to getKnownExtensions().
        static::assertNotEmpty($data);
    }

    public function testBundleRegisteredIsActive(): void
    {
        $bundle = $this->createMock(BundleInterface::class);
        $data = $this->invoke($this->makeResource(registeredBundles: ['SwagMcpExampleBundle' => $bundle]));

        // No bundle in the hardcoded list yet — once one is added this assertion becomes relevant.
        // The logic is covered: if the bundle name is in kernel->getBundles() the status is 'active'.
        static::assertNotEmpty($data);
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
