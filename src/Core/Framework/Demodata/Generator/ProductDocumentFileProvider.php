<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Dompdf\Dompdf;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentFileProvider
{
    private const FILE_NAME_PREFIX = 'demo-';

    private const DOCUMENT_TYPES = [
        'user-manual' => 'User manual',
        'quick-start-guide' => 'Quick start guide',
        'technical-datasheet' => 'Technical datasheet',
        'safety-instructions' => 'Safety instructions',
        'declaration-of-conformity' => 'Declaration of conformity',
        'warranty-information' => 'Warranty information',
        'installation-guide' => 'Installation guide',
        'care-instructions' => 'Care instructions',
        'spare-parts-list' => 'Spare parts list',
        'energy-label' => 'Energy label',
    ];

    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly MediaDefinition $mediaDefinition,
        private readonly FileSaver $fileSaver,
        private readonly FileNameProvider $fileNameProvider,
        private readonly Filesystem $filesystem,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Returns the title and file of each document type. Files of earlier runs are reused.
     *
     * @return list<array{title: string, mediaId: string}>
     */
    public function getDocuments(DemodataContext $context): array
    {
        $existingFiles = $this->getExistingFiles(array_map(
            static fn (string $type): string => self::FILE_NAME_PREFIX . $type,
            array_keys(self::DOCUMENT_TYPES)
        ));

        $folderId = $this->getFolderId();
        $writeContext = WriteContext::createFromContext($context->getContext());

        $documents = [];
        foreach (self::DOCUMENT_TYPES as $type => $title) {
            $fileName = self::FILE_NAME_PREFIX . $type;

            $documents[] = [
                'title' => $title,
                'mediaId' => $existingFiles[$fileName] ?? $this->createFile($title, $fileName, $folderId, $context, $writeContext),
            ];
        }

        return $documents;
    }

    private function createFile(string $title, string $fileName, ?string $folderId, DemodataContext $context, WriteContext $writeContext): string
    {
        $mediaId = Uuid::randomHex();

        $this->writer->insert(
            $this->mediaDefinition,
            [
                [
                    'id' => $mediaId,
                    'title' => $title,
                    'mediaFolderId' => $folderId,
                    'private' => false,
                    'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
                ],
            ],
            $writeContext
        );

        $faker = $context->getFaker();
        $content = $this->createPdf($title, [$faker->paragraph(), $faker->paragraph(), $faker->paragraph()]);

        $path = $this->filesystem->tempnam(sys_get_temp_dir(), 'demodata-document-');

        try {
            $this->filesystem->dumpFile($path, $content);

            $this->fileSaver->persistFileToMedia(
                new MediaFile($path, 'application/pdf', 'pdf', \strlen($content), Hasher::hash($content, 'md5')),
                $this->fileNameProvider->provide($fileName, 'pdf', $mediaId, $context->getContext()),
                $mediaId,
                $context->getContext()
            );
        } finally {
            $this->filesystem->remove($path);
        }

        return $mediaId;
    }

    /**
     * @param list<string> $paragraphs
     */
    private function createPdf(string $title, array $paragraphs): string
    {
        $html = \sprintf(
            '<h1>%s</h1><p>%s</p>',
            htmlspecialchars($title),
            implode('</p><p>', array_map(htmlspecialchars(...), $paragraphs)),
        );

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param list<string> $fileNames
     *
     * @return array<string, string>
     */
    private function getExistingFiles(array $fileNames): array
    {
        /** @var array<string, string> $files */
        $files = $this->connection->fetchAllKeyValue(
            'SELECT media.file_name, LOWER(HEX(media.id))
             FROM media
             INNER JOIN media_translation ON media_translation.media_id = media.id AND media_translation.language_id = :languageId
             WHERE media.file_name IN (:fileNames)
                AND media.file_extension = :extension
                AND media.private = 0
                AND JSON_CONTAINS(media_translation.custom_fields, \'true\', :demodataPath)',
            [
                'fileNames' => $fileNames,
                'extension' => 'pdf',
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'demodataPath' => '$.' . DemodataService::DEMODATA_CUSTOM_FIELDS_KEY,
            ],
            ['fileNames' => ArrayParameterType::STRING]
        );

        return $files;
    }

    private function getFolderId(): ?string
    {
        $folderId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(media_folder.id))
             FROM media_folder
             INNER JOIN media_default_folder ON media_default_folder.id = media_folder.default_folder_id
             WHERE media_default_folder.entity = :entity
             LIMIT 1',
            ['entity' => ProductDocumentDefinition::ENTITY_NAME]
        );

        return \is_string($folderId) ? $folderId : null;
    }
}
