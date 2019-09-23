<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;

class FakeLineItemGroupSorter implements LineItemGroupSorterInterface
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

    public function sort(LineItemFlatCollection $items): LineItemFlatCollection
    {
        $this->sequenceCount = $this->sequenceSupervisor->getNextCount();

        return $items;
    }
}
