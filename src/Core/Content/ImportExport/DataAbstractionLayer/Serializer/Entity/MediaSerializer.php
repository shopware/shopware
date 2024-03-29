<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Exception\InvalidMediaUrlException;
use Shopware\Core\Content\ImportExport\Exception\MediaDownloadException;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @final
 */
#[Package('core')]
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

        if (empty($url)) {
            return $deserialized;
        }

        if (!filter_var($url, \FILTER_VALIDATE_URL)) {
            $deserialized['_error'] = new InvalidMediaUrlException($url);

            return $deserialized;
        }

        $context = Context::createDefaultContext();

        $media = null;
        if (isset($deserialized['id'])) {
            $media = $this->mediaRepository->search(new Criteria([$deserialized['id']]), $context)->getEntities()->first();
        }

        $isNew = $media === null;

        if ($isNew || $media->getUrl() !== $url) {
            $entityName = $config->get('sourceEntity') ?? $definition->getEntityName();
            $deserialized['mediaFolderId'] ??= $this->getMediaFolderId($deserialized['id'] ?? null, $entityName, $context);

            $deserialized['id'] ??= Uuid::randomHex();

            $parsed = parse_url((string) $url);
            if (!$parsed) {
                throw new \RuntimeException('Error parsing media URL: ' . $url);
            }

            $pathInfo = pathinfo($parsed['path'] ?? '');

            $media = $this->fetchFileFromURL((string) $url, $pathInfo['extension'] ?? '');

            if ($media === null) {
                $deserialized['_error'] = new MediaDownloadException($url);

                return $deserialized;
            }

            if ($isNew && $media->getHash()) {
                $deserialized = $this->fetchExistingMediaByHash($deserialized, $media->getHash(), $context);
            }

            $this->cacheMediaFiles[(string) $deserialized['id']] = [
                'media' => $media,
                'destination' => urldecode($pathInfo['filename']),
            ];
        }

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
        if (empty($this->cacheMediaFiles)) {
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
            throw new \RuntimeException('Failed to find default media folder for import_export_profile');
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

    /**
     * @param array<string, mixed> $deserialized
     *
     * @return array<string, mixed>
     */
    private function fetchExistingMediaByHash(array $deserialized, string $hash, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('metaData.hash', $hash));

        $mediaId = $this->mediaRepository->searchIds($criteria, $context)->firstId();
        if ($mediaId !== null) {
            $deserialized['id'] = $mediaId;
        }

        return $deserialized;
    }
}
