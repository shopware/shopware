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
final readonly class DeliveryNoteDocumentType extends AbstractDocumentType
{
    public function getTechnicalName(): string
    {
        return DocumentType::DELIVERY_NOTE->value;
    }

    public function getSupportedFormats(): array
    {
        return [
            DocumentFormat::HTML->value,
            DocumentFormat::PDF->value,
        ];
    }
}
