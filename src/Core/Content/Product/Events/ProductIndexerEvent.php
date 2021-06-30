<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductIndexerEvent extends NestedEvent implements ProductChangedEventInterface
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var array
     */
    private $ids;

    /**
     * @var array
     */
    private $childrenIds;

    /**
     * @var array
     */
    private $parentIds;

    private array $skip;

    public function __construct(array $ids, array $childrenIds, array $parentIds, Context $context, array $skip = [])
    {
        $this->context = $context;
        $this->ids = $ids;
        $this->childrenIds = $childrenIds;
        $this->parentIds = $parentIds;
        $this->skip = $skip;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getChildrenIds(): array
    {
        return $this->childrenIds;
    }

    public function getParentIds(): array
    {
        return $this->parentIds;
    }

    public function getSkip(): array
    {
        return $this->skip;
    }
}
