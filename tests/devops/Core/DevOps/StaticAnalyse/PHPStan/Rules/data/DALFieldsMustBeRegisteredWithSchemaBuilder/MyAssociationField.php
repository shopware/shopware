<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;

/**
 * @internal
 */
class MyAssociationField extends AssociationField
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
