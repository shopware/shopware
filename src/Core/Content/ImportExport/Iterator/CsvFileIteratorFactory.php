<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\Context;

class CsvFileIteratorFactory implements IteratorFactoryInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function create(Context $context, ImportExportLogEntity $logEntity): RecordIterator
    {
        return new CsvFileIterator(
            $this->filesystem->readStream($logEntity->getFile()->getPath()),
            $logEntity->getProfile()->getDelimiter(),
            $logEntity->getProfile()->getEnclosure()
        );
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_IMPORT
            && $logEntity->getProfile()->getFileType() === 'text/csv';
    }
}
