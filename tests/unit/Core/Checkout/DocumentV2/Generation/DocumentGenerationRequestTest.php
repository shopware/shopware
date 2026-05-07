<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentGenerationRequest::class)]
class DocumentGenerationRequestTest extends TestCase
{
    public function testWithDocumentNumber(): void
    {
        $request = new DocumentGenerationRequest(
            Uuid::randomHex(),
            Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::HTML],
        );

        static::assertNull($request->documentNumber);

        $request = $request->withDocumentNumber('12345');

        static::assertSame('12345', $request->documentNumber);
    }

    public function testNormalization(): void
    {
        $request = new DocumentGenerationRequest(
            Uuid::randomHex(),
            Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::HTML],
        );

        static::assertSame([DocumentFormat::HTML->value], $request->requestedFormats);
        static::assertSame(DocumentType::INVOICE->value, $request->documentType);

        $request = new DocumentGenerationRequest(
            Uuid::randomHex(),
            Uuid::randomHex(),
            DocumentType::INVOICE->value,
            [DocumentFormat::HTML->value],
        );

        static::assertSame([DocumentFormat::HTML->value], $request->requestedFormats);
        static::assertSame(DocumentType::INVOICE->value, $request->documentType);
    }
}
