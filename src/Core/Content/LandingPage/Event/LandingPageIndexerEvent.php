<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class LandingPageIndexerEvent extends NestedEvent
{
    /**
     * @param list<string> $ids
     * @param list<string> $skip
     */
    public function __construct(
        protected array $ids,
        protected Context $context,
        private readonly array $skip = [],
    ) {
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
     * @return list<string>
     */
    public function getSkip(): array
    {
        return $this->skip;
    }
}
