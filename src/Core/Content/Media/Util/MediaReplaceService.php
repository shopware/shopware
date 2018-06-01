<?php declare(strict_types=1);
/**
 * Shopware\Core 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Content\Media\Util;

use Shopware\Core\Components\Model\ModelManager;
use Shopware\Core\Components\Thumbnail\Manager;
use Shopware\Core\Content\Media\Exception\ReplaceTypeMismatchException;
use Shopware\Core\Content\Media\Util\Strategy\StrategyFilesystem;
use Shopware\Core\Models\Media\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaReplaceService implements MediaReplaceServiceInterface
{
    /** @var StrategyFilesystem */
    private $mediaService;

    /** @var ModelManager */
    private $modelManager;

    /** @var Manager */
    private $thumbnailManager;

    public function __construct(StrategyFilesystem $mediaService)
    {
        $this->mediaService = $mediaService;
//        $this->thumbnailManager = $thumbnailManager;
//        $this->modelManager = $modelManager;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function replace($mediaId, UploadedFile $file): void
    {
        // todo@next: should be moved to repository
        return;
        $media = $this->modelManager->find(Media::class, $mediaId);

        if (!$this->validateMediaType($media, $file)) {
            throw new ReplaceTypeMismatchException($media->getType());
        }

        $stream = fopen($file->getRealPath(), 'rb+');
        $this->mediaService->getFilesystem()->putStream($media->getPath(), $stream);
        fclose($stream);

        $media->setExtension($file->getClientOriginalExtension());
        $media->setFileSize($file->getSize());

        if ($media->getType() === $media::TYPE_IMAGE) {
            $imageSize = getimagesize($file->getRealPath());

            if ($imageSize) {
                $media->setWidth($imageSize[0]);
                $media->setHeight($imageSize[1]);
            }

            $media->removeThumbnails();
//            $this->thumbnailManager->createMediaThumbnail($media, $media->getDefaultThumbnails(), true);
            $media->createAlbumThumbnails($media->getAlbum());
        }

        $this->modelManager->flush();
    }

    /**
     * @param Media        $media
     * @param UploadedFile $file
     *
     * @return bool
     */
    private function validateMediaType(Media $media, UploadedFile $file): bool
    {
        $fileInfo = pathinfo($file->getClientOriginalName());
        $uploadedFileExtension = strtolower($fileInfo['extension']);
        $types = $media->getTypeMapping();

        if (!array_key_exists($uploadedFileExtension, $types)) {
            $types[$uploadedFileExtension] = Media::TYPE_UNKNOWN;
        }

        return $media->getType() === $types[$uploadedFileExtension];
    }
}
