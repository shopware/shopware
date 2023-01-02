<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppScriptConditionTranslation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AppScriptConditionTranslationEntity>
 * @package core
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
