<?php declare(strict_types=1);

namespace Shopware\Core\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

/**
 * @internal
 */
class Definition
{
    public const ENTITY_NAME = 'my-entity';

    public function __construct()
    {
        new OneToOneAssociationField('prop', 'storageName', 'referenceField', 'referenceClass', false);
        new ManyToOneAssociationField('prop', 'storageName', 'referenceClass', 'referenceField', false);
    }
}
