<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\Struct\Struct;

class CartPromotionsDataDefinition extends Struct
{
    /**
     * @var PromotionEntity[]
     */
    protected $promotions;

    public function __construct(array $promotions)
    {
        $this->promotions = $promotions;
    }

    /**
     * @return PromotionEntity[]
     */
    public function getPromotions(): array
    {
        return $this->promotions;
    }
}
