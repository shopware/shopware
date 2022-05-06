<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppScriptConditionTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                     add(AppScriptConditionTranslationEntity $entity)
 * @method void                                     set(string $key, AppScriptConditionTranslationEntity $entity)
 * @method AppScriptConditionTranslationEntity[]    getIterator()
 * @method AppScriptConditionTranslationEntity[]    getElements()
 * @method AppScriptConditionTranslationEntity|null get(string $key)
 * @method AppScriptConditionTranslationEntity|null first()
 * @method AppScriptConditionTranslationEntity|null last()
 */
class AppScriptConditionTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_script_condition_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppScriptConditionTranslationEntity::class;
    }
}
