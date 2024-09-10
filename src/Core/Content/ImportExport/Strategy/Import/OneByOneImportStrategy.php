<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Strategy\Import;

use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\ImportResult;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('services-settings')]
class OneByOneImportStrategy implements ImportStrategyService
{
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

            $progress->addProcessedRecords(1);

            return new ImportResult([$result], []);
        } catch (\Throwable $exception) {
            $event = new ImportExportExceptionImportRecordEvent($exception, $record, $row, $config, $context);
            $this->eventDispatcher->dispatch($event);

            $importException = $event->getException();

            if ($importException) {
                $record['_error'] = mb_convert_encoding($importException->getMessage(), 'UTF-8', 'UTF-8');

                return new ImportResult([], [$record]);
            }

            return new ImportResult([], []);
        }
    }

    /**
     * We don't need to do anything here, as we are already committing the data in the import method.
     */
    public function commit(Config $config, Progress $progress, Context $context): ImportResult
    {
        return new ImportResult([], []);
    }
}
