<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Dompdf\Dompdf;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataException;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentGenerator implements DemodataGeneratorInterface
{
    private const MAX_DOCUMENTS_PER_PRODUCT = 4;

    private const DOCUMENT_TYPES = [
        ['file' => 'user-manual', 'en' => 'User manual', 'de' => 'Bedienungsanleitung'],
        ['file' => 'technical-datasheet', 'en' => 'Technical datasheet', 'de' => 'Technisches Datenblatt'],
        ['file' => 'safety-instructions', 'en' => 'Safety instructions', 'de' => 'Sicherheitshinweise'],
        ['file' => 'declaration-of-conformity', 'en' => 'Declaration of conformity', 'de' => 'Konformitätserklärung'],
        ['file' => 'warranty-information', 'en' => 'Warranty information', 'de' => 'Garantiebedingungen'],
        ['file' => 'installation-guide', 'en' => 'Installation guide', 'de' => 'Installationsanleitung'],
    ];

    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly ProductDocumentDefinition $productDocumentDefinition,
        private readonly MediaDefinition $mediaDefinition,
        private readonly FileSaver $fileSaver,
        private readonly FileNameProvider $fileNameProvider,
        private readonly Filesystem $filesystem,
        private readonly InheritanceUpdater $inheritanceUpdater,
        private readonly Connection $connection,
    ) {
    }

    public function getDefinition(): string
    {
        return ProductDocumentDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $products = $this->getProducts($numberOfItems);
        if ($products === []) {
            throw DemodataException::wrongExecutionOrder();
        }

        $faker = $context->getFaker();
        $writeContext = WriteContext::createFromContext($context->getContext());
        $languages = $this->getLanguages();
        $folderId = $this->getFolderId();

        $context->getConsole()->progressStart($numberOfItems);

        $remaining = $numberOfItems;
        $productIds = [];
        foreach ($products as $product) {
            if ($remaining <= 0) {
                break;
            }

            $count = min($remaining, $faker->numberBetween(1, self::MAX_DOCUMENTS_PER_PRODUCT));

            $documents = [];
            foreach ($faker->randomElements(self::DOCUMENT_TYPES, $count) as $position => $type) {
                $translations = [
                    Defaults::LANGUAGE_SYSTEM => [
                        'title' => $type['en'],
                        'mediaId' => $this->createFile($type['en'], $type['file'], $product, $folderId, $context, $writeContext),
                        'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
                    ],
                ];

                foreach ($languages as $languageId => $localeCode) {
                    if (!$faker->boolean()) {
                        continue;
                    }

                    $title = str_starts_with($localeCode, 'de-') ? $type['de'] : $type['en'];

                    $translations[$languageId] = [
                        'title' => $title,
                        'mediaId' => $this->createFile($title, $type['file'] . '-' . mb_strtolower($localeCode), $product, $folderId, $context, $writeContext),
                    ];
                }

                $documents[] = [
                    'id' => Uuid::randomHex(),
                    'productId' => $product['id'],
                    'position' => $position,
                    'translations' => $translations,
                ];
            }

            $this->writer->upsert($this->productDocumentDefinition, $documents, $writeContext);
            $productIds[] = $product['id'];

            $remaining -= $count;
            $context->getConsole()->progressAdvance($count);
        }

        // The writer skips the indexers, so point the products and their variants to the new documents here
        $this->inheritanceUpdater->update(
            ProductDefinition::ENTITY_NAME,
            [...$productIds, ...$this->getVariantIds($productIds)],
            $context->getContext()
        );

        $context->getConsole()->progressFinish();
    }

    /**
     * @param array{id: string, productNumber: string, name: ?string} $product
     */
    private function createFile(string $title, string $fileName, array $product, ?string $folderId, DemodataContext $context, WriteContext $writeContext): string
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

        $content = $this->createPdf(
            $title,
            [
                $product['name'] ?? $product['productNumber'],
                'Product number: ' . $product['productNumber'],
            ],
            [$faker->paragraph(), $faker->paragraph(), $faker->paragraph()],
        );

        $path = $this->filesystem->tempnam(sys_get_temp_dir(), 'demodata-document-');

        try {
            $this->filesystem->dumpFile($path, $content);

            $this->fileSaver->persistFileToMedia(
                new MediaFile($path, 'application/pdf', 'pdf', \strlen($content), Hasher::hash($content, 'md5')),
                $this->fileNameProvider->provide($product['productNumber'] . '-' . $fileName, 'pdf', $mediaId, $context->getContext()),
                $mediaId,
                $context->getContext()
            );
        } finally {
            $this->filesystem->remove($path);
        }

        return $mediaId;
    }

    /**
     * @param list<string> $subtitles
     * @param list<string> $paragraphs
     */
    private function createPdf(string $title, array $subtitles, array $paragraphs): string
    {
        $html = \sprintf(
            '<h1>%s</h1><p>%s</p><p>%s</p>',
            htmlspecialchars($title),
            implode('<br>', array_map(htmlspecialchars(...), $subtitles)),
            implode('</p><p>', array_map(htmlspecialchars(...), $paragraphs)),
        );

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @return list<array{id: string, productNumber: string, name: ?string}>
     */
    private function getProducts(int $limit): array
    {
        /** @var list<array{id: string, productNumber: string, name: ?string}> $products */
        $products = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(product.id)) AS id, product.product_number AS productNumber, translation.name
             FROM product
             LEFT JOIN product_translation translation
                ON translation.product_id = product.id
                AND translation.product_version_id = product.version_id
                AND translation.language_id = :languageId
             WHERE product.parent_id IS NULL
                AND product.version_id = :versionId
                AND NOT EXISTS (
                    SELECT 1 FROM product_document document
                    WHERE document.product_id = product.id AND document.product_version_id = product.version_id
                )
             LIMIT :limit',
            [
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'limit' => $limit,
            ],
            ['limit' => ParameterType::INTEGER]
        );

        return $products;
    }

    /**
     * @param list<string> $productIds
     *
     * @return list<string>
     */
    private function getVariantIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        /** @var list<string> $variantIds */
        $variantIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM product WHERE parent_id IN (:productIds) AND version_id = :versionId',
            [
                'productIds' => Uuid::fromHexToBytesList($productIds),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['productIds' => ArrayParameterType::BINARY]
        );

        return $variantIds;
    }

    /**
     * @return array<string, string>
     */
    private function getLanguages(): array
    {
        /** @var array<string, string> $languages */
        $languages = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(language.id)), locale.code
             FROM language
             INNER JOIN locale ON locale.id = language.translation_code_id
             WHERE language.id != :languageId',
            ['languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        return $languages;
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
