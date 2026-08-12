<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Type\AppDocumentTypeConfig;
use Shopware\Core\Checkout\DocumentV2\Type\AppDocumentTypeLoader;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AppDocumentTypeLoader::class)]
final class AppDocumentTypeLoaderTest extends TestCase
{
    public function testLoadReturnsFormatsIntersectedWithValidDocumentFormats(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')
            ->willReturn([
                $this->appFeature(new AppDocumentTypeConfig('swag_warranty', ['html', 'pdf', 'bogus_format'], [], [])),
            ]);

        $loader = new AppDocumentTypeLoader($storage);

        static::assertSame(['swag_warranty' => ['html', 'pdf']], $loader->load());
    }

    public function testLoadSkipsTypeWithoutAnyValidFormatButKeepsItsConfig(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')
            ->willReturn([
                $this->appFeature(new AppDocumentTypeConfig('swag_warranty', ['bogus_format'], [], ['foo' => 'bar'])),
            ]);

        $loader = new AppDocumentTypeLoader($storage);

        static::assertSame([], $loader->load());
        static::assertSame(['foo' => 'bar'], $loader->loadConfig('swag_warranty'));
    }

    public function testLoadConfigReturnsEmptyArrayForUnknownIdentifier(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')
            ->willReturn([
                $this->appFeature(new AppDocumentTypeConfig('swag_warranty', ['html'], [], ['foo' => 'bar'])),
            ]);

        $loader = new AppDocumentTypeLoader($storage);

        static::assertSame([], $loader->loadConfig('does_not_exist'));
    }

    public function testResultIsComputedOnceUntilReset(): void
    {
        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->exactly(2))
            ->method('forActiveApps')
            ->willReturn([
                $this->appFeature(new AppDocumentTypeConfig('swag_warranty', ['html'], [], ['foo' => 'bar'])),
            ]);

        $loader = new AppDocumentTypeLoader($storage);

        static::assertSame(['swag_warranty' => ['html']], $loader->load());
        static::assertSame(['foo' => 'bar'], $loader->loadConfig('swag_warranty'));
        static::assertSame(['swag_warranty' => ['html']], $loader->load());

        $loader->reset();

        static::assertSame(['swag_warranty' => ['html']], $loader->load());
    }

    /**
     * @return AppFeature<AppDocumentTypeConfig>
     */
    private function appFeature(AppDocumentTypeConfig $config): AppFeature
    {
        return new AppFeature(
            appId: 'app-id',
            appName: 'SwagWarranty',
            appActive: true,
            appVersion: '1.0.0',
            appHasSecret: false,
            createdAt: new \DateTimeImmutable(),
            config: $config,
        );
    }
}
