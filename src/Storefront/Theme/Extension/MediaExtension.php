<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Extension;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Storefront\Theme\ThemeDefinition;

class MediaExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('previewMedia', ThemeDefinition::class, 'preview_media_id')
        );
    }

    public function getDefinitionClass(): string
    {
        return ThemeDefinition::class;
    }
}
