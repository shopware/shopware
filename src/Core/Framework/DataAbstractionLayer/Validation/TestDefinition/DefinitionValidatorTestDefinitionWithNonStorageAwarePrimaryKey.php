<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 * Test fixture with a non-StorageAware field incorrectly marked as PrimaryKey
 * Used to test that the validator skips non-StorageAware fields when checking primary keys
 */
#[Package('framework')]
class DefinitionValidatorTestDefinitionWithNonStorageAwarePrimaryKey extends DefinitionValidatorTestDefinition
{
    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        // Add a TranslatedField (which is not StorageAware) and mark it as PrimaryKey
        // This is an unusual/incorrect configuration, but we need to test the handling
        $translatedField = new TranslatedField('translated');
        $translatedField->addFlags(new PrimaryKey());
        $fields->add($translatedField);

        return $fields;
    }
}
