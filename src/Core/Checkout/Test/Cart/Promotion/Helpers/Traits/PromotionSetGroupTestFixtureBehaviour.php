<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Uuid\Uuid;

trait PromotionSetGroupTestFixtureBehaviour
{
    private function createSetGroup(string $packagerKey, float $value, string $sorterKey, array $rules): PromotionSetGroupEntity
    {
        $group = new PromotionSetGroupEntity();
        $group->setId(Uuid::randomBytes());

        $group->setPackagerKey($packagerKey);
        $group->setValue($value);
        $group->setSorterKey($sorterKey);
        $group->setSetGroupRules(new RuleCollection($rules));

        return $group;
    }
}
