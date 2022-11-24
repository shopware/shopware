<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;

class CsvFileWriterFactory extends AbstractWriterFactory
{
    /**
     * @var FilesystemOperator
     */
    private $filesystem;

    /**
     * @internal
     */
    public function __construct(FilesystemOperator $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function create(ImportExportLogEntity $logEntity): AbstractWriter
    {
        return new CsvFileWriter($this->filesystem);
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getProfile()->getFileType() === 'text/csv';
    }
}
