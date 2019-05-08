<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Iterator\IteratorFactory;
use Shopware\Core\Content\ImportExport\Iterator\RecordIterator;
use Shopware\Core\Content\ImportExport\Mapping\MapperFactory;
use Shopware\Core\Content\ImportExport\Writer\WriterFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ProcessingService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $logRepository;

    /**
     * @var WriterFactory
     */
    private $writerFactory;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var MapperFactory
     */
    private $mapperFactory;

    public function __construct(
        EntityRepositoryInterface $logRepository,
        WriterFactory $writerFactory,
        IteratorFactory $iteratorFactory,
        MapperFactory $mapperFactory
    ) {
        $this->logRepository = $logRepository;
        $this->writerFactory = $writerFactory;
        $this->iteratorFactory = $iteratorFactory;
        $this->mapperFactory = $mapperFactory;
    }

    public function findLog(Context $context, string $logId): ?ImportExportLogEntity
    {
        $result = $this->logRepository->search(new Criteria([$logId]), $context);

        return $result->getEntities()->get($logId);
    }

    public function createRecordIterator(Context $context, string $activity, ImportExportFileEntity $fileEntity, ImportExportProfileEntity $profileEntity): RecordIterator
    {
        return $this->iteratorFactory->create($context, $activity, $fileEntity, $profileEntity);
    }

    public function process(Context $context, ImportExportLogEntity $logEntity, \Iterator $iterator): int
    {
        $writer = $this->writerFactory->create($logEntity, $context);
        $mapper = $this->mapperFactory->create($logEntity);

        $processed = 0;
        $lastIndex = 0;
        foreach ($iterator as $index => $record) {
            $writer->append($mapper->map($record));
            if ($index % 100 === 0) {
                $writer->flush();
            }
            ++$processed;
            $lastIndex = $index;
        }
        $writer->flush();

        if ($lastIndex > 0 && ++$lastIndex >= $logEntity->getRecords()) {
            $this->updateState($context, $logEntity->getId(), ImportExportLogEntity::STATE_SUCCEEDED);
        }

        return $processed;
    }

    public function cancel(Context $context, string $logId): void
    {
        $this->updateState($context, $logId, ImportExportLogEntity::STATE_ABORTED);
    }

    private function updateState(Context $context, string $logId, string $newState): void
    {
        $logData = [
            'id' => $logId,
            'state' => $newState,
        ];
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($logData) {
            $this->logRepository->update([$logData], $context);
        });
    }
}
