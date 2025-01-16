<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionTranslationEntity>
 */
#[Package('checkout')]
class PromotionTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PromotionTranslationEntity::class;
    }
}
