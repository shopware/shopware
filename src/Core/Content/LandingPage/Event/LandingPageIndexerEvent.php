<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class LandingPageIndexerEvent extends NestedEvent
{
    /**
     * @var string[]
     */
    protected array $ids;

    protected Context $context;

    /**
     * @param string[] $ids
     * @param string[] $skip
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
     * @return string[]
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
     * @return string[]
     */
    public function getSkip(): array
    {
        return $this->skip;
    }
}
