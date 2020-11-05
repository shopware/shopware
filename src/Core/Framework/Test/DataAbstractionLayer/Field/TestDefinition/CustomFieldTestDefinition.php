<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomFieldTestDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'attribute_test';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function isInheritanceAware(): bool
    {
        return true;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new IdField('parent_id', 'parentId'))->addFlags(new PrimaryKey()),
            new ParentFkField(self::class),
            (new StringField('name', 'name'))->setFlags(new Inherited()),
            (new TranslatedField('customTranslated'))->setFlags(new Inherited()),
            (new CustomFields('custom', 'custom'))->setFlags(new Inherited()),
            new TranslationsAssociationField(CustomFieldTestTranslationDefinition::class, 'attribute_test_id'),
            //parent - child inheritance
            new ParentAssociationField(self::class, 'id'),
            new ChildrenAssociationField(self::class),
        ]);
    }
}
