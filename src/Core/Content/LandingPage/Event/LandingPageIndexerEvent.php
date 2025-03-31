<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class LandingPageIndexerEvent extends NestedEvent
{
    /**
     * @var array<string>
     */
    protected array $ids;

    protected Context $context;

    /**
     * @param array<string> $ids
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
     * @return array<string>
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
