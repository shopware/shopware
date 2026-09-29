<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Type;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class InvoiceDocumentType extends AbstractDocumentType
{
    public function getTechnicalName(): string
    {
        return DocumentType::INVOICE->value;
    }

    public function getSupportedFormats(): array
    {
        return [
            DocumentFormat::HTML->value,
            DocumentFormat::PDF->value,
            DocumentFormat::ZUGFERD_XML->value,
            DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
        ];
    }
}
