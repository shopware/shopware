<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Event\MediaFileUploadedEvent;
use Shopware\Core\Content\Media\Exception\FileTypeNotSupportedException;
use Shopware\Core\Content\Media\Exception\IllegalMimeTypeException;
use Shopware\Core\Content\Media\Exception\MediaNotFoundException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Content\Media\Util\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FileSaver
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
     * @throws UploadException
     */
    public function persistFileToMedia(string $filePath, string $mediaId, string $mimeType, string $extension, int $fileSize, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('id', $mediaId));
        if ($mediaEntity = $this->repository->searchIds($criteria, $context)->getTotal() !== 1) {
            throw new MediaNotFoundException($mediaId);
        }

        $this->saveFileToMediaDir($filePath, $mediaId, $extension);
        $this->updateMediaEntity($mediaId, $mimeType, $extension, $fileSize, $context);

        try {
            $this->eventDispatcher->dispatch(
                MediaFileUploadedEvent::EVENT_NAME,
                new MediaFileUploadedEvent($mediaId, $mimeType, $extension, $context)
            );
        } catch (FileTypeNotSupportedException $e) {
            //ignore that a thumbnail was not created
        }
    }

    private function saveFileToMediaDir(string $filePath, string $mediaId, string $extension): void
    {
        $stream = fopen($filePath, 'r');
        $path = $this->urlGenerator->getRelativeMediaUrl($mediaId, $extension);
        try {
            $this->filesystem->putStream($path, $stream);
        } finally {
            fclose($stream);
        }
    }

    private function updateMediaEntity(string $mediaId, string $mimeType, string $extension, int $fileSize, Context $context): void
    {
        $data = [
            'id' => $mediaId,
            'mimeType' => $mimeType,
            'fileExtension' => $extension,
            'fileSize' => $fileSize,
            'thumbnailsCreated' => false,
        ];

        $context->getExtension('write_protection')->set('write_media', true);
        $this->repository->update([$data], $context);
    }
}
