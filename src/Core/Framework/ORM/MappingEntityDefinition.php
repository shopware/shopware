<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM;

use Shopware\Core\Framework\ORM\Exception\MappingEntityRepositoryException;
use Shopware\Core\Framework\ORM\Exception\MappingEntityStructException;

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
