<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

class LineItemGroupResult
{
    /**
     * @var LineItemGroupDefinition
     */
    private $groupDefinition;

    /**
     * @var LineItemCollection
     */
    private $lineItems;

    public function __construct(LineItemGroupDefinition $groupDefinition, LineItemCollection $lineItems)
    {
        $this->groupDefinition = $groupDefinition;
        $this->lineItems = $lineItems;
    }

    public function getGroupDefinition(): LineItemGroupDefinition
    {
        return $this->groupDefinition;
    }

    public function getLineItems(): LineItemCollection
    {
        return $this->lineItems;
    }
}
