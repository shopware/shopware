<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax\Struct;

interface TaxRuleInterface extends \JsonSerializable
{
    public function getTaxRate(): float;
}
