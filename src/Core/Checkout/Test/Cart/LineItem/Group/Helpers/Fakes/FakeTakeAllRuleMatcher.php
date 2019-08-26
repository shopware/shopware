<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupRuleMatcherInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakeTakeAllRuleMatcher implements LineItemGroupRuleMatcherInterface
{
    /**
     * @var FakeSequenceSupervisor
     */
    private $sequenceSupervisor;

    /**
     * @var int
     */
    private $sequenceCount;

    public function __construct(FakeSequenceSupervisor $sequenceSupervisor)
    {
        $this->sequenceSupervisor = $sequenceSupervisor;
    }

    public function getSequenceCount(): int
    {
        return $this->sequenceCount;
    }

    public function getMatchingItems(LineItemGroupDefinition $group, LineItemCollection $items, SalesChannelContext $context): LineItemCollection
    {
        $this->sequenceCount = $this->sequenceSupervisor->getNextCount();

        return $items;
    }
}
