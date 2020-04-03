<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipeFactory;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReaderFactory;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriterFactory;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @experimental We might break this with v6.2
 */
class ImportExportFactory
{
    /**
     * @var AbstractReaderFactory[]
     */
    private $readerFactories;

    /**
     * @var AbstractWriterFactory[]
     */
    private $writerFactories;

    /**
     * @var AbstractPipeFactory[]
     */
    private $pipeFactories;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionInstanceRegistry;

    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var ImportExportService
     */
    private $importExportService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $logRepository;

    public function __construct(
        ImportExportService $importExportService,
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        FilesystemInterface $filesystem,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $logRepository,
        $readerFactories,
        $writerFactories,
        $pipeFactories
    ) {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->readerFactories = $readerFactories;
        $this->writerFactories = $writerFactories;
        $this->pipeFactories = $pipeFactories;
        $this->filesystem = $filesystem;
        $this->importExportService = $importExportService;
        $this->eventDispatcher = $eventDispatcher;
        $this->logRepository = $logRepository;
    }

    public function create(string $logId, int $importBatchSize = 250, int $exportBatchSize = 250): ImportExport
    {
        $logEntity = $this->findLog($logId);
        $repository = $this->getRepository($logEntity);

        return new ImportExport(
            $this->importExportService,
            $logEntity,
            $this->filesystem,
            $this->eventDispatcher,
            $repository,
            $this->getPipe($logEntity),
            $this->getReader($logEntity),
            $this->getWriter($logEntity),
            $importBatchSize,
            $exportBatchSize
        );
    }

    private function findLog(string $logId): ImportExportLogEntity
    {
        $criteria = new Criteria([$logId]);
        $criteria->addAssociation('profile');
        $criteria->addAssociation('invalidRecordsLog');
        $logEntity = $this->logRepository->search($criteria, Context::createDefaultContext())->first();

        if ($logEntity === null) {
            throw new ProcessingException('LogEntity not found');
        }

        return $logEntity;
    }

    private function getRepository(ImportExportLogEntity $logEntity): EntityRepositoryInterface
    {
        return $this->definitionInstanceRegistry->getRepository($logEntity->getProfile()->getSourceEntity());
    }

    private function getPipe(ImportExportLogEntity $logEntity): AbstractPipe
    {
        foreach ($this->pipeFactories as $factory) {
            if ($factory->supports($logEntity)) {
                return $factory->create($logEntity);
            }
        }

        throw new \RuntimeException('No pipe factory found');
    }

    private function getReader(ImportExportLogEntity $logEntity): AbstractReader
    {
        foreach ($this->readerFactories as $factory) {
            if ($factory->supports($logEntity)) {
                return $factory->create($logEntity);
            }
        }

        throw new \RuntimeException('No reader factory found');
    }

    private function getWriter(ImportExportLogEntity $logEntity): AbstractWriter
    {
        foreach ($this->writerFactories as $factory) {
            if ($factory->supports($logEntity)) {
                return $factory->create($logEntity);
            }
        }

        throw new \RuntimeException('No writer factory found');
    }
}
