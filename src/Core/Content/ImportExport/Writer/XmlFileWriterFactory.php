<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\Context;

class XmlFileWriterFactory implements WriterFactoryInterface
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
        return new XmlFileWriter($this->filesystem, $logEntity->getFile()->getPath());
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_EXPORT
            && $logEntity->getProfile()->getFileType() === 'text/xml';
    }
}
