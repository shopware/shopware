<?php
/**
 * Shopware 5
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
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

use Shopware\Components\Thumbnail\Manager;
use Shopware\Models\Media\Album;

class Shopware_Tests_Components_Thumbnail_ManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Shopware\Bundle\MediaBundle\MediaServiceInterface
     */
    private $mediaService;

    public function setUp()
    {
        parent::setUp();

        $this->mediaService = Shopware()->Container()->get('shopware_media.media_service');
    }

    public function tearDown()
    {
        parent::tearDown();

        $filesToDelete = [
            'media/unknown/sw_icon.jpg',
            'media/image/sw_icon.jpg',
            'media/image/thumbnail/sw_icon_100x110.jpg',
            'media/image/thumbnail/sw_icon_120x130.jpg',
            'media/image/thumbnail/sw_icon_140x140.jpg',
            'media/image/thumbnail/sw_icon_150x160.jpg',
            'media/image/thumbnail/sw_icon_200x210.jpg',
            'media/image/thumbnail/sw_icon_220x230.jpg',
            'media/image/thumbnail/sw_icon_240x250.jpg',
            'media/image/thumbnail/sw_icon_300x310.jpg',
            'media/image/thumbnail/sw_icon_320x330.jpg',
            'media/image/thumbnail/sw_icon_340x350.jpg',

            'media/unknown/sw_icon.png',
            'media/image/sw_icon.png',
            'media/image/sw_icon_copy.png',
            'media/image/thumbnail/sw_icon_100x110.png',
            'media/image/thumbnail/sw_icon_120x130.png',
            'media/image/thumbnail/sw_icon_140x140.png',
            'media/image/thumbnail/sw_icon_150x160.png',
            'media/image/thumbnail/sw_icon_200x210.png',
            'media/image/thumbnail/sw_icon_220x230.png',
            'media/image/thumbnail/sw_icon_240x250.png',
            'media/image/thumbnail/sw_icon_300x310.png',
            'media/image/thumbnail/sw_icon_320x330.png',
            'media/image/thumbnail/sw_icon_340x350.png',
        ];

        foreach ($filesToDelete as $file) {
            if ($this->mediaService->getFilesystem()->has($file)) {
                $this->mediaService->getFilesystem()->delete($file);
            }
        }
    }

    public function testManagerInstance()
    {
        $manager = Shopware()->Container()->get('thumbnail_manager');
        $this->assertInstanceOf(Manager::class, $manager);
    }

    public function testThumbnailGeneration()
    {
        $manager = Shopware()->Container()->get('thumbnail_manager');

        $media = $this->getMediaModel();

        $sizes = [
            '100x110',
            [120, 130],
            [140],
            [
                'width' => 150,
                'height' => 160,
            ],
        ];

        $manager->createMediaThumbnail($media, $sizes);

        $path = sprintf('media/%s/thumbnail/%s', strtolower($media->getType()), $media->getName());
        $this->assertTrue($this->mediaService->getFilesystem()->has($path . '_100x110.jpg'));
        $this->assertTrue($this->mediaService->getFilesystem()->has($path . '_120x130.jpg'));
        $this->assertTrue($this->mediaService->getFilesystem()->has($path . '_140x140.jpg'));
        $this->assertTrue($this->mediaService->getFilesystem()->has($path . '_150x160.jpg'));
    }

    public function testGenerationWithoutPassedSizes()
    {
        $manager = Shopware()->Container()->get('thumbnail_manager');
        $media = $this->getMediaModel();

        $sizes = [
            '200x210',
            '220x230',
            '240x250',
        ];

        $media->getAlbum()->getSettings()->setThumbnailSize($sizes);
        $manager->createMediaThumbnail($media);

        $path = sprintf('media/%s/thumbnail/%s', strtolower($media->getType()), $media->getName());

        foreach ($sizes as $size) {
            $this->assertTrue($this->mediaService->getFilesystem()->has($path . '_' . $size . '.jpg'));
            $this->assertTrue($this->mediaService->getFilesystem()->has($path . '_' . $size . '.png'));
        }
    }

    public function testGenerationWithoutPassedSizesButProportion()
    {
        $manager = Shopware()->Container()->get('thumbnail_manager');
        $media = $this->getMediaModel();

        $sizes = [
            '300x310',
            '320x330',
            '340x350',
        ];

        $proportionalSizes = [
            '300x298',
            '320x318',
            '340x337',
        ];

        $media->getAlbum()->getSettings()->setThumbnailSize($sizes);
        $manager->createMediaThumbnail($media, [], true);

        $path = sprintf('media/%s/thumbnail/%s', strtolower($media->getType()), $media->getName());

        foreach ($sizes as $key => $size) {
            $this->assertTrue($this->mediaService->getFilesystem()->has($path . '_' . $size . '.jpg'));
            $this->assertTrue($this->mediaService->getFilesystem()->has($path . '_' . $size . '.png'));

            $image = imagecreatefromstring($this->mediaService->getFilesystem()->read($path . '_' . $size . '.jpg'));
            $width = imagesx($image);
            $height = imagesy($image);

            $this->assertSame($proportionalSizes[$key], $width . 'x' . $height);
        }
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage No album configured for the passed media object and no size passed!
     */
    public function testGenerationWithoutAlbum()
    {
        $media = new \Shopware\Models\Media\Media();

        $sourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'sw_icon.png';
        $imagePath = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'sw_icon_copy.png';
        copy($sourcePath, $imagePath);

        $file = new \Symfony\Component\HttpFoundation\File\File($imagePath);

        $media->setFile($file);
        $media->setPath(str_replace(Shopware()->DocPath(), '', $imagePath));

        @unlink($file->getRealPath());

        $manager = Shopware()->Container()->get('thumbnail_manager');
        $manager->createMediaThumbnail($media);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage File is not an image
     */
    public function testGenerationWithEmptyMedia()
    {
        $media = new \Shopware\Models\Media\Media();

        $manager = Shopware()->Container()->get('thumbnail_manager');
        $manager->createMediaThumbnail($media);
    }

    public function testThumbnailCleanUp()
    {
        $media = $this->getMediaModel();

        $defaultSizes = $media->getDefaultThumbnails();
        $defaultSize = $defaultSizes[0];
        $defaultSize = $defaultSize[0] . 'x' . $defaultSize[1];

        $manager = Shopware()->Container()->get('thumbnail_manager');
        $manager->createMediaThumbnail($media, [$defaultSize]);

        $path = sprintf('media/%s/thumbnail/%s', strtolower($media->getType()), $media->getName());

        $this->assertTrue($this->mediaService->getFilesystem()->has($path . '_' . $defaultSize . '.' . $media->getExtension()));

        $manager->removeMediaThumbnails($media);
        $this->assertFalse($this->mediaService->getFilesystem()->has($path . '_' . $defaultSize . '.' . $media->getExtension()));

        $this->mediaService->getFilesystem()->delete($media->getPath());
        $this->assertFalse($this->mediaService->getFilesystem()->has($media->getPath()));
    }

    private function getMediaModel()
    {
        $media = new \Shopware\Models\Media\Media();

        $sourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'sw_icon.png';
        $imagePath = 'media/unknown/sw_icon.png';

        $stream = fopen($sourcePath, 'rb+');
        $this->mediaService->getFilesystem()->putStream($imagePath, $stream);
        fclose($stream);

        $file = new \Symfony\Component\HttpFoundation\File\File($sourcePath);

        $media->setFile($file);
        $media->setAlbumId(-10);
        $media->setAlbum(Shopware()->Models()->find(Album::class, -10));
        $media->setPath(str_replace(Shopware()->DocPath(), '', $imagePath));
        $media->setDescription('');
        $media->setUserId(0);

        return $media;
    }
}
