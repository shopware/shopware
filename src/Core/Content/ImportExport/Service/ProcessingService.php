<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Iterator\IteratorFactoryInterface;
use Shopware\Core\Content\ImportExport\Iterator\RecordIterator;
use Shopware\Core\Content\ImportExport\Mapping\MapperFactory;
use Shopware\Core\Content\ImportExport\Writer\WriterFactoryInterface;
use Shopware\Core\Content\ImportExport\Writer\WriterInterface;
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
     * @var WriterFactoryInterface[]
     */
    private $writerFactories;

    /**
     * @var IteratorFactoryInterface[]
     */
    private $iteratorFactories;

    /**
     * @var MapperFactory
     */
    private $mapperFactory;

    /**
     * @var int
     */
    private $writeBufferSize;

    public function __construct(
        EntityRepositoryInterface $logRepository,
        iterable $writerFactories,
        iterable $iteratorFactories,
        MapperFactory $mapperFactory,
        int $writeBufferSize
    ) {
        $this->logRepository = $logRepository;
        $this->writerFactories = $writerFactories;
        $this->iteratorFactories = $iteratorFactories;
        $this->mapperFactory = $mapperFactory;
        $this->writeBufferSize = $writeBufferSize;
    }

    public function findLog(Context $context, string $logId): ?ImportExportLogEntity
    {
        $result = $this->logRepository->search(new Criteria([$logId]), $context);

        return $result->getEntities()->get($logId);
    }

    public function createRecordIterator(Context $context, ImportExportLogEntity $logEntity): RecordIterator
    {
        foreach ($this->iteratorFactories as $iteratorFactory) {
            if ($iteratorFactory->supports($logEntity)) {
                return $iteratorFactory->create($context, $logEntity);
            }
        }

        throw new \RuntimeException('Cannot find supported iterator factory');
    }

    public function process(Context $context, ImportExportLogEntity $logEntity, \Iterator $iterator): int
    {
        $writer = $this->createWriter($context, $logEntity);
        $mapper = $this->mapperFactory->create($logEntity);

        $processed = 0;
        $lastIndex = -1;
        foreach ($iterator as $index => $record) {
            $writer->append($mapper->map($record), $index);
            if ($index % $this->writeBufferSize === 0) {
                $writer->flush();
            }
            ++$processed;
            $lastIndex = $index;
        }
        $writer->flush();

        if ($lastIndex >= 0 && ++$lastIndex >= $logEntity->getRecords()) {
            $writer->finish();
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

    private function createWriter(Context $context, ImportExportLogEntity $logEntity): WriterInterface
    {
        foreach ($this->writerFactories as $writerFactory) {
            if ($writerFactory->supports($logEntity)) {
                return $writerFactory->create($context, $logEntity);
            }
        }

        throw new \RuntimeException('Cannot find supported writer factory');
    }
}
