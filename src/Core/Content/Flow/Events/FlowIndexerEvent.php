<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
class FlowIndexerEvent extends NestedEvent
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        private readonly array $ids,
        private readonly Context $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
