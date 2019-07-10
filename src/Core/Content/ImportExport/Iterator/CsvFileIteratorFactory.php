<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
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

    public function create(Context $context, string $activity, ImportExportProfileEntity $profileEntity, ImportExportFileEntity $fileEntity): RecordIterator
    {
        return new CsvFileIterator(
            $this->filesystem->readStream($fileEntity->getPath()),
            $profileEntity->getDelimiter(),
            $profileEntity->getEnclosure()
        );
    }

    public function supports(string $activity, ImportExportProfileEntity $profileEntity): bool
    {
        return $activity === ImportExportLogEntity::ACTIVITY_IMPORT
            && $profileEntity->getFileType() === 'text/csv';
    }
}
