<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * @internal
 */
#[Package('TODO')]
class DocumentGenerationContext
{
    public function __construct(
        public readonly OrderEntity $order,
        public readonly string $documentType,
        public readonly DocumentConfig $documentConfig,
    ) {
    }
}
