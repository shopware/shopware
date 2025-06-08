<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionEntity>
 */
#[Package('checkout')]
class PromotionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_collection';
    }

    protected function getExpectedClass(): string
    {
        return PromotionEntity::class;
    }
}
