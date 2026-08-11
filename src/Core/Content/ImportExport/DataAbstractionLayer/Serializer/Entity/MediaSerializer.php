<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Exception\InvalidMediaUrlException;
use Shopware\Core\Content\ImportExport\Exception\MediaDownloadException;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\UrlEncoder;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class MediaSerializer extends AbstractMediaSerializer implements ResetInterface
{
    /**
     * @var array<string, array{media: MediaFile, destination: string}>
     */
    private array $cacheMediaFiles = [];

    /**
     * @internal
     *
     * @param EntityRepository<MediaFolderCollection> $mediaFolderRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly FileSaver $fileSaver,
        private readonly EntityRepository $mediaFolderRepository,
        private readonly EntityRepository $mediaRepository
    ) {
    }

    /**
     * @param array<mixed>|\Traversable<mixed> $entity
     *
     * @return array<mixed>
     */
    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);
        $deserialized = parent::deserialize($config, $definition, $entity);
        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        $url = $entity['url'] ?? null;

        if ($url === null || $url === '') {
            return $deserialized;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $url = UrlEncoder::encodeUrl($url);
        }

        if (!filter_var($url, \FILTER_VALIDATE_URL)) {
            $deserialized['_error'] = new InvalidMediaUrlException($url);

            return $deserialized;
        }

        $context = Context::createDefaultContext();

        $mediaEntity = null;
        if (isset($deserialized['id'])) {
            $mediaEntity = $this->mediaRepository->search(new Criteria([$deserialized['id']]), $context)->getEntities()->first();
        }

        if ($mediaEntity !== null && $mediaEntity->getUrl() === $url) {
            return $deserialized;
        }

        $entityName = $config->get('sourceEntity') ?? $definition->getEntityName();
        $deserialized['mediaFolderId'] ??= $this->getMediaFolderId($deserialized['id'] ?? null, $entityName, $context);

        $deserialized['id'] ??= Uuid::randomHex();

        $parsed = parse_url((string) $url);
        if (!$parsed) {
            throw ImportExportException::failedParsingMediaUrl($url);
        }

        $pathInfo = pathinfo($parsed['path'] ?? '');

        $mediaFile = $this->fetchFileFromURL((string) $url, $pathInfo['extension'] ?? '');

        if ($mediaFile === null) {
            $deserialized['_error'] = new MediaDownloadException($url);

            return $deserialized;
        }

        $downloadedHash = $mediaFile->getHash();

        if ($downloadedHash !== null) {
            if ($mediaEntity !== null) {
                $existingHash = $mediaEntity->getMetaData()['hash'] ?? null;

                if ($existingHash === $downloadedHash) {
                    // The CSV URL can differ from the generated media URL while still pointing to the same file.
                    return $deserialized;
                }
            } else {
                $existingMediaId = $this->findExistingMediaIdByHash($downloadedHash, $context);

                if ($existingMediaId !== null) {
                    // Existing media with the same hash is reused; persisting the download again would move its file path.
                    $deserialized['id'] = $existingMediaId;

                    return $deserialized;
                }
            }
        }

        $this->cacheMediaFiles[(string) $deserialized['id']] = [
            'media' => $mediaFile,
            'destination' => urldecode($pathInfo['filename']),
        ];

        return $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === 'media';
    }

    /**
     * @internal
     */
    public function persistMedia(EntityWrittenEvent $event): void
    {
        if ($this->cacheMediaFiles === []) {
            return;
        }

        $mediaFiles = $this->cacheMediaFiles;
        // prevent recursion
        $this->cacheMediaFiles = [];

        foreach ($event->getIds() as $id) {
            if (!isset($mediaFiles[$id])) {
                continue;
            }
            $mediaFile = $mediaFiles[$id];

            $this->fileSaver->persistFileToMedia(
                $mediaFile['media'],
                $mediaFile['destination'],
                $id,
                $event->getContext()
            );
        }
    }

    public function reset(): void
    {
        $this->cacheMediaFiles = [];
    }

    private function getMediaFolderId(?string $id, string $entity, Context $context): string
    {
        if ($id !== null) {
            $folderId = $this->mediaFolderRepository->searchIds(new Criteria([$id]), $context)->firstId();
            if ($folderId !== null) {
                return $folderId;
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', $entity));
        $criteria->addAssociation('defaultFolder');

        $defaultFolderId = $this->mediaFolderRepository->searchIds($criteria, $context)->firstId();
        if ($defaultFolderId !== null) {
            return $defaultFolderId;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', 'import_export_profile'));
        $criteria->addAssociation('defaultFolder');

        $fallbackFolderId = $this->mediaFolderRepository->searchIds($criteria, $context)->firstId();
        if ($fallbackFolderId === null) {
            throw ImportExportException::mediaFolderNotFoundForImportExportProfile();
        }

        return $fallbackFolderId;
    }

    private function fetchFileFromURL(string $url, string $extension): ?MediaFile
    {
        $request = new Request();
        $request->query->set('url', $url);
        $request->query->set('extension', $extension);
        $request->request->set('url', $url);
        $request->request->set('extension', $extension);
        $request->headers->set('content-type', 'application/json');

        try {
            $file = $this->mediaService->fetchFile($request);
            if ($file->getFileSize() > 0) {
                return $file;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function findExistingMediaIdByHash(string $downloadedHash, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('metaData.hash', $downloadedHash));

        return $this->mediaRepository->searchIds($criteria, $context)->firstId();
    }
}
