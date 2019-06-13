<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Mapping\CriteriaBuilder;
use Shopware\Core\Content\ImportExport\Mapping\FieldDefinitionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

class IteratorFactory
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var int
     */
    private $readBufferSize;

    public function __construct(FilesystemInterface $filesystem, DefinitionInstanceRegistry $definitionRegistry, int $readBufferSize)
    {
        $this->filesystem = $filesystem;
        $this->definitionRegistry = $definitionRegistry;
        $this->readBufferSize = $readBufferSize;
    }

    public function create(Context $context, string $activity, ImportExportFileEntity $fileEntity, ImportExportProfileEntity $profileEntity): RecordIterator
    {
        if ($activity === ImportExportLogEntity::ACTIVITY_IMPORT) {
            switch ($profileEntity->getFileType()) {
                case 'text/csv':
                    return new CsvFileIterator(
                        $this->filesystem->readStream($fileEntity->getPath()),
                        $profileEntity->getDelimiter(),
                        $profileEntity->getEnclosure()
                    );

                case 'text/xml':
                    $fakePath = 'files/' . $fileEntity->getPath();

                    return new XmlFileIterator($fakePath);

                default:
                    throw new \InvalidArgumentException('Unsupported file type: ' . $profileEntity->getFileType());
            }
        }

        $entityDefinition = $this->definitionRegistry->getByEntityName($profileEntity->getSourceEntity());

        return new RepositoryIterator(
            $this->definitionRegistry->getRepository($profileEntity->getSourceEntity()),
            $context,
            new CriteriaBuilder(FieldDefinitionCollection::fromArray($profileEntity->getMapping()), $entityDefinition),
            $this->readBufferSize
        );
    }
}
