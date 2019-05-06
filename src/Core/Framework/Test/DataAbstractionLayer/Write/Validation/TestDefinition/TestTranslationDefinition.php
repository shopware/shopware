<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Validation\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class TestTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return '_test_lock_translation';
    }

    protected function getParentDefinitionClass(): string
    {
        return TestDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('name', 'name'),
        ]);
    }
}
