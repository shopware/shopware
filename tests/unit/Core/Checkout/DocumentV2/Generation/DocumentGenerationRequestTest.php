<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentGenerationRequest::class)]
class DocumentGenerationRequestTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testWithDocumentNumber(): void
    {
        $request = new DocumentGenerationRequest(
            Uuid::randomHex(),
            Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::HTML],
            documentDate: '2026-05-05T12:00:00+00:00',
        );

        static::assertNull($request->documentNumber);

        $request = $request->withDocumentNumber('12345');

        static::assertSame('12345', $request->documentNumber);
        static::assertSame('2026-05-05T12:00:00+00:00', $request->documentDate);
    }

    public function testDocumentDateDefaultsToClockNow(): void
    {
        $clock = self::mockTime('2026-05-18 10:00:00');

        $request = new DocumentGenerationRequest(
            Uuid::randomHex(),
            Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::HTML],
        );

        static::assertSame(
            $clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $request->documentDate
        );
    }

    public function testExplicitDocumentDateIsPreserved(): void
    {
        $request = new DocumentGenerationRequest(
            Uuid::randomHex(),
            Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::HTML],
            documentDate: '2026-05-05T12:00:00+00:00',
        );

        static::assertSame('2026-05-05T12:00:00+00:00', $request->documentDate);
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
