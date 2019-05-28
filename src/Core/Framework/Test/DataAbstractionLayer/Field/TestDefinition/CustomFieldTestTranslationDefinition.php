<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomFieldTestTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'attribute_test_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function getParentDefinitionClass(): string
    {
        return CustomFieldTestDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new CustomFields('custom_translated', 'customTranslated'),
        ]);
    }
}
