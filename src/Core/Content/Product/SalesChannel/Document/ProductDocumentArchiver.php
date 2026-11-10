<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Document;

use League\Flysystem\FilesystemOperator;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Util\PathHelper;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentArchiver
{
    public const DIRECTORY = 'product-documents/archives';

    private const TAG_PREFIX = 'product-document-archive-';

    /**
     * @param EntityRepository<ProductDocumentCollection> $productDocumentRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $productDocumentRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly FilesystemOperator $publicFilesystem,
        private readonly FilesystemOperator $privateFilesystem,
        private readonly Filesystem $filesystem,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public static function buildTag(string $productId): string
    {
        return self::TAG_PREFIX . $productId;
    }

    public static function extractProductId(string $tag): ?string
    {
        if (!str_starts_with($tag, self::TAG_PREFIX)) {
            return null;
        }

        $productId = substr($tag, \strlen(self::TAG_PREFIX));

        return Uuid::isValid($productId) ? $productId : null;
    }

    public function archive(SalesChannelProductEntity $product, SalesChannelContext $context): ?ProductDocumentArchive
    {
        $documents = $this->loadDocuments($product->getId(), $context);
        $document = $documents->first();
        if ($document === null) {
            return null;
        }

        $files = $this->collectFiles($documents, $context);
        if ($files->count() === 0) {
            return null;
        }

        $path = \sprintf('%s/%s/%s.zip', self::DIRECTORY, $document->getProductId(), $this->hashFiles($files));

        if (!$this->privateFilesystem->fileExists($path)) {
            $this->build($path, $files);
        }

        return new ProductDocumentArchive($path, $this->createFileName($product), $this->privateFilesystem->fileSize($path));
    }

    public function readStream(ProductDocumentArchive $archive): StreamInterface
    {
        return $this->streamFactory->createStreamFromResource($this->privateFilesystem->readStream($archive->path));
    }

    private function loadDocuments(string $productId, SalesChannelContext $context): ProductDocumentCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('product-document-archiver::documents');
        // inherited, unlike `productId`
        $criteria->addFilter(new EqualsFilter('product.id', $productId));
        $criteria->addSorting(new FieldSorting('position'));

        return $this->productDocumentRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function collectFiles(ProductDocumentCollection $documents, SalesChannelContext $context): MediaCollection
    {
        $mediaIds = [];
        foreach ($documents as $document) {
            $mediaId = $document->getTranslation('mediaId');
            if (\is_string($mediaId)) {
                $mediaIds[] = $mediaId;
            }
        }

        if ($mediaIds === []) {
            return new MediaCollection();
        }

        $criteria = new Criteria(array_values(array_unique($mediaIds)));
        $criteria->setTitle('product-document-archiver::media');
        $criteria->addFilter(
            new EqualsFilter('private', false),
            new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('path', null)]),
        );

        return $this->mediaRepository->search($criteria, $context->getContext())->getEntities();
    }

    /**
     * @param array<string, true> $names
     */
    private function createEntryName(MediaEntity $media, array $names): string
    {
        $base = (string) $media->getFileName();
        if ($base === '') {
            $base = $media->getId();
        }

        $extension = (string) $media->getFileExtension();
        $extension = $extension !== '' ? '.' . $extension : '';

        $name = $base . $extension;
        $number = 1;
        while (isset($names[mb_strtolower($name)])) {
            ++$number;
            $name = \sprintf('%s (%d)%s', $base, $number, $extension);
        }

        return $name;
    }

    private function cleanFileName(string $name): string
    {
        // a download name can't contain `/` or `\`
        return trim(str_replace(['/', '\\'], '-', PathHelper::stripControlAndFormatChars($name)));
    }

    private function hashFiles(MediaCollection $files): string
    {
        return Hasher::hash($files->map(static fn (MediaEntity $file): array => [
            $file->getId(),
            $file->getFileName(),
            $file->getFileExtension(),
            $file->getPath(),
            $file->getFileSize(),
            $file->getUploadedAt()?->format(\DATE_ATOM),
        ]));
    }

    private function createFileName(SalesChannelProductEntity $product): string
    {
        $name = $product->getTranslation('name');
        $name = $this->cleanFileName(\is_string($name) ? $name : '');

        return ($name !== '' ? $name : $this->cleanFileName($product->getProductNumber())) . '.zip';
    }

    private function build(string $path, MediaCollection $files): void
    {
        $temporaryFiles = [];

        try {
            $archivePath = $temporaryFiles[] = $this->filesystem->tempnam(sys_get_temp_dir(), 'product-documents-', '.zip');

            $zip = new \ZipArchive();
            if ($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw ProductException::documentArchiveFailed('the ZIP file could not be created');
            }

            $names = [];
            foreach ($files as $file) {
                $name = $this->createEntryName($file, $names);
                $names[mb_strtolower($name)] = true;

                $copy = $temporaryFiles[] = $this->filesystem->tempnam(sys_get_temp_dir(), 'product-document-');
                $this->filesystem->dumpFile($copy, $this->publicFilesystem->readStream($file->getPath()));

                // documents are compressed formats already, so they are stored as they are
                if (!$zip->addFile($copy, $name) || !$zip->setCompressionName($name, \ZipArchive::CM_STORE)) {
                    throw ProductException::documentArchiveFailed(\sprintf('"%s" could not be added', $name));
                }
            }

            if (!$zip->close()) {
                throw ProductException::documentArchiveFailed('the ZIP file could not be written');
            }

            $stream = fopen($archivePath, 'r');
            if ($stream === false) {
                throw ProductException::documentArchiveFailed('the ZIP file could not be read');
            }

            $uploadPath = $path . '.' . Uuid::randomHex();

            try {
                $this->privateFilesystem->writeStream($uploadPath, $stream);
                $this->privateFilesystem->move($uploadPath, $path);
            } catch (\Throwable $exception) {
                $this->privateFilesystem->delete($uploadPath);

                throw $exception;
            } finally {
                fclose($stream);
            }
        } finally {
            $this->filesystem->remove($temporaryFiles);
        }
    }
}
