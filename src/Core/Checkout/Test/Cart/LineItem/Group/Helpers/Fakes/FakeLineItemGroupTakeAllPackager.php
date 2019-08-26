<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakeLineItemGroupTakeAllPackager implements LineItemGroupPackagerInterface
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var FakeSequenceSupervisor
     */
    private $sequenceSupervisor;

    /**
     * @var int
     */
    private $sequenceCount;

    public function __construct(string $key, FakeSequenceSupervisor $sequenceSupervisor)
    {
        $this->key = $key;
        $this->sequenceSupervisor = $sequenceSupervisor;
    }

    public function getSequenceCount(): int
    {
        return $this->sequenceCount;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function buildGroupPackage(float $value, LineItemCollection $sortedItems, SalesChannelContext $context): LineItemCollection
    {
        $this->sequenceCount = $this->sequenceSupervisor->getNextCount();

        return $sortedItems;
    }
}
