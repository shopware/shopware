<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Finder\Finder;

class MediaGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var FileSaver
     */
    private $mediaUpdater;

    /**
     * @var FileNameProvider
     */
    private $fileNameProvider;

    /**
     * @var array
     */
    private $tmpImages = [];

    /**
     * @var EntityRepositoryInterface
     */
    private $defaultFolderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $folderRepository;

    /**
     * @var MediaDefinition
     */
    private $mediaDefinition;

    public function __construct(
        EntityWriterInterface $writer,
        FileSaver $mediaUpdater,
        FileNameProvider $fileNameProvider,
        EntityRepositoryInterface $defaultFolderRepository,
        EntityRepositoryInterface $folderRepository,
        MediaDefinition $mediaDefinition
    ) {
        $this->writer = $writer;
        $this->mediaUpdater = $mediaUpdater;
        $this->fileNameProvider = $fileNameProvider;
        $this->defaultFolderRepository = $defaultFolderRepository;
        $this->folderRepository = $folderRepository;
        $this->mediaDefinition = $mediaDefinition;
    }

    public function getDefinition(): string
    {
        return MediaDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $writeContext = WriteContext::createFromContext($context->getContext());

        $mediaFolderId = $this->getOrCreateDefaultFolder($context);

        for ($i = 0; $i < $numberOfItems; ++$i) {
            $file = $this->getRandomFile($context);

            $mediaId = Uuid::randomHex();
            $this->writer->insert(
                $this->mediaDefinition,
                [
                    [
                        'id' => $mediaId,
                        'title' => "File #{$i}: {$file}",
                        'mediaFolderId' => $mediaFolderId,
                    ],
                ],
                $writeContext
            );

            $this->mediaUpdater->persistFileToMedia(
                new MediaFile(
                    $file,
                    mime_content_type($file),
                    pathinfo($file, \PATHINFO_EXTENSION),
                    filesize($file)
                ),
                $this->fileNameProvider->provide(
                    pathinfo($file, \PATHINFO_FILENAME),
                    pathinfo($file, \PATHINFO_EXTENSION),
                    $mediaId,
                    $context->getContext()
                ),
                $mediaId,
                $context->getContext()
            );

            $context->getConsole()->progressAdvance(1);
        }

        $context->getConsole()->progressFinish();
    }

    private function getRandomFile(DemodataContext $context): string
    {
        $fixtureDir = $context->getProjectDir() . '/build/media';
        $images = [];

        if (is_dir($fixtureDir)) {
            $images = array_values(
                iterator_to_array(
                    (new Finder())
                        ->files()
                        ->in($fixtureDir)
                        ->name('/\.(jpg|png)$/')
                        ->getIterator()
                )
            );
        }

        if (\count($images)) {
            return $images[array_rand($images)]->getPathname();
        }

        /** @var string $text */
        $text = $context->getFaker()->words(1, true);

        /*
         * @deprecated tag:v6.5.0 remove and replace by importing \Maltyxx\ImagesGenerator\ImagesGeneratorProvider
         */
        if (\class_exists(\Maltyxx\ImagesGenerator\ImagesGeneratorProvider::class)) {
            $provider = \Maltyxx\ImagesGenerator\ImagesGeneratorProvider::class;
        } else {
            $provider = \bheller\ImagesGenerator\ImagesGeneratorProvider::class;
        }

        return $this->tmpImages[] = $provider::imageGenerator(
            null,
            $context->getFaker()->numberBetween(600, 800),
            $context->getFaker()->numberBetween(400, 600),
            'jpg',
            true,
            $text,
            '#d8dde6',
            '#333333'
        );
    }

    private function getOrCreateDefaultFolder(DemodataContext $context): ?string
    {
        $mediaFolderId = null;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entity', 'product'));
        $criteria->addAssociation('folder');
        $criteria->setLimit(1);

        $defaultFolders = $this->defaultFolderRepository->search($criteria, $context->getContext());

        if ($defaultFolders->count() <= 0) {
            return $mediaFolderId;
        }

        /** @var MediaDefaultFolderEntity $defaultFolder */
        $defaultFolder = $defaultFolders->first();

        if ($defaultFolder->getFolder()) {
            return $defaultFolder->getFolder()->getId();
        }

        $mediaFolderId = Uuid::randomHex();
        $this->folderRepository->upsert([
            [
                'id' => $mediaFolderId,
                'defaultFolderId' => $defaultFolder->getId(),
                'name' => 'Product Media',
                'useParentConfiguration' => false,
                'configuration' => [],
            ],
        ], $context->getContext());

        return $mediaFolderId;
    }
}
