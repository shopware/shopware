<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Exception\IllegalMimeTypeException;
use Shopware\Core\Content\Media\Util\Strategy\StrategyInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;

class MediaUpdater
{
    const ALLOWED_MIME_TYPES = [
        'image/png' => '.png',
        'image/tiff' => '.tiff',
        'image/jpeg' => '.jpg',
        'image/jpg' => '.jpg',
        'image/gif' => '.gif',
        'image/bmp' => '.bmp',
        'image/svg+xml' => '.svg',

        'video/mpeg' => '.mp4',
        'video/webm' => '.webm',
        'video/ogg' => '.ogv',
        'video/quicktime' => '.mov',
        'video/x-msvideo' => '.avi',

        'audio/mpeg' => '.mp3',
        'audio/webm' => '.webm',
        'audio/ogg' => '.ogg',
        'audio/wav' => '.wav',

        'application/pdf' => '.pdf',
        'application/msword' => '.doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
        'application/vnd.ms-excel' => '.xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
        'application/vnd.ms-powerpoint' => '.ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '.pptx',
    ];

    /** @var RepositoryInterface */
    protected $repository;

    /** @var FilesystemInterface */
    protected $filesystem;

    /** @var StrategyInterface */
    protected $strategy;

    /**
     * @param RepositoryInterface $repository
     * @param FilesystemInterface $filesystem
     * @param StrategyInterface   $strategy
     */
    public function __construct(RepositoryInterface $repository, FilesystemInterface $filesystem, StrategyInterface $strategy)
    {
        $this->repository = $repository;
        $this->filesystem = $filesystem;
        $this->strategy = $strategy;
    }

    /**
     * @param string  $filePath
     * @param string  $mediaId
     * @param string  $mimeType
     * @param int     $fileSize
     * @param Context $context
     */
    public function persistFileToMedia(string $filePath, string $mediaId, string $mimeType, int $fileSize, Context $context)
    {
        if (!in_array($mimeType, array_keys(self::ALLOWED_MIME_TYPES))) {
            throw new IllegalMimeTypeException($mimeType);
        }

        $this->saveFileToMediaDir($filePath, $mediaId, $mimeType);
        $this->updateMediaEntity($mediaId, $mimeType, $fileSize, $context);
    }

    /**
     * @param string $filePath
     * @param string $mediaId
     * @param string $mimeType
     */
    private function saveFileToMediaDir(string $filePath, string $mediaId, string $mimeType): void
    {
        $stream = fopen($filePath, 'r');
        $path = $this->strategy->encode($mediaId);
        try {
            $this->filesystem->putStream('media/' . $path . self::ALLOWED_MIME_TYPES[$mimeType], $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param string $mediaId
     * @param string $mimeType
     * @param int    $fileSize
     * @param $context
     */
    private function updateMediaEntity(string $mediaId, string $mimeType, int $fileSize, $context): void
    {
        $data = [
            'id' => $mediaId,
            'mimeType' => $mimeType,
            'fileSize' => $fileSize,
        ];

        $this->repository->update([$data], $context);
    }
}
