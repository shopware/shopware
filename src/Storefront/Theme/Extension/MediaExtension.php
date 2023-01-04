<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Extension;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Aggregate\ThemeMediaDefinition;
use Shopware\Storefront\Theme\ThemeDefinition;

#[Package('storefront')]
class MediaExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('themes', ThemeDefinition::class, 'preview_media_id')
        );

        $collection->add(
            new ManyToManyAssociationField('themeMedia', ThemeDefinition::class, ThemeMediaDefinition::class, 'media_id', 'theme_id')
        );
    }

    public function getDefinitionClass(): string
    {
        return MediaDefinition::class;
    }
}
