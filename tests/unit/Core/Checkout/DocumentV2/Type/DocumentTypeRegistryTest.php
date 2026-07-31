<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Type;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Type\AppDocumentTypeLoader;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentType;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentTypeRegistry::class)]
class DocumentTypeRegistryTest extends TestCase
{
    public function testGetDocumentTypesAndFormats(): void
    {
        $registry = new DocumentTypeRegistry([
            new StaticDocumentType('invoice', ['html', 'pdf']),
            new StaticDocumentType('delivery_note', ['html']),
        ], $this->appDocumentTypeLoader());

        static::assertSame(['invoice', 'delivery_note'], $registry->getDocumentTypes());
        static::assertSame(['html', 'pdf'], $registry->getSupportedFormats('invoice'));
        static::assertTrue($registry->supports('invoice'));
        static::assertFalse($registry->supports('credit_note'));
        static::assertSame([], $registry->getSupportedFormats('credit_note'));
    }

    public function testFormatsAreUnionMergedAcrossDefinitions(): void
    {
        $registry = new DocumentTypeRegistry([
            new StaticDocumentType('delivery_note', ['html', 'pdf']),
            new StaticDocumentType('delivery_note', ['pdf', 'zugferd_xml']),
        ], $this->appDocumentTypeLoader());

        static::assertSame(['html', 'pdf', 'zugferd_xml'], $registry->getSupportedFormats('delivery_note'));
    }

    public function testValidateFormatsPassesForSupported(): void
    {
        $registry = new DocumentTypeRegistry([new StaticDocumentType('invoice', ['html', 'pdf'])], $this->appDocumentTypeLoader());

        $registry->validateFormats('invoice', ['html', 'pdf']);

        static::assertTrue($registry->supports('invoice'));
    }

    public function testValidateFormatsThrowsForUnsupported(): void
    {
        $registry = new DocumentTypeRegistry([new StaticDocumentType('invoice', ['html'])], $this->appDocumentTypeLoader());

        $this->expectExceptionObject(DocumentV2Exception::unsupportedDocumentFormat('pdf', 'invoice'));

        $registry->validateFormats('invoice', ['pdf']);
    }

    public function testAppRegisteredTypeAppearsInRegistry(): void
    {
        $registry = new DocumentTypeRegistry(
            [new StaticDocumentType('invoice', ['html', 'pdf'])],
            $this->appDocumentTypeLoader(['swag_certificate' => ['html', 'pdf']]),
        );

        static::assertSame(['invoice', 'swag_certificate'], $registry->getDocumentTypes());
        static::assertSame(['html', 'pdf'], $registry->getSupportedFormats('swag_certificate'));
        static::assertTrue($registry->supports('swag_certificate'));
    }

    public function testAppRegisteredTypeDoesNotOverrideCoreTypeOfSameName(): void
    {
        $registry = new DocumentTypeRegistry(
            [new StaticDocumentType('invoice', ['html', 'pdf'])],
            $this->appDocumentTypeLoader(['invoice' => ['pdf']]),
        );

        static::assertSame(['html', 'pdf'], $registry->getSupportedFormats('invoice'));
    }

    public function testResetInvalidatesMemoizedMergeSoUpdatedAppTypesAreReflected(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            $this->appTypeRows(['swag_certificate' => ['html']]),
            $this->appTypeRows(['swag_warranty' => ['pdf']]),
        );

        $loader = new AppDocumentTypeLoader($connection);
        $registry = new DocumentTypeRegistry([new StaticDocumentType('invoice', ['html', 'pdf'])], $loader);

        static::assertSame(['invoice', 'swag_certificate'], $registry->getDocumentTypes());
        // Memoized: a second read before reset must not requery, and must still reflect the old app type.
        static::assertSame(['invoice', 'swag_certificate'], $registry->getDocumentTypes());

        $registry->reset();
        $loader->reset();

        static::assertSame(['invoice', 'swag_warranty'], $registry->getDocumentTypes());
    }

    /**
     * @param array<string, list<string>> $appTypes
     */
    private function appDocumentTypeLoader(array $appTypes = []): AppDocumentTypeLoader
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($this->appTypeRows($appTypes));

        return new AppDocumentTypeLoader($connection);
    }

    /**
     * @param array<string, list<string>> $appTypes
     *
     * @return list<array{technical_name: string, formats: string, config: null}>
     */
    private function appTypeRows(array $appTypes): array
    {
        return array_map(
            static fn (string $identifier, array $formats): array => [
                'technical_name' => $identifier,
                'formats' => (string) json_encode($formats, \JSON_THROW_ON_ERROR),
                'config' => null,
            ],
            array_keys($appTypes),
            array_values($appTypes),
        );
    }
}
