<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppScriptCondition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                          add(AppScriptConditionEntity $entity)
 * @method void                          set(string $key, AppScriptConditionEntity $entity)
 * @method AppScriptConditionEntity[]    getIterator()
 * @method AppScriptConditionEntity[]    getElements()
 * @method AppScriptConditionEntity|null get(string $key)
 * @method AppScriptConditionEntity|null first()
 * @method AppScriptConditionEntity|null last()
 */
class AppScriptConditionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_script_condition_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppScriptConditionEntity::class;
    }
}
