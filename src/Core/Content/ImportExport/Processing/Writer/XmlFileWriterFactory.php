<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;

/**
 * @package system-settings
 */
class XmlFileWriterFactory extends AbstractWriterFactory
{
    /**
     * @var FilesystemOperator
     */
    private $filesystem;

    public function __construct(FilesystemOperator $filesystem)
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
