<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Mapping\CriteriaBuilder;
use Shopware\Core\Content\ImportExport\Mapping\FieldDefinitionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

class RepositoryIteratorFactory implements IteratorFactoryInterface
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var int
     */
    private $readBufferSize;

    public function __construct(DefinitionInstanceRegistry $definitionRegistry, int $readBufferSize)
    {
        $this->definitionRegistry = $definitionRegistry;
        $this->readBufferSize = $readBufferSize;
    }

    public function create(Context $context, string $activity, ImportExportProfileEntity $profileEntity, ImportExportFileEntity $fileEntity): RecordIterator
    {
        $entityDefinition = $this->definitionRegistry->getByEntityName($profileEntity->getSourceEntity());
        $criteriaBuilder = new CriteriaBuilder(FieldDefinitionCollection::fromArray($profileEntity->getMapping()), $entityDefinition);
        $entityRepository = $this->definitionRegistry->getRepository($profileEntity->getSourceEntity());

        return new RepositoryIterator($entityRepository, $context, $criteriaBuilder, $this->readBufferSize);
    }

    public function supports(string $activity, ImportExportProfileEntity $profileEntity): bool
    {
        return $activity === ImportExportLogEntity::ACTIVITY_EXPORT;
    }
}
