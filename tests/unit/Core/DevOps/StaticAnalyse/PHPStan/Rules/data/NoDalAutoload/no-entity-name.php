<?php declare(strict_types=1);

namespace Shopware\Core\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

/**
 * @internal
 */
class Definition
{
    public function __construct()
    {
        new OneToOneAssociationField('prop', 'storageName', 'referenceField', 'referenceClass', true);
        new ManyToOneAssociationField('prop2', 'storageName', 'referenceClass', 'referenceField', true);
    }
}
