<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;

class MapperFactory
{
    public function create(ImportExportLogEntity $logEntity): MapperInterface
    {
        $definitions = FieldDefinitionCollection::fromArray($logEntity->getProfile()->getMapping());
        if ($logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_EXPORT) {
            return new ExportMapper($definitions);
        }

        return new ImportMapper($definitions);
    }
}
