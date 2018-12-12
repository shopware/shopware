<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\MappingEntityStructException;

abstract class MappingEntityDefinition extends EntityDefinition
{
    public static function getCollectionClass(): string
    {
        throw new MappingEntityStructException();
    }

    public static function getEntityClass(): string
    {
        throw new MappingEntityStructException();
    }
}
