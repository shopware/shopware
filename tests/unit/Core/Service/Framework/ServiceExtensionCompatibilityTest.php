<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopware\Core\Framework\Update\Struct\Version;
use Shopware\Core\Service\Framework\ServiceExtensionCompatibility;
use Shopware\Core\Service\ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistryEntry;

/**
 * @internal
 */
#[CoversClass(ServiceExtensionCompatibility::class)]
class ServiceExtensionCompatibilityTest extends TestCase
{
    private ServiceRegistryClient&MockObject $serviceRegistryClient;

    private ExtensionCompatibility&MockObject $compatibility;

    private Version $update;

    protected function setUp(): void
    {
        $this->update = new Version([
            'version' => '6.7.0.0',
            'title' => 'Shopware 6.7.0.0',
            'body' => 'Shopware 6.7.0.0',
            'date' => new \DateTimeImmutable(),
        ]);

        $this->compatibility = $this->createMock(ExtensionCompatibility::class);
        $this->serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
    }

    public function testAppWithSameNameAsServiceIsMarkedAsUpdatableFuture(): void
    {
        $this->compatibility->expects(static::once())
            ->method('getExtensionCompatibilities')
            ->willReturn([
                [
                    'name' => 'TestApp',
                    'managedByComposer' => false,
                    'installedVersion' => '1.0.0',
                    'statusVariant' => 'error',
                    'statusColor' => null,
                    'statusMessage' => '',
                    'statusName' => ExtensionCompatibility::PLUGIN_COMPATIBILITY_NOT_IN_STORE,
                ],
            ]);

        $this->serviceRegistryClient->expects(static::once())
            ->method('getAll')
            ->willReturn([
                new ServiceRegistryEntry('TestApp', 'TestApp', 'https://www.testapp.com', '/'),
            ]);

        $extensionCompatibilityDecorator = new ServiceExtensionCompatibility(
            $this->compatibility,
            $this->serviceRegistryClient,
            $this->createMock(AbstractExtensionDataProvider::class)
        );

        $compatibilities = $extensionCompatibilityDecorator->getExtensionCompatibilities(
            $this->update,
            Context::createDefaultContext()
        );

        static::assertCount(1, $compatibilities);
        static::assertSame('updatableFuture', $compatibilities[0]['statusName']);
        static::assertSame('With new Shopware version', $compatibilities[0]['statusMessage']);
        static::assertSame('yellow', $compatibilities[0]['statusColor']);
        static::assertNull($compatibilities[0]['statusVariant']);
    }

    public function testIncompatibleAppWithNoServiceIsMarkedAsNotInStore(): void
    {
        $this->compatibility->expects(static::once())
            ->method('getExtensionCompatibilities')
            ->willReturn([
                [
                    'name' => 'TestApp',
                    'managedByComposer' => false,
                    'installedVersion' => '1.0.0',
                    'statusVariant' => 'error',
                    'statusColor' => null,
                    'statusMessage' => '',
                    'statusName' => ExtensionCompatibility::PLUGIN_COMPATIBILITY_NOT_IN_STORE,
                ],
            ]);

        $this->serviceRegistryClient->expects(static::once())
            ->method('getAll')
            ->willReturn([]);

        $extensionCompatibilityDecorator = new ServiceExtensionCompatibility(
            $this->compatibility,
            $this->serviceRegistryClient,
            $this->createMock(AbstractExtensionDataProvider::class)
        );

        $compatibilities = $extensionCompatibilityDecorator->getExtensionCompatibilities(
            $this->update,
            Context::createDefaultContext()
        );

        static::assertCount(1, $compatibilities);
        static::assertSame('notInStore', $compatibilities[0]['statusName']);
        static::assertSame('', $compatibilities[0]['statusMessage']);
        static::assertNull($compatibilities[0]['statusColor']);
        static::assertSame('error', $compatibilities[0]['statusVariant']);
    }

    public function testGetExtensionsToDeactivateReturnsAppWithSameNameAsService(): void
    {
        $extensionDataProvider = $this->createMock(AbstractExtensionDataProvider::class);

        $extensionDataProvider->method('getInstalledExtensions')
            ->willReturn(new ExtensionCollection([
                'CompatibleApp' => (new ExtensionStruct())->assign(['name' => 'CompatibleApp', 'active' => true]),
                'TestApp' => (new ExtensionStruct())->assign(['name' => 'TestApp', 'active' => true]),
            ]));

        $this->compatibility->expects(static::once())->method('getExtensionsToDeactivate')
            ->willReturn([]);

        $this->compatibility->expects(static::once())
            ->method('getExtensionCompatibilities')
            ->willReturn([
                [
                    'name' => 'CompatibleApp',
                    'managedByComposer' => false,
                    'installedVersion' => '1.0.0',
                    'statusVariant' => 'success',
                    'statusColor' => null,
                    'statusMessage' => 'Compatible',
                    'statusName' => ExtensionCompatibility::PLUGIN_COMPATIBILITY_COMPATIBLE,
                ],
                [
                    'name' => 'TestApp',
                    'managedByComposer' => false,
                    'installedVersion' => '1.0.0',
                    'statusVariant' => 'error',
                    'statusColor' => null,
                    'statusMessage' => '',
                    'statusName' => ExtensionCompatibility::PLUGIN_COMPATIBILITY_NOT_IN_STORE,
                ],
            ]);

        $this->serviceRegistryClient->expects(static::once())
            ->method('getAll')
            ->willReturn([
                new ServiceRegistryEntry('TestApp', 'TestApp', 'https://www.testapp.com', '/'),
            ]);

        $extensionCompatibilityDecorator = new ServiceExtensionCompatibility(
            $this->compatibility,
            $this->serviceRegistryClient,
            $extensionDataProvider
        );

        $extensions = $extensionCompatibilityDecorator->getExtensionsToDeactivate($this->update, Context::createDefaultContext());

        static::assertCount(1, $extensions);
        static::assertSame('TestApp', $extensions[0]->getName());
    }
}
