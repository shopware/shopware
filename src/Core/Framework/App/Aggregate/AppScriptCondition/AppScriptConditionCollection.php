<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppScriptCondition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AppScriptConditionEntity>
 */
#[Package('core')]
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
