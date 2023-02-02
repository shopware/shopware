<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;

class XmlFileWriterFactory extends AbstractWriterFactory
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function create(ImportExportLogEntity $logEntity): AbstractWriter
    {
        return new XmlFileWriter($this->filesystem);
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_EXPORT
            && $logEntity->getProfile()->getFileType() === 'text/xml';
    }
}
