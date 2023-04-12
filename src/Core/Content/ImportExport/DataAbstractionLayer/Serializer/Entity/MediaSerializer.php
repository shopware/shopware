<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Exception\InvalidMediaUrlException;
use Shopware\Core\Content\ImportExport\Exception\MediaDownloadException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
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
     * @return array<mixed>|\Traversable<mixed>
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

        $media = null;
        if (isset($deserialized['id'])) {
            $media = $this->mediaRepository->search(new Criteria([$deserialized['id']]), Context::createDefaultContext())->first();
        }

        $isNew = $media === null;

        if ($isNew || $media->getUrl() !== $url) {
            $entityName = $config->get('sourceEntity') ?? $definition->getEntityName();
            $deserialized['mediaFolderId'] ??= $this->getMediaFolderId($deserialized['id'] ?? null, $entityName);

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
                $deserialized = $this->fetchExistingMediaByHash($deserialized, $media->getHash());
            }

            $this->cacheMediaFiles[(string) $deserialized['id']] = [
                'media' => $media,
                'destination' => $pathInfo['filename'],
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

    private function getMediaFolderId(?string $id, string $entity): string
    {
        if ($id !== null) {
            /** @var MediaFolderEntity|null $folder */
            $folder = $this->mediaFolderRepository->search(new Criteria([$id]), Context::createDefaultContext())->first();
            if ($folder !== null) {
                return $folder->getId();
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', $entity));
        $criteria->addAssociation('defaultFolder');

        /** @var MediaFolderEntity|null $default */
        $default = $this->mediaFolderRepository->search($criteria, Context::createDefaultContext())->first();

        if ($default !== null) {
            return $default->getId();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', 'import_export_profile'));
        $criteria->addAssociation('defaultFolder');

        /** @var MediaFolderEntity|null $fallback */
        $fallback = $this->mediaFolderRepository->search($criteria, Context::createDefaultContext())->first();
        if ($fallback === null) {
            throw new \RuntimeException('Failed to find default media folder for import_export_profile');
        }

        return $fallback->getId();
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
            if ($file !== null && $file->getFileSize() > 0) {
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
    private function fetchExistingMediaByHash(array $deserialized, string $hash): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('metaData.hash', $hash));

        $media = $this->mediaRepository->search($criteria, Context::createDefaultContext())->first();

        if ($media) {
            $deserialized['id'] = $media->getId();
        }

        return $deserialized;
    }
}
