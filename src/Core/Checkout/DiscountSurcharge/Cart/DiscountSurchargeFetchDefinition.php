<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Cart;

use Shopware\Core\Framework\Struct\Struct;

class DiscountSurchargeFetchDefinition extends Struct
{
    /**
     * @var string[]
     */
    protected $ruleIds;

    /**
     * @param string[] $ruleIds
     */
    public function __construct(array $ruleIds)
    {
        $this->ruleIds = $ruleIds;
    }

    /**
     * @return string[]
     */
    public function getRuleIds(): array
    {
        return $this->ruleIds;
    }
}
