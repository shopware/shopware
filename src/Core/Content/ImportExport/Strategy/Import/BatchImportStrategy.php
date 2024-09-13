<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Strategy\Import;

use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\ImportResult;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @phpstan-import-type ImportData from ImportExport
 *
 * @internal
 */
#[Package('services-settings')]
class BatchImportStrategy extends OneByOneImportStrategy implements ResetInterface
{
    /**
     * @var ImportData[] array
     */
    protected array $toImport = [];

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        EntityRepository $repository,
        protected readonly int $batchSize = 250,
    ) {
        parent::__construct($eventDispatcher, $repository);

        $this->reset();
    }

    /**
     * The import method here only stores the records in a batch. The actual import is done in the commit method.
     *
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
        $this->toImport[] = [
            'record' => $record,
            'original' => $row,
        ];

        return new ImportResult([], []);
    }

    public function commit(Config $config, Progress $progress, Context $context): ImportResult
    {
        $records = array_map(fn (array $data) => $data['record'], $this->toImport);

        $createEntities = $config->get('createEntities') ?? true;
        $updateEntities = $config->get('updateEntities') ?? true;

        try {
            if ($createEntities === true && $updateEntities === false) {
                $result = $this->repository->create($records, $context);
            } elseif ($createEntities === false && $updateEntities === true) {
                $result = $this->repository->update($records, $context);
            } else {
                // expect that both create and update are true -> upsert
                // both false isn't possible via admin (but still results in an upsert)
                $result = $this->repository->upsert($records, $context);
            }

            foreach ($this->toImport as $data) {
                $afterRecord = new ImportExportAfterImportRecordEvent($result, $data['record'], $data['original'], $config, $context);
                $this->eventDispatcher->dispatch($afterRecord);
            }

            $progress->addProcessedRecords(\count($this->toImport));

            $this->reset();

            return new ImportResult([$result], []);
        } catch (\Throwable $exception) {
            // If we have an error, we will try to import one by one
            $results = [];
            $failedRecords = [];

            foreach ($this->toImport as $data) {
                $importResult = parent::import($data['record'], $data['original'], $config, $progress, $context);

                $results = array_merge($results, $importResult->results);
                $failedRecords = array_merge($failedRecords, $importResult->failedRecords);
            }

            $this->reset();

            return new ImportResult($results, $failedRecords);
        }
    }

    public function reset(): void
    {
        $this->toImport = [];
    }
}
