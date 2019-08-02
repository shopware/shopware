<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Content\Rule\RuleCollection;

trait LineItemGroupTestFixtureBehaviour
{
    private function buildGroup(string $packagerKey, float $value, string $sorterKey, RuleCollection $rules): LineItemGroupDefinition
    {
        $group = new LineItemGroupDefinition(
            $packagerKey,
            $value,
            $sorterKey,
            $rules
        );

        return $group;
    }
}
