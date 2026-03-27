<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductIndexerEvent extends NestedEvent implements ProductChangedEventInterface
{
    /**
     * @internal
     *
     * @param list<string> $ids
     * @param list<string> $skip
     */
    public function __construct(
        private readonly array $ids,
        private readonly Context $context,
        private readonly array $skip = []
    ) {
    }

    /**
     * @param list<string> $ids
     * @param list<string> $skip
     */
    public static function create(array $ids, Context $context, array $skip): self
    {
        return new self($ids, $context, $skip);
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

    /**
     * @return list<string>
     */
    public function getSkip(): array
    {
        return $this->skip;
    }
}
