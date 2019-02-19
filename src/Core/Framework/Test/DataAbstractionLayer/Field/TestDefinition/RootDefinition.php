<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class RootDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'root';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new StringField('name', 'name'),
            new OneToOneAssociationField('sub', 'id', 'root_id', SubDefinition::class, true),
        ]);
    }
}

class SubDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'root_sub';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new StringField('name', 'name'),
            new IntField('stock', 'stock'),
            (new FkField('root_id', 'rootId', RootDefinition::class, 'id'))->addFlags(new Required()),
            (new ReferenceVersionField(RootDefinition::class))->addFlags(new Required()),
            new OneToOneAssociationField('root', 'root_id', 'id', RootDefinition::class, false),
        ]);
    }
}
