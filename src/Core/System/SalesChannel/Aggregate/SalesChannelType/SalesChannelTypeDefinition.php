<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ListField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SalesChannelTypeDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'sales_channel_type';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new TenantIdField(),
            new StringField('cover', 'cover'),
            new StringField('icon', 'icon'),
            new ListField('screenshots', 'screenshots', StringField::class),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new TranslatedField(new StringField('manufacturer', 'manufacturer')),
            new TranslatedField(new StringField('description', 'description')),
            new TranslatedField(new StringField('description_long', 'descriptionLong')),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslationsAssociationField('translations', SalesChannelTypeTranslationDefinition::class, 'sales_channel_type_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),

            new OneToManyAssociationField('salesChannels', SalesChannelDefinition::class, 'type_id', false, 'id'),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return SalesChannelTypeCollection::class;
    }

    public static function getStructClass(): string
    {
        return SalesChannelTypeStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return SalesChannelTypeTranslationDefinition::class;
    }
}
