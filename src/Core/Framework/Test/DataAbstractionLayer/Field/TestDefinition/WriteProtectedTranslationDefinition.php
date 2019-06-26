<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class WriteProtectedTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = '_test_nullable_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function getParentDefinitionClass(): string
    {
        return WriteProtectedTranslatedDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('protected', 'protected'),
            new StringField('system_protected', 'systemProtected'),
        ]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
