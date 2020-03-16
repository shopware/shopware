<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class CategoryIndexerEvent extends NestedEvent
{
    /**
     * @var array
     */
    protected $ids;

    /**
     * @var Context
     */
    protected $context;

    public function __construct(array $ids, Context $context)
    {
        $this->ids = $ids;
        $this->context = $context;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
