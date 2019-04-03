<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AttributesTestDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'attribute_test';
    }

    public static function isInheritanceAware(): bool
    {
        return true;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            (new IdField('parent_id', 'parentId'))->addFlags(new PrimaryKey()),
            new ParentFkField(self::class),
            (new StringField('name', 'name'))->setFlags(new Inherited()),

            (new TranslatedField('translatedAttributes'))->setFlags(new Inherited()),

            (new AttributesField())->setFlags(new Inherited()),

            new TranslationsAssociationField(AttributesTestTranslationDefinition::class, 'attribute_test_id'),

            //parent - child inheritance
            new ParentAssociationField(self::class, 'id', false),
            new ChildrenAssociationField(self::class),
        ]);
    }
}
