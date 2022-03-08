<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Feature;

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

    /**
     * @deprecated tag:v6.5.0 - `$parentIds` and `$childrenIds` will be removed, for be compatible right now, use ::create method
     */
    public function __construct(array $ids, array $childrenIds, array $parentIds, Context $context, array $skip = [])
    {
        $this->context = $context;
        $this->ids = $ids;

        if (!Feature::isActive('v6.5.0.0')) {
            $this->childrenIds = $childrenIds;
            $this->parentIds = $parentIds;
        }

        $this->skip = $skip;
    }

    public static function create($ids, Context $context, array $skip): self
    {
        // @deprecated tag:v6.5.0 - `$parentIds` and `$childrenIds` will be removed, remove parameters
        return new self($ids, [], [], $context, $skip);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * @deprecated tag:v6.5.0 - `$parentIds` and `$childrenIds` will be removed. The children and parents are no longer indexed at the same time
     */
    public function getChildrenIds(): array
    {
        Feature::throwException('v6.5.0.0', '`$parentIds` and `$childrenIds` will be removed. The children and parents are no longer indexed at the same time');
        return $this->childrenIds;
    }

    /**
     * @deprecated tag:v6.5.0 - `$parentIds` and `$childrenIds` will be removed. The children and parents are no longer indexed at the same time
     */
    public function getParentIds(): array
    {
        Feature::throwException('v6.5.0.0', '`$parentIds` and `$childrenIds` will be removed. The children and parents are no longer indexed at the same time');
        return $this->parentIds;
    }

    public function getSkip(): array
    {
        return $this->skip;
    }
}
