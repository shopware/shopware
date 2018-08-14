<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Event\MediaFileUploadedEvent;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\Exception\IllegalMimeTypeException;
use Shopware\Core\Content\Media\Util\MimeType;
use Shopware\Core\Content\Media\Util\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MediaUpdater
{
    /**
     * @var RepositoryInterface
     */
    protected $repository;

    /**
     * @var FilesystemInterface
     */
    protected $filesystem;

    /**
     * @var UrlGeneratorInterface
     */
    protected $urlGenerator;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(
        RepositoryInterface $repository,
        FilesystemInterface $filesystem,
        UrlGeneratorInterface $urlGenerator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->repository = $repository;
        $this->filesystem = $filesystem;
        $this->urlGenerator = $urlGenerator;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws IllegalMimeTypeException
     */
    public function persistFileToMedia(string $filePath, string $mediaId, string $mimeType, int $fileSize, Context $context): void
    {
        if (!MimeType::isSupported($mimeType)) {
            throw new IllegalMimeTypeException($mimeType);
        }

        $this->saveFileToMediaDir($filePath, $mediaId, $mimeType);
        $this->updateMediaEntity($mediaId, $mimeType, $fileSize, $context);

        try {
            $this->eventDispatcher->dispatch(
                MediaFileUploadedEvent::EVENT_NAME,
                new MediaFileUploadedEvent($mediaId, $mimeType, $context)
            );
        } catch (FileTypeNotSupportedException $e) {
            //ignore that a thumbnail was not created
        }
    }

    private function saveFileToMediaDir(string $filePath, string $mediaId, string $mimeType): void
    {
        $stream = fopen($filePath, 'r');
        $path = $this->urlGenerator->getMediaUrl($mediaId, $mimeType, false);
        try {
            $this->filesystem->putStream($path, $stream);
        } finally {
            fclose($stream);
        }
    }

    private function updateMediaEntity(string $mediaId, string $mimeType, int $fileSize, Context $context): void
    {
        $data = [
            'id' => $mediaId,
            'mimeType' => $mimeType,
            'fileSize' => $fileSize,
            'thumbnailsCreated' => false,
        ];

        $context->getExtension('write_protection')->set('write_media', true);
        $this->repository->update([$data], $context);
    }
}
