<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
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

    public function create(Context $context, ImportExportLogEntity $logEntity): RecordIterator
    {
        $profile = $logEntity->getProfile();
        $entityDefinition = $this->definitionRegistry->getByEntityName($profile->getSourceEntity());
        $criteriaBuilder = new CriteriaBuilder(FieldDefinitionCollection::fromArray($profile->getMapping()), $entityDefinition);
        $entityRepository = $this->definitionRegistry->getRepository($profile->getSourceEntity());

        return new RepositoryIterator($entityRepository, $context, $criteriaBuilder, $this->readBufferSize);
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_EXPORT;
    }
}
