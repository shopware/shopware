<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentGenerationRequest::class)]
class DocumentGenerationRequestTest extends TestCase
{
    public function testBuildMethods(): void
    {
        $request = new DocumentGenerationRequest(
            Uuid::randomHex(),
            Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::HTML],
            Context::createDefaultContext(),
        );

        static::assertNull($request->documentNumber);
        static::assertNull($request->languageAwareContext);

        $langContext = Context::createDefaultContext();
        $langContext->assign(['languageIdChain' => [Uuid::randomHex()]]);

        $request = $request->withDocumentNumber('12345');
        $request = $request->withLanguageAwareContext($langContext);

        static::assertSame('12345', $request->documentNumber);
        static::assertSame($langContext, $request->languageAwareContext);
    }

    public function testNormalization(): void
    {
        $request = new DocumentGenerationRequest(
            Uuid::randomHex(),
            Uuid::randomHex(),
            DocumentType::INVOICE,
            [DocumentFormat::HTML],
            Context::createDefaultContext(),
        );

        static::assertSame([DocumentFormat::HTML->value], $request->requestedFormats);
        static::assertSame(DocumentType::INVOICE->value, $request->documentType);

        $request = new DocumentGenerationRequest(
            Uuid::randomHex(),
            Uuid::randomHex(),
            DocumentType::INVOICE->value,
            [DocumentFormat::HTML->value],
            Context::createDefaultContext(),
        );

        static::assertSame([DocumentFormat::HTML->value], $request->requestedFormats);
        static::assertSame(DocumentType::INVOICE->value, $request->documentType);
    }
}
