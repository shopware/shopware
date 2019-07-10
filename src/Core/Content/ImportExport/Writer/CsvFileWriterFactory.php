<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\Context;

class CsvFileWriterFactory implements WriterFactoryInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function create(Context $context, ImportExportLogEntity $logEntity): WriterInterface
    {
        return new CsvFileWriter(
            $this->filesystem,
            $logEntity->getFile()->getPath(),
            $logEntity->getProfile()->getDelimiter(),
            $logEntity->getProfile()->getEnclosure()
        );
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_EXPORT
            && $logEntity->getProfile()->getFileType() === 'text/csv';
    }
}
