<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('framework')]
class SalesChannelFileDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'sales_channel_file';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelFileCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalesChannelFileEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.12.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return SalesChannelDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware(AdminApiSource::class))->setDescription('Unique identity of the sales channel file configuration.'),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required(), new ApiAware(AdminApiSource::class))->setDescription('Unique identity of the configured sales channel.'),
            (new StringField('file_family', 'fileFamily', 64))->addFlags(new Required(), new ApiAware(AdminApiSource::class))->setDescription('File family below Resources/views/files.'),
            (new StringField('file_name', 'fileName', 512))->addFlags(new Required(), new ApiAware(AdminApiSource::class))->setDescription('Normalized public file path without a leading slash.'),
            (new BoolField('enabled', 'enabled'))->addFlags(new Required(), new ApiAware(AdminApiSource::class))->setDescription('Controls whether the file is served for this sales channel.'),
            (new JsonField('template_overrides', 'templateOverrides', [], []))->addFlags(new ApiAware(AdminApiSource::class))->setDescription('Twig template overrides keyed by Twig namespace.'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
