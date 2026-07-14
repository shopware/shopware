<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentFormatValidator;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentRenderer;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentFormatValidator::class)]
class DocumentFormatValidatorTest extends TestCase
{
    public function testValidateAllowsSupportedFormats(): void
    {
        $this->expectNotToPerformAssertions();

        $validator = new DocumentFormatValidator(new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
            new StaticDocumentRenderer(DocumentFormat::PDF, [DocumentType::INVOICE->value]),
        ]));

        $validator->validate(DocumentType::INVOICE->value, [
            DocumentFormat::HTML->value,
            DocumentFormat::PDF->value,
        ]);
    }

    public function testValidateRejectsUnsupportedFormats(): void
    {
        $validator = new DocumentFormatValidator(new DocumentRendererRegistry([
            new StaticDocumentRenderer(DocumentFormat::HTML, [DocumentType::INVOICE->value]),
        ]));

        static::expectExceptionObject(
            DocumentV2Exception::unsupportedDocumentFormat(
                DocumentFormat::PDF->value,
                DocumentType::INVOICE->value,
            )
        );

        $validator->validate(DocumentType::INVOICE->value, [
            DocumentFormat::HTML->value,
            DocumentFormat::PDF->value,
        ]);
    }
}
