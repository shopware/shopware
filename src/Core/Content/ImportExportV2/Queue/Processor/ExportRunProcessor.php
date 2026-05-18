<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Queue\Processor;

use Shopware\Core\Content\ImportExportV2\Event\ExportCriteriaBuiltEvent;
use Shopware\Core\Content\ImportExportV2\Event\ExportRecordConvertedEvent;
use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Format\FormatRegistry;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecordBuilder;
use Shopware\Core\Content\ImportExportV2\Run\ImportExportV2RunEntity;
use Shopware\Core\Content\ImportExportV2\Service\CriteriaFilterBuilder;
use Shopware\Core\Content\ImportExportV2\Service\ExportCriteriaEnricher;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ExportRunProcessor
{
    public function __construct(
        private readonly FormatRegistry             $formatRegistry,
        private readonly ExportCriteriaEnricher     $exportCriteriaEnricher,
        private readonly ImportExportRecordBuilder  $exportRecordBuilder,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly CriteriaFilterBuilder      $criteriaFilterBuilder,
        private readonly EventDispatcherInterface   $eventDispatcher
    ) {
    }

    /**
     * @return bool Returns true if there are more records to process, false otherwise
     */
    public function processNextChunk(
        ImportExportV2RunEntity $run,
        ImportExportV2ProfileEntity $profile,
        ImportExportV2FileEntity $file,
        Context $context
    ): bool {
        $format = $this->formatRegistry->get($profile->getFormat());

        $repository = $this->definitionInstanceRegistry->getRepository($profile->getEntity());

        $criteria = new Criteria();
        $criteria->setOffset($run->getOffset());
        $criteria->setLimit($run->getLimit());
        // TODO: we need to make sure we always have the same data in each chunk, even if the underlying data changes during export.
        // For now we just sort by id, but we might need to make this configurable or use a more stable sorting mechanism.
        $criteria->addSorting(new FieldSorting('id'));

        // Load associations that are needed by the selected profile
        $this->exportCriteriaEnricher->enrich($profile, $criteria);

        // Apply filters from the run
        $this->criteriaFilterBuilder->apply($criteria, $run->getExportFilters());

        // Allow extensions to modify the fully prepared export criteria
        $this->eventDispatcher->dispatch(new ExportCriteriaBuiltEvent($profile, $run, $criteria));

        $searchResult = $repository->search($criteria, $context);
        $run->setTotalRecords($searchResult->getTotal());

        $entities = $searchResult->getEntities();
        if ($entities->count() === 0) {
            return false;
        }

        $records = [];
        foreach ($entities as $entity) {
            $record = $this->exportRecordBuilder->build($entity, $profile);

            // Dispatch event so that the export record can be modified before it gets written to the file
            $this->eventDispatcher->dispatch(new ExportRecordConvertedEvent($profile, $entity, $record));

            $records[] = $record;
        }

        $format->getExportWriter()->append($profile, $file, $records);

        $processedCount = $entities->count();
        $nextOffset = $run->getOffset() + $processedCount;

        $run->addProcessed($processedCount);
        $run->addSucceeded(\count($records));
        $run->setOffset($nextOffset);

        return $nextOffset < $searchResult->getTotal();
    }
}
