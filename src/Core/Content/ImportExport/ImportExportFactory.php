<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('system-settings')]
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
        private readonly EntityRepository $logRepository,
        private readonly Connection $connection,
        private readonly AbstractFileService $fileService,
        private readonly \IteratorAggregate $readerFactories,
        private readonly \IteratorAggregate $writerFactories,
        private readonly \IteratorAggregate $pipeFactories
    ) {
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
        $criteria->addAssociation('file');
        $criteria->addAssociation('invalidRecordsLog.file');
        $logEntity = $this->logRepository->search($criteria, Context::createDefaultContext())->first();

        if ($logEntity === null) {
            throw new ProcessingException('LogEntity not found');
        }

        return $logEntity;
    }

    private function getRepository(ImportExportLogEntity $logEntity): EntityRepository
    {
        /** @var ImportExportProfileEntity $profile */
        $profile = $logEntity->getProfile();

        return $this->definitionInstanceRegistry->getRepository($profile->getSourceEntity());
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
