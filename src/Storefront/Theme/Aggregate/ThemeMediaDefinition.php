<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Aggregate;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ThemeDefinition;

#[Package('storefront')]
class ThemeMediaDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'theme_media';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('theme_id', 'themeId', ThemeDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('theme', 'theme_id', ThemeDefinition::class),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class),
        ]);
    }
}
