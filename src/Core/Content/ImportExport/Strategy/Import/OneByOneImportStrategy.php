<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Strategy\Import;

use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordsEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\ImportResult;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class OneByOneImportStrategy implements ImportStrategyService
{
    /**
     * @var list<EntityWrittenContainerEvent>
     */
    private array $results = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $failedRecords = [];

    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly EntityRepository $repository,
    ) {
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $row
     */
    public function import(
        array $record,
        array $row,
        Config $config,
        Progress $progress,
        Context $context
    ): ImportResult {
        $createEntities = $config->get('createEntities') ?? true;
        $updateEntities = $config->get('updateEntities') ?? true;

        try {
            if ($createEntities === true && $updateEntities === false) {
                $result = $this->repository->create([$record], $context);
            } elseif ($createEntities === false && $updateEntities === true) {
                $result = $this->repository->update([$record], $context);
            } else {
                // expect that both create and update are true -> upsert
                // both false isn't possible via admin (but still results in an upsert)
                $result = $this->repository->upsert([$record], $context);
            }

            $afterRecord = new ImportExportAfterImportRecordEvent($result, $record, $row, $config, $context);
            $this->eventDispatcher->dispatch($afterRecord);
            $this->results[] = $result;

            $progress->addProcessedRecords(1);

            return new ImportResult([$result], []);
        } catch (\Throwable $exception) {
            if ($exception instanceof WriteTypeIntendException
                && $createEntities === false
                && $updateEntities === true
            ) {
                $exception = ImportExportException::updateEntityNotFound(
                    $this->repository->getDefinition()->getEntityName()
                );
            }

            $event = new ImportExportExceptionImportRecordEvent($exception, $record, $row, $config, $context);
            $this->eventDispatcher->dispatch($event);

            $importException = $event->getException();

            if ($importException) {
                $record['_error'] = mb_convert_encoding($importException->getMessage(), 'UTF-8', 'UTF-8');
                $this->failedRecords[] = $record;

                return new ImportResult([], [$record]);
            }

            return new ImportResult([], []);
        }
    }

    /**
     * The records are written individually in import(). The commit only dispatches the aggregate event
     * for the collected results and resets the state.
     */
    public function commit(Config $config, Progress $progress, Context $context): ImportResult
    {
        if ($this->results === [] && $this->failedRecords === []) {
            return new ImportResult([], []);
        }

        $this->eventDispatcher->dispatch(
            new ImportExportAfterImportRecordsEvent(
                $config,
                $context,
                new ImportResult($this->results, $this->failedRecords),
            )
        );

        $this->reset();

        return new ImportResult([], []);
    }

    public function reset(): void
    {
        $this->results = [];
        $this->failedRecords = [];
    }
}
