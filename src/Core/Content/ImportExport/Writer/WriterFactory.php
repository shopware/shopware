<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

class WriterFactory
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function __construct(FilesystemInterface $filesystem, DefinitionInstanceRegistry $definitionRegistry)
    {
        $this->filesystem = $filesystem;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function create(ImportExportLogEntity $logEntity, Context $context): WriterInterface
    {
        if ($logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_EXPORT) {
            switch ($logEntity->getProfile()->getFileType()) {
                case 'text/csv':
                    return new CsvFileWriter(
                        $this->filesystem,
                        $logEntity->getFile()->getPath(),
                        $logEntity->getProfile()->getDelimiter(),
                        $logEntity->getProfile()->getEnclosure()
                    );
                case 'text/xml':
                    return new XmlFileWriter(
                        $this->filesystem,
                        $logEntity->getFile()->getPath()
                    );
            }

            throw new \InvalidArgumentException('Unsupported file type: ' . $logEntity->getProfile()->getFileType());
        }

        $targetRepo = $this->definitionRegistry->getRepository($logEntity->getProfile()->getSourceEntity());

        return new RepositoryWriter($targetRepo, $context);
    }
}
