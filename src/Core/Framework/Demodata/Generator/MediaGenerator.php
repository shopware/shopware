<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
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
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('framework')]
class MediaGenerator implements DemodataGeneratorInterface
{
    private Generator $faker;

    /**
     * @internal
     *
     * @param EntityRepository<MediaDefaultFolderCollection> $defaultFolderRepository
     * @param EntityRepository<MediaFolderCollection> $folderRepository
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
        $tags = $this->getIds();

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
                        'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
                    ],
                ],
                $writeContext
            );

            $this->mediaUpdater->persistFileToMedia(
                new MediaFile(
                    $file,
                    (string) mime_content_type($file),
                    pathinfo($file, \PATHINFO_EXTENSION),
                    (int) filesize($file),
                    Hasher::hashFile($file, 'md5')
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

            if (str_starts_with($file, sys_get_temp_dir())) {
                unlink($file);
            }

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

        if ($tags !== []) {
            $chosenTags = $this->faker->randomElements($tags, $this->faker->randomDigit());

            if (!empty($chosenTags)) {
                $tagAssignments = array_values(array_map(
                    static fn (string $id) => ['id' => $id],
                    $chosenTags
                ));
            }
        }

        return $tagAssignments;
    }

    /**
     * @return list<string>
     */
    private function getIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM tag LIMIT 500');

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
                        ->name('/\.(jpg|png|webp|avif)$/')
                        ->getIterator()
                )
            );
        }

        if ($images !== []) {
            return $images[array_rand($images)]->getPathname();
        }

        $faker = $context->getFaker();

        /** @var positive-int $width */
        $width = $faker->numberBetween(600, 800);
        /** @var positive-int $height */
        $height = $faker->numberBetween(400, 600);

        $image = imagecreate($width, $height);
        \assert($image !== false);

        imagecolorallocate($image, 0xD8, 0xDD, 0xE6);

        /** @var string $text */
        $text = $faker->words(1, true);

        // Render text at built-in font size, then scale up to fill the image
        $font = 5;
        $charWidth = imagefontwidth($font);
        $charHeight = imagefontheight($font);
        $textWidth = $charWidth * \strlen($text);

        /** @var positive-int $textImageWidth */
        $textImageWidth = $textWidth + 2;
        /** @var positive-int $textImageHeight */
        $textImageHeight = $charHeight + 2;

        $textImage = imagecreate($textImageWidth, $textImageHeight);
        \assert($textImage !== false);
        imagecolorallocate($textImage, 0xD8, 0xDD, 0xE6);
        $textImageColor = (int) imagecolorallocate($textImage, 0x33, 0x33, 0x33);
        imagestring($textImage, $font, 1, 1, $text, $textImageColor);

        $scale = min(($width - 20) / $textImageWidth, ($height - 20) / $textImageHeight);
        $scaledWidth = (int) ($textImageWidth * $scale);
        $scaledHeight = (int) ($textImageHeight * $scale);

        imagecopyresized(
            $image,
            $textImage,
            (int) (($width - $scaledWidth) / 2),
            (int) (($height - $scaledHeight) / 2),
            0,
            0,
            $scaledWidth,
            $scaledHeight,
            $textImageWidth,
            $textImageHeight,
        );

        $filePath = sys_get_temp_dir() . '/' . Uuid::randomHex() . '.jpg';
        imagejpeg($image, $filePath);

        return $filePath;
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

        $defaultFolder = $defaultFolders->getEntities()->first();
        if (!$defaultFolder) {
            return $mediaFolderId;
        }

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
