<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Promotion\PromotionEntity;

class PromotionCodeTuple
{
    /**
     * @var string
     */
    private $code;

    /**
     * @var PromotionEntity
     */
    private $promotion;

    public function __construct(string $code, PromotionEntity $promotion)
    {
        $this->code = $code;
        $this->promotion = $promotion;
    }

    /**
     * Gets the code of the tuple.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Gets the promotion for this code tuple.
     */
    public function getPromotion(): PromotionEntity
    {
        return $this->promotion;
    }
}
