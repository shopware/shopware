<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
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
        ]);

        static::assertSame(['invoice', 'delivery_note'], $registry->getTechnicalNames());
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
        ]);

        static::assertSame(['html', 'pdf', 'zugferd_xml'], $registry->getSupportedFormats('delivery_note'));
    }

    public function testValidateFormatsPassesForSupported(): void
    {
        $registry = new DocumentTypeRegistry([new StaticDocumentType('invoice', ['html', 'pdf'])]);

        $registry->validateFormats('invoice', ['html', 'pdf']);

        static::assertTrue($registry->supports('invoice'));
    }

    public function testValidateFormatsThrowsForUnsupported(): void
    {
        $registry = new DocumentTypeRegistry([new StaticDocumentType('invoice', ['html'])]);

        $this->expectExceptionObject(DocumentV2Exception::unsupportedDocumentFormat('pdf', 'invoice'));

        $registry->validateFormats('invoice', ['pdf']);
    }

    public function testThrowsForNotExistingDocumentType(): void
    {
        $registry = new DocumentTypeRegistry([new StaticDocumentType('invoice', ['html'])]);

        static::assertSame('invoice', $registry->getDocumentType('invoice')->getTechnicalName());

        $this->expectExceptionObject(DocumentV2Exception::documentTypeNotFound('test'));
        $registry->getDocumentType('test');
    }
}
