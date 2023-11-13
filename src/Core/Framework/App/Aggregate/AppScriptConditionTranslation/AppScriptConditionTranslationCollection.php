<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppScriptConditionTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AppScriptConditionTranslationEntity>
 */
#[Package('core')]
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
