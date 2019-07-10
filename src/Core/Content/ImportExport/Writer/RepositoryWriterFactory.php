<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

class RepositoryWriterFactory implements WriterFactoryInterface
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function __construct(DefinitionInstanceRegistry $definitionRegistry)
    {
        $this->definitionRegistry = $definitionRegistry;
    }

    public function create(Context $context, ImportExportLogEntity $logEntity): WriterInterface
    {
        $entityRepository = $this->definitionRegistry->getRepository($logEntity->getProfile()->getSourceEntity());

        return new RepositoryWriter($entityRepository, $context);
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_IMPORT;
    }
}
