<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class CategoryIndexerEvent extends NestedEvent
{
    /**
     * @var list<string>
     */
    protected $ids;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @param list<string> $ids
     * @param array<string> $skip
     */
    public function __construct(
        array $ids,
        Context $context,
        private readonly array $skip = []
    ) {
        $this->ids = $ids;
        $this->context = $context;
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string>
     */
    public function getSkip(): array
    {
        return $this->skip;
    }
}
