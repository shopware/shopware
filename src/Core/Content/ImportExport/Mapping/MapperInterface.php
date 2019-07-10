<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

interface MapperInterface
{
    public function map(array $data, FieldDefinitionCollection $fieldDefinitions, EntityDefinition $entityDefinition): array;

    public function supports(ImportExportLogEntity $logEntity): bool;
}
