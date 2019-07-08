<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\Context;

class XmlFileIteratorFactory implements IteratorFactoryInterface
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
        $fakePath = 'files/' . $logEntity->getFile()->getPath();

        return new XmlFileIterator($fakePath);
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getProfile()->getFileType() === 'text/xml';
    }
}
