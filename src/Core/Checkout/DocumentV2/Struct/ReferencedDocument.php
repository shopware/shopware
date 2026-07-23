<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\Provider\OrderVersionStrategy;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * The document another document references, resolved by the generation pipeline.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class ReferencedDocument
{
    /**
     * @param OrderEntity|null $order loaded only for {@see OrderVersionStrategy::BOTH}
     */
    public function __construct(
        public string $id,
        public string $documentNumber,
        public string $orderVersionId,
        public ?OrderEntity $order = null,
    ) {
    }

    public function withOrder(OrderEntity $order): self
    {
        return new self(
            $this->id,
            $this->documentNumber,
            $this->orderVersionId,
            $order,
        );
    }
}
