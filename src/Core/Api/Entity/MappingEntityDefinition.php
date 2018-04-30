<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

use Shopware\Api\Entity\Exception\MappingEntityRepositoryException;
use Shopware\Api\Entity\Exception\MappingEntityStructException;

abstract class MappingEntityDefinition extends EntityDefinition
{
    public static function getRepositoryClass(): string
    {
        throw new MappingEntityRepositoryException();
    }

    public static function getBasicCollectionClass(): string
    {
        throw new MappingEntityStructException();
    }

    public static function getBasicStructClass(): string
    {
        throw new MappingEntityStructException();
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
