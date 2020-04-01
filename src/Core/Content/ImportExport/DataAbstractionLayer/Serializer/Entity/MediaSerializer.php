<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class MediaSerializer extends EntitySerializer implements EventSubscriberInterface
{
    /**
     * @var FileSaver
     */
    private $fileSaver;

    /**
     * @var MediaService
     */
    private $mediaService;

    /**
     * @var array[]
     */
    private $mediaFiles = [];

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaFolderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    public function __construct(
        MediaService $mediaService,
        FileSaver $fileSaver,
        EntityRepositoryInterface $mediaFolderRepository,
        EntityRepositoryInterface $mediaRepository
    ) {
        $this->mediaService = $mediaService;
        $this->fileSaver = $fileSaver;
        $this->mediaFolderRepository = $mediaFolderRepository;
        $this->mediaRepository = $mediaRepository;
    }

    public function serialize(Config $config, EntityDefinition $definition, $value): iterable
    {
        yield from parent::serialize($config, $definition, $value);
    }

    public function deserialize(Config $config, EntityDefinition $definition, $value)
    {
        $value = is_array($value) ? $value : iterator_to_array($value);
        $deserialized = parent::deserialize($config, $definition, $value);

        if (is_iterable($deserialized)) {
            $deserialized = iterator_to_array($deserialized);
        }

        if (isset($deserialized['id'])) {
            $entityName = $config->get('sourceEntity') ?? $definition->getEntityName();
            $deserialized['mediaFolderId'] = $deserialized['mediaFolderId']
                ?? $this->getMediaFolderId($deserialized['id'], $entityName);
        }

        $url = $value['url'] ?? null;

        if ($url !== null && isset($value['id']) && filter_var($url, FILTER_VALIDATE_URL)) {
            /** @var MediaEntity|null $media */
            $media = $this->mediaRepository->search(new Criteria([$value['id']]), Context::createDefaultContext())->first();
            if ($media === null || $media->getUrl() !== $url) {
                $parsed = parse_url($url);
                $pathInfo = pathinfo($parsed['path']);

                $media = $this->fetchFileFromURL((string) $url, $pathInfo['extension'] ?? '');
                $this->mediaFiles[$deserialized['id']] = [
                    'media' => $media,
                    'destination' => $pathInfo['filename'] ?? $deserialized['id'],
                ];
            }
        }

        return $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === 'media';
    }

    public static function getSubscribedEvents()
    {
        return [
            'media.written' => 'persistMedia',
        ];
    }

    /**
     * @internal
     */
    public function persistMedia(EntityWrittenEvent $event): void
    {
        if (empty($this->mediaFiles)) {
            return;
        }

        $mediaFiles = $this->mediaFiles;
        // prevent recursion
        $this->mediaFiles = [];

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

    private function getMediaFolderId(string $id, $entity): string
    {
        $criteria = new Criteria([$id]);
        /** @var MediaFolderEntity|null $folder */
        $folder = $this->mediaFolderRepository->search($criteria, Context::createDefaultContext())->first();
        if ($folder !== null) {
            return $folder->getId();
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

    private function fetchFileFromURL(string $url, string $extension): MediaFile
    {
        $request = new Request();
        $request->query->set('url', $url);
        $request->query->set('extension', $extension);
        $request->request->set('url', $url);
        $request->request->set('extension', $extension);
        $request->headers->set('content-type', 'application/json');

        return $this->mediaService->fetchFile($request);
    }
}
