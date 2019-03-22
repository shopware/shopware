<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Framework\Struct\Struct;

class CartPromotionsFetchDefinition extends Struct
{
    /**
     * @var string[]
     */
    protected $lineItemIds;

    /**
     * @param string[] $lineItemIds
     */
    public function __construct(array $lineItemIds)
    {
        $this->lineItemIds = $lineItemIds;
    }

    /**
     * @return string[]
     */
    public function getLineItemIds(): array
    {
        return $this->lineItemIds;
    }
}
