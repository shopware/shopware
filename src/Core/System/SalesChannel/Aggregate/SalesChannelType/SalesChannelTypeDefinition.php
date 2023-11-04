<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('sales-channel')]
class SalesChannelTypeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'sales_channel_type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalesChannelTypeEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('cover_url', 'coverUrl'),
            new StringField('icon_name', 'iconName'),
            new ListField('screenshot_urls', 'screenshotUrls', StringField::class),
            new TranslatedField('name'),
            new TranslatedField('manufacturer'),
            new TranslatedField('description'),
            new TranslatedField('descriptionLong'),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(SalesChannelTypeTranslationDefinition::class, 'sales_channel_type_id'))->addFlags(new Required()),
            new OneToManyAssociationField('salesChannels', SalesChannelDefinition::class, 'type_id', 'id'),
        ]);
    }
}
