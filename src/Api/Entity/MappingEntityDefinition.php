<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

abstract class MappingEntityDefinition extends EntityDefinition
{
    public static function getRepositoryClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own repositories');
    }

    public static function getBasicCollectionClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own collection classes');
    }

    public static function getBasicStructClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own struct classes');
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
