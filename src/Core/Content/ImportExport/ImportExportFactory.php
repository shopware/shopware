<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipeFactory;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReaderFactory;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriterFactory;
use Shopware\Core\Content\ImportExport\Service\AbstractFileService;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ImportExportFactory
{
    private ImportExportService $importExportService;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    private FilesystemInterface $filesystem;

    private EventDispatcherInterface $eventDispatcher;

    private EntityRepositoryInterface $logRepository;

    private Connection $connection;

    /**
     * @var \IteratorAggregate<AbstractReaderFactory>
     */
    private \IteratorAggregate $readerFactories;

    /**
     * @var \IteratorAggregate<AbstractWriterFactory>
     */
    private \IteratorAggregate $writerFactories;

    /**
     * @var \IteratorAggregate<AbstractPipeFactory>
     */
    private \IteratorAggregate $pipeFactories;

    private AbstractFileService $fileService;

    public function __construct(
        ImportExportService $importExportService,
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        FilesystemInterface $filesystem,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $logRepository,
        Connection $connection,
        AbstractFileService $fileService,
        \IteratorAggregate $readerFactories,
        \IteratorAggregate $writerFactories,
        \IteratorAggregate $pipeFactories
    ) {
        $this->importExportService = $importExportService;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->filesystem = $filesystem;
        $this->eventDispatcher = $eventDispatcher;
        $this->logRepository = $logRepository;
        $this->connection = $connection;
        $this->fileService = $fileService;
        $this->readerFactories = $readerFactories;
        $this->writerFactories = $writerFactories;
        $this->pipeFactories = $pipeFactories;
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
            $this->connection,
            $repository,
            $this->getPipe($logEntity),
            $this->getReader($logEntity),
            $this->getWriter($logEntity),
            $this->fileService,
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
