<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

interface InheritedDefinition
{
    public static function getParentPropertyName(): string;
}
