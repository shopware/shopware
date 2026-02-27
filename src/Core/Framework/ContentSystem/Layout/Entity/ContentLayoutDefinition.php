<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Entity;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\FooterContentLayout\FooterContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\HeaderContentLayout\HeaderContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementListField;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentLayoutDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'content_layout';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ContentLayoutCollection::class;
    }

    public function since(): string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new StringField('name', 'name', 255))->addFlags(new ApiAware(), new Required()),
            (new StringField('version', 'version', 20))->addFlags(new ApiAware(), new Required()),
            (new ContentElementListField('layout', 'layout'))->addFlags(new ApiAware(), new Required()),
            (new JsonField('schema', 'schema'))->addFlags(new ApiAware()),

            new OneToManyAssociationField('productContentLayouts', ProductContentLayoutDefinition::class, 'content_layout_id', 'id'),
            new OneToManyAssociationField('categoryContentLayouts', CategoryContentLayoutDefinition::class, 'content_layout_id', 'id'),
            new OneToManyAssociationField('landingPageContentLayouts', LandingPageContentLayoutDefinition::class, 'content_layout_id', 'id'),
            new OneToManyAssociationField('headerContentLayouts', HeaderContentLayoutDefinition::class, 'content_layout_id', 'id'),
            new OneToManyAssociationField('footerContentLayouts', FooterContentLayoutDefinition::class, 'content_layout_id', 'id'),
        ]);
    }
}
