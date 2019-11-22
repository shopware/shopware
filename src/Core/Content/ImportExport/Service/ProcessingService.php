<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\ImportExport\Iterator\IteratorFactoryInterface;
use Shopware\Core\Content\ImportExport\Iterator\RecordIterator;
use Shopware\Core\Content\ImportExport\Mapping\FieldDefinitionCollection;
use Shopware\Core\Content\ImportExport\Mapping\MapperInterface;
use Shopware\Core\Content\ImportExport\Writer\WriterFactoryInterface;
use Shopware\Core\Content\ImportExport\Writer\WriterInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\ShopwareHttpException;

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
     * @var MapperInterface[]
     */
    private $mappers;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $entityDefinitionRegistry;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $writeBufferSize;

    public function __construct(
        EntityRepositoryInterface $logRepository,
        iterable $writerFactories,
        iterable $iteratorFactories,
        iterable $mappers,
        DefinitionInstanceRegistry $entityDefinitionRegistry,
        LoggerInterface $logger,
        int $writeBufferSize
    ) {
        $this->logRepository = $logRepository;
        $this->writerFactories = $writerFactories;
        $this->iteratorFactories = $iteratorFactories;
        $this->mappers = $mappers;
        $this->entityDefinitionRegistry = $entityDefinitionRegistry;
        $this->logger = $logger;
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
            if ($iteratorFactory->supports($logEntity->getActivity(), $logEntity->getProfile())) {
                return $iteratorFactory->create(
                    $context,
                    $logEntity->getActivity(),
                    $logEntity->getProfile(),
                    $logEntity->getFile()
                );
            }
        }

        throw new ProcessingException(
            'Cannot find supported factory to build instance of {{ type }}',
            ['type' => RecordIterator::class]
        );
    }

    public function process(Context $context, ImportExportLogEntity $logEntity, \Iterator $iterator): int
    {
        $writer = $this->createWriter($context, $logEntity);
        $mapper = $this->getMapper($logEntity);
        $fieldDefinitions = FieldDefinitionCollection::fromArray($logEntity->getProfile()->getMapping());
        $entityDefinition = $this->entityDefinitionRegistry->getByEntityName($logEntity->getProfile()->getSourceEntity());

        $processed = 0;
        $lastIndex = -1;

        try {
            foreach ($iterator as $index => $record) {
                $writer->append($mapper->map($record, $fieldDefinitions, $entityDefinition), $index);
                ++$processed;
                $lastIndex = $index;
                if ($processed % $this->writeBufferSize === 0) {
                    $writer->flush();
                }
            }
            $writer->flush();

            ++$lastIndex;
            if ($lastIndex >= $logEntity->getRecords()) {
                $writer->finish();
                $this->updateState($context, $logEntity->getId(), ImportExportLogEntity::STATE_SUCCEEDED);
            }
        } catch (\Exception $exception) {
            $meta = [
                'logId' => $logEntity->getId(),
            ];

            if ($exception instanceof ShopwareHttpException) {
                $meta['errors'] = iterator_to_array($exception->getErrors());
            } else {
                $meta['exception'] = $exception;
            }

            $this->logger->error('Failed to process import/export', $meta);
            $this->updateState($context, $logEntity->getId(), ImportExportLogEntity::STATE_FAILED);

            throw $exception;
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
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($logData): void {
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

        throw new ProcessingException(
            'Cannot find supported factory to build instance of {{ type }}',
            ['type' => WriterInterface::class]
        );
    }

    private function getMapper(ImportExportLogEntity $logEntity): MapperInterface
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->supports($logEntity)) {
                return $mapper;
            }
        }

        throw new ProcessingException(
            'Cannot find supported instance of {{ type }}',
            ['type' => MapperInterface::class]
        );
    }
}
