<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PromotionIndexerEvent extends NestedEvent
{
    /**
     * @param array<int, string> $ids
     * @param array<int, string> $skip
     */
    public function __construct(
        private readonly array $ids,
        private readonly Context $context,
        private readonly array $skip = []
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<int, string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * @return array<int, string>
     */
    public function getSkip(): array
    {
        return $this->skip;
    }
}
