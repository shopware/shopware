<?php declare(strict_types=1);

namespace Shopware\Storefront\ContentSystem\Extension;

use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;

/**
 * @internal
 */
#[Package('framework')]
class ContentLayoutExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('headerContentLayouts', HeaderContentLayoutDefinition::class, 'content_layout_id', 'id')
        );
        $collection->add(
            new OneToManyAssociationField('footerContentLayouts', FooterContentLayoutDefinition::class, 'content_layout_id', 'id')
        );
    }

    public function getEntityName(): string
    {
        return ContentLayoutDefinition::ENTITY_NAME;
    }
}
