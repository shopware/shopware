<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Maltyxx\ImagesGenerator\ImagesGeneratorProvider;
use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('core')]
class MediaGenerator implements DemodataGeneratorInterface
{
    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly FileSaver $mediaUpdater,
        private readonly FileNameProvider $fileNameProvider,
        private readonly EntityRepository $defaultFolderRepository,
        private readonly EntityRepository $folderRepository,
        private readonly MediaDefinition $mediaDefinition,
        private readonly Connection $connection
    ) {
    }

    public function getDefinition(): string
    {
        return MediaDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);
        $this->faker = $context->getFaker();

        $writeContext = WriteContext::createFromContext($context->getContext());

        $mediaFolderId = $this->getOrCreateDefaultFolder($context);
        $downloadFolderId = $this->getOrCreateDefaultFolder($context, true);
        $tags = $this->getIds('tag');

        for ($i = 0; $i < $numberOfItems; ++$i) {
            $isDownloadFile = $i % 30 === 0;
            $file = $this->getRandomFile($context);

            $mediaId = Uuid::randomHex();
            $this->writer->insert(
                $this->mediaDefinition,
                [
                    [
                        'id' => $mediaId,
                        'title' => "File #{$i}: {$file}",
                        'mediaFolderId' => $isDownloadFile ? $downloadFolderId : $mediaFolderId,
                        'private' => $isDownloadFile,
                        'tags' => $this->getTags($tags),
                    ],
                ],
                $writeContext
            );

            $this->mediaUpdater->persistFileToMedia(
                new MediaFile(
                    $file,
                    (string) mime_content_type($file),
                    pathinfo($file, \PATHINFO_EXTENSION),
                    (int) filesize($file)
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

    /**
     * @param list<string> $tags
     *
     * @return list<array{id: string}>
     */
    private function getTags(array $tags): array
    {
        $tagAssignments = [];

        if (!empty($tags)) {
            $chosenTags = $this->faker->randomElements($tags, $this->faker->randomDigit());

            if (!empty($chosenTags)) {
                $tagAssignments = array_values(array_map(
                    fn (string $id) => ['id' => $id],
                    $chosenTags
                ));
            }
        }

        return $tagAssignments;
    }

    /**
     * @return list<string>
     */
    private function getIds(string $table): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM ' . $table . ' LIMIT 500');

        return $ids;
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

        $provider = new ImagesGeneratorProvider(new Generator());

        return $provider->imageGenerator(
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

    private function getOrCreateDefaultFolder(DemodataContext $context, bool $isDownloadFolder = false): ?string
    {
        $mediaFolderId = null;

        $entity = $isDownloadFolder ? 'product_download' : 'product';
        $name = $isDownloadFolder ? 'Product Downloads' : 'Product Media';
        $configuration = $isDownloadFolder ? ['private' => true] : [];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entity', $entity));
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
                'name' => $name,
                'useParentConfiguration' => false,
                'configuration' => $configuration,
            ],
        ], $context->getContext());

        return $mediaFolderId;
    }
}
