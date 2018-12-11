<?php declare(strict_types=1);

namespace Shopware\Storefront\Seo\Entity\FieldHandler;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;
use Shopware\Storefront\Seo\Entity\Field\CanonicalUrlAssociationField;

class CanonicalUrlFieldSerializer extends ManyToOneAssociationFieldSerializer
{
    public function getFieldClass(): string
    {
        return CanonicalUrlAssociationField::class;
    }
}
