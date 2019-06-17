<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

interface ErdDumper
{
    public function addTable(string $definition, string $entityName, string $description, bool $isTranslation): void;

    public function addField(string $definition, Field $field, string $type): void;

    public function dump(): string;

    public function addAssociation(string $definition, string $name, string $referenceDefinition, string $referenceName): void;
}
