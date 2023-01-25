<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Aggregate;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ThemeDefinition;

#[Package('storefront')]
class ThemeChildDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'theme_child';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.4.8.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('parent_id', 'parentId', ThemeDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('child_id', 'childId', ThemeDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('parentTheme', 'parent_id', ThemeDefinition::class),
            new ManyToOneAssociationField('childTheme', 'child_id', ThemeDefinition::class),
        ]);
    }
}
