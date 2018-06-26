<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo;

use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\Extension;
use Shopware\Core\System\Touchpoint\TouchpointDefinition;

class TouchpointExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'touchpoint_id', false, 'id'))
                ->setFlags(new Extension())
        );
    }

    public function getDefinitionClass(): string
    {
        return TouchpointDefinition::class;
    }
}
