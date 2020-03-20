<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                            add(PromotionTranslationEntity $entity)
 * @method void                            set(string $key, PromotionTranslationEntity $entity)
 * @method PromotionTranslationEntity[]    getIterator()
 * @method PromotionTranslationEntity[]    getElements()
 * @method PromotionTranslationEntity|null get(string $key)
 * @method PromotionTranslationEntity|null first()
 * @method PromotionTranslationEntity|null last()
 */
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
