<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\FlowActionTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                       add(AppFlowActionTranslationEntity $entity)
 * @method void                                       set(string $key, AppFlowActionTranslationEntity $entity)
 * @method \Generator<AppFlowActionTranslationEntity> getIterator()
 * @method array<AppFlowActionTranslationEntity>      getElements()
 * @method AppFlowActionTranslationEntity|null        get(string $key)
 * @method AppFlowActionTranslationEntity|null        first()
 * @method AppFlowActionTranslationEntity|null        last()
 */
class AppFlowActionTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppFlowActionTranslationEntity::class;
    }
}
