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

namespace Shopware\Tests\Functional\Components\Api;

use Shopware\Components\Api\Resource\Media;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class MediaTest extends TestCase
{
    /**
     * @var Media
     */
    protected $resource;

    /**
     * @return Media
     */
    public function createResource()
    {
        return new Media();
    }

    public function testUploadName()
    {
        $data = $this->getSimpleTestData();
        $source = __DIR__ . '/fixtures/test-bild.jpg';
        $dest = __DIR__ . '/fixtures/test-bild-used.jpg';

        //copy image to execute test case multiple times.
        @unlink($dest);
        copy($source, $dest);

        $data['file'] = $dest;
        $path = Shopware()->DocPath('media_image') . 'test-bild-used.jpg';
        $mediaService = Shopware()->Container()->get('shopware_media.media_service');
        if ($mediaService->getFilesystem()->has($path)) {
            $mediaService->getFilesystem()->delete($path);
        }

        $this->resource->create($data);
        $this->assertTrue($mediaService->getFilesystem()->has($path));

        //check if the thumbnails are generated
        $path = Shopware()->DocPath('media_image_thumbnail') . 'test-bild-used_140x140.jpg';
        $this->assertTrue($mediaService->getFilesystem()->has($path));
    }

    public function testUploadNameWithOver50Characters()
    {
        $data = $this->getSimpleTestData();
        $source = __DIR__ . '/fixtures/test-bild.jpg';
        $dest = __DIR__ . '/fixtures/test-bild-with-more-than-50-characaters-more-more-more-more-used.jpg';

        //copy image to execute test case multiple times.
        @unlink($dest);
        copy($source, $dest);

        $data['file'] = $dest;
        $media = $this->resource->create($data);

        $pathPicture = Shopware()->DocPath('media_image') . $media->getFileName();
        $mediaService = Shopware()->Container()->get('shopware_media.media_service');
        $this->assertTrue($mediaService->getFilesystem()->has($pathPicture));

        //check if the thumbnails are generated
        $path = Shopware()->DocPath('media_image_thumbnail') . $media->getName() . '_140x140.jpg';
        $this->assertTrue($mediaService->getFilesystem()->has($path));

        $mediaService->getFilesystem()->delete(Shopware()->DocPath('media_image') . $media->getFileName());
        $mediaService->getFilesystem()->delete($path);
    }

    protected function getSimpleTestData()
    {
        return [
            'album' => -1,
            'description' => 'Test description',
        ];
    }
}
