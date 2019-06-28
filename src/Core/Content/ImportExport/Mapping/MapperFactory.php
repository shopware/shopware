<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

class MapperFactory
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $entityDefinitionRegistry;

    public function __construct(DefinitionInstanceRegistry $entityDefinitionRegistry)
    {
        $this->entityDefinitionRegistry = $entityDefinitionRegistry;
    }

    public function create(ImportExportLogEntity $logEntity): MapperInterface
    {
        $definitions = FieldDefinitionCollection::fromArray($logEntity->getProfile()->getMapping());
        if ($logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_EXPORT) {
            return new ExportMapper($definitions);
        }

        return new ImportMapper($definitions, $this->entityDefinitionRegistry->getByEntityName($logEntity->getProfile()->getSourceEntity()));
    }
}
