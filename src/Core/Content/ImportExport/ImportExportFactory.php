<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipeFactory;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReaderFactory;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriterFactory;
use Shopware\Core\Content\ImportExport\Service\AbstractFileService;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Strategy\Import\BatchImportStrategy;
use Shopware\Core\Content\ImportExport\Strategy\Import\OneByOneImportStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('services-settings')]
class ImportExportFactory
{
    /**
     * @internal
     *
     * @param \IteratorAggregate<mixed, AbstractReaderFactory> $readerFactories
     * @param \IteratorAggregate<mixed, AbstractWriterFactory> $writerFactories
     * @param \IteratorAggregate<mixed, AbstractPipeFactory> $pipeFactories
     */
    public function __construct(
        private readonly ImportExportService $importExportService,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly FilesystemOperator $filesystem,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection,
        private readonly AbstractFileService $fileService,
        private readonly \IteratorAggregate $readerFactories,
        private readonly \IteratorAggregate $writerFactories,
        private readonly \IteratorAggregate $pipeFactories
    ) {
    }

    /**
     * @deprecated tag:v6.7.0 - Parameter $useBatchImport will be added - reason:new-optional-parameter
     */
    public function create(
        string $logId,
        int $importBatchSize = 250,
        int $exportBatchSize = 250,
        /* , bool $useBatchImport = false */
    ): ImportExport {
        $useBatchImport = \func_get_args()[3] ?? false;

        $logEntity = $this->importExportService->findLog(Context::createDefaultContext(), $logId);
        $repository = $this->getRepository($logEntity);

        $importStrategy = $useBatchImport
            ? new BatchImportStrategy($this->eventDispatcher, $repository, $importBatchSize)
            : new OneByOneImportStrategy($this->eventDispatcher, $repository);

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
            $importStrategy,
            $importBatchSize,
            $exportBatchSize
        );
    }

    private function getRepository(ImportExportLogEntity $logEntity): EntityRepository
    {
        $profile = $logEntity->getProfile();

        if ($profile === null) {
            throw ImportExportException::profileNotFound($logEntity->getProfileId() ?? 'null');
        }

        return $this->definitionInstanceRegistry->getRepository($profile->getSourceEntity());
    }

    private function getPipe(ImportExportLogEntity $logEntity): AbstractPipe
    {
        foreach ($this->pipeFactories as $factory) {
            if ($factory->supports($logEntity)) {
                return $factory->create($logEntity);
            }
        }

        throw ImportExportException::processingError('No pipe factory found');
    }

    private function getReader(ImportExportLogEntity $logEntity): AbstractReader
    {
        foreach ($this->readerFactories as $factory) {
            if ($factory->supports($logEntity)) {
                return $factory->create($logEntity);
            }
        }

        throw ImportExportException::processingError('No reader factory found');
    }

    private function getWriter(ImportExportLogEntity $logEntity): AbstractWriter
    {
        foreach ($this->writerFactories as $factory) {
            if ($factory->supports($logEntity)) {
                return $factory->create($logEntity);
            }
        }

        throw ImportExportException::processingError('No writer factory found');
    }
}
