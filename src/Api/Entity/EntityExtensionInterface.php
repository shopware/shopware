<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

interface EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection);

    public function getDefinitionClass(): string;
}
