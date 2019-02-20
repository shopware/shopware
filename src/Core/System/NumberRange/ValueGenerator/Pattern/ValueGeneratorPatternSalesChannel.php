<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\System\NumberRange\NumberRangeEntity;

class ValueGeneratorPatternSalesChannel extends ValueGeneratorPattern
{
    protected const PATTERN_ID = 'saleschannel';

    public function resolve(NumberRangeEntity $configuration, CheckoutContext $checkoutContext, ?array $args = null): string
    {
        if (is_array($args) && isset($args[0]) && $args[0] === 'shortname') {
            return $checkoutContext->getSalesChannel()->getShortName() ?? '';
        }

        return $checkoutContext->getSalesChannel()->getId();
    }
}
