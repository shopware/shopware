<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentDataProvider;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentDataProviderRegistry::class)]
class DocumentDataProviderRegistryTest extends TestCase
{
    public function testGetProvidersByDocumentType(): void
    {
        $registry = $this->createRegistry();

        $invoiceProviders = $registry->getByDocumentType(DocumentType::INVOICE->value);
        static::assertCount(2, $invoiceProviders);

        $cancellationInvoiceProviders = $registry->getByDocumentType(DocumentType::CANCELLATION_INVOICE->value);
        static::assertCount(1, $cancellationInvoiceProviders);

        $creditNoteProviders = $registry->getByDocumentType(DocumentType::CREDIT_NOTE->value);
        static::assertCount(0, $creditNoteProviders);
    }

    public function testGetProvidersByDocumentTypeThrowsOnDuplicateProviderKeys(): void
    {
        static::expectExceptionObject(
            DocumentV2Exception::duplicateProviderKey('duplicate', DocumentType::INVOICE->value)
        );

        new DocumentDataProviderRegistry([
            new StaticDocumentDataProvider([DocumentType::INVOICE->value], 'duplicate'),
            new StaticDocumentDataProvider([DocumentType::INVOICE->value], 'duplicate'),
        ]);
    }

    public function testGetProvidersByDocumentTypeAllowsSameKeyForDifferentDocumentTypes(): void
    {
        $registry = new DocumentDataProviderRegistry([
            new StaticDocumentDataProvider([DocumentType::INVOICE->value], 'duplicate'),
            new StaticDocumentDataProvider([DocumentType::CREDIT_NOTE->value], 'duplicate'),
        ]);

        static::assertCount(1, $registry->getByDocumentType(DocumentType::INVOICE->value));
        static::assertCount(1, $registry->getByDocumentType(DocumentType::CREDIT_NOTE->value));
    }

    public function testGetProvidersByDocumentTypeCanBeCalledMultipleTimesWithGeneratorInput(): void
    {
        $registry = new DocumentDataProviderRegistry($this->createProviderGenerator());

        static::assertCount(2, $registry->getByDocumentType(DocumentType::INVOICE->value));
        static::assertCount(2, $registry->getByDocumentType(DocumentType::INVOICE->value));
    }

    private function createRegistry(): DocumentDataProviderRegistry
    {
        return new DocumentDataProviderRegistry([
            new StaticDocumentDataProvider([
                DocumentType::INVOICE->value,
            ], 'invoice'),
            new StaticDocumentDataProvider([
                DocumentType::CANCELLATION_INVOICE->value,
                DocumentType::INVOICE->value,
            ], 'cancellation'),
        ]);
    }

    /**
     * @return \Generator<StaticDocumentDataProvider>
     */
    private function createProviderGenerator(): \Generator
    {
        yield new StaticDocumentDataProvider([
            DocumentType::INVOICE->value,
        ], 'invoice');

        yield new StaticDocumentDataProvider([
            DocumentType::CANCELLATION_INVOICE->value,
            DocumentType::INVOICE->value,
        ], 'cancellation');
    }
}
