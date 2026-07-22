<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Service;

use Shopware\Core\Content\ImportExportV2\Format\FormatRegistry;
use Shopware\Core\Content\ImportExportV2\Job\Mapping\ExportEntityMapper;
use Shopware\Core\Content\ImportExportV2\Job\Run\ImportExportV2RunEntity;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ExportRunProcessor
{
    public function __construct(
        private readonly FormatRegistry $formatRegistry,
        private readonly ExportEntityMapper $exportEntityMapper,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry
    ) {
    }

    /**
     * @param list<string> $recordIds
     */
    public function processNextChunk(
        ImportExportV2RunEntity $run,
        ImportExportV2ProfileEntity $profile,
        \Shopware\Core\Content\ImportExportV2\Job\Artifact\ImportExportV2ArtifactEntity $outputArtifact,
        array $recordIds,
        Context $context
    ): bool {
        $format = $this->formatRegistry->get($profile->getFormat());
        $repository = $this->definitionInstanceRegistry->getRepository($profile->getEntity());
        $run->setTotalRecords(\count($recordIds));

        $chunkIds = \array_slice($recordIds, $run->getOffset(), $run->getChunkSize());
        if ($chunkIds === []) {
            return false;
        }

        $criteria = new Criteria($chunkIds);
        // Only load associations that are needed by the selected profile so export stays profile-driven.
        $this->exportEntityMapper->enrichCriteria($profile, $criteria);
        $entities = $repository->search($criteria, $context)->getEntities();

        $records = [];
        foreach ($entities as $entity) {
            // Export uses the same record shape as import, then the chosen format turns it into the final file.
            $records[] = $this->exportEntityMapper->toImportExportRecord($entity, $profile);
        }

        $contents = $format->getExportWriter()->append($outputArtifact->getContents(), $records, $profile);
        $nextOffset = $run->getOffset() + \count($chunkIds);
        if ($nextOffset >= \count($recordIds)) {
            $contents = $format->getExportWriter()->finalize($contents, $profile);
        }

        $outputArtifact->setContents($contents);

        $run->addProcessed(\count($chunkIds));
        $run->addSucceeded(\count($records));
        $run->setOffset($nextOffset);

        return $nextOffset < \count($recordIds);
    }
}
