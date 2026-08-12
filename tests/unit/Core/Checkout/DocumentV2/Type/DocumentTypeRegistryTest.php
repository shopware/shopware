<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Type\AppDocumentTypeConfig;
use Shopware\Core\Checkout\DocumentV2\Type\AppDocumentTypeLoader;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentType;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentTypeRegistry::class)]
final class DocumentTypeRegistryTest extends TestCase
{
    public function testGetTechnicalNamesReturnsUnionOfCoreAndAppIdentifiers(): void
    {
        $registry = new DocumentTypeRegistry(
            [new StaticDocumentType('invoice', ['html', 'pdf'])],
            $this->appDocumentTypeLoader(['swag_warranty' => ['html', 'pdf']]),
        );

        static::assertSame(['invoice', 'swag_warranty'], $registry->getTechnicalNames());
    }

    public function testGetSupportedFormatsForCoreType(): void
    {
        $registry = new DocumentTypeRegistry(
            [new StaticDocumentType('invoice', ['html', 'pdf'])],
            $this->appDocumentTypeLoader([]),
        );

        static::assertSame(['html', 'pdf'], $registry->getSupportedFormats('invoice'));
    }

    public function testGetSupportedFormatsForAppType(): void
    {
        $registry = new DocumentTypeRegistry(
            [],
            $this->appDocumentTypeLoader(['swag_warranty' => ['html', 'pdf']]),
        );

        static::assertSame(['html', 'pdf'], $registry->getSupportedFormats('swag_warranty'));
    }

    public function testGetSupportedFormatsReturnsEmptyArrayForUnknownType(): void
    {
        $registry = new DocumentTypeRegistry([], $this->appDocumentTypeLoader([]));

        static::assertSame([], $registry->getSupportedFormats('does_not_exist'));
    }

    public function testSupports(): void
    {
        $registry = new DocumentTypeRegistry(
            [new StaticDocumentType('invoice', ['html'])],
            $this->appDocumentTypeLoader(['swag_warranty' => ['html']]),
        );

        static::assertTrue($registry->supports('invoice'));
        static::assertTrue($registry->supports('swag_warranty'));
        static::assertFalse($registry->supports('does_not_exist'));
    }

    public function testValidateFormatsPassesForSupportedFormats(): void
    {
        $registry = new DocumentTypeRegistry(
            [new StaticDocumentType('invoice', ['html', 'pdf'])],
            $this->appDocumentTypeLoader([]),
        );

        $registry->validateFormats('invoice', ['html', 'pdf']);

        static::assertTrue($registry->supports('invoice'));
    }

    public function testValidateFormatsThrowsForUnsupportedFormat(): void
    {
        $registry = new DocumentTypeRegistry(
            [new StaticDocumentType('invoice', ['html'])],
            $this->appDocumentTypeLoader([]),
        );

        $this->expectExceptionObject(DocumentV2Exception::unsupportedDocumentFormat('pdf', 'invoice'));

        $registry->validateFormats('invoice', ['pdf']);
    }

    public function testGetDocumentTypeReturnsCoreInstance(): void
    {
        $invoice = new StaticDocumentType('invoice', ['html']);

        $registry = new DocumentTypeRegistry([$invoice], $this->appDocumentTypeLoader([]));

        static::assertSame($invoice, $registry->getDocumentType('invoice'));
    }

    public function testGetDocumentTypeThrowsForAppTypeBecauseItHasNoInstance(): void
    {
        $registry = new DocumentTypeRegistry(
            [],
            $this->appDocumentTypeLoader(['swag_warranty' => ['html']]),
        );

        static::assertTrue($registry->supports('swag_warranty'));

        $this->expectExceptionObject(DocumentV2Exception::documentTypeNotFound('swag_warranty'));

        $registry->getDocumentType('swag_warranty');
    }

    public function testGetDocumentTypeThrowsForUnknownType(): void
    {
        $registry = new DocumentTypeRegistry(
            [new StaticDocumentType('invoice', ['html'])],
            $this->appDocumentTypeLoader([]),
        );

        $this->expectExceptionObject(DocumentV2Exception::documentTypeNotFound('does_not_exist'));

        $registry->getDocumentType('does_not_exist');
    }

    public function testAppTypesAreOnlyFetchedOnceUntilReset(): void
    {
        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->exactly(2))
            ->method('forActiveApps')
            ->with(AppDocumentTypeConfig::class)
            ->willReturn([$this->appFeature(new AppDocumentTypeConfig('swag_warranty', ['html'], [], []))]);

        $loader = new AppDocumentTypeLoader($storage);
        $registry = new DocumentTypeRegistry([new StaticDocumentType('invoice', ['html'])], $loader);

        static::assertSame(['invoice', 'swag_warranty'], $registry->getTechnicalNames());
        static::assertSame(['html'], $registry->getSupportedFormats('swag_warranty'));
        static::assertSame(['invoice', 'swag_warranty'], $registry->getTechnicalNames());

        $registry->reset();
        $loader->reset();

        static::assertSame(['invoice', 'swag_warranty'], $registry->getTechnicalNames());
    }

    /**
     * @param array<string, list<string>> $appTypes
     */
    private function appDocumentTypeLoader(array $appTypes): AppDocumentTypeLoader
    {
        $features = [];

        foreach ($appTypes as $identifier => $formats) {
            $features[] = $this->appFeature(new AppDocumentTypeConfig($identifier, $formats, [], []));
        }

        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn($features);

        return new AppDocumentTypeLoader($storage);
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
