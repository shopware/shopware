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

namespace Shopware\Tests\Functional\Bundle\MediaBundle;

use Shopware\Bundle\MediaBundle\MediaServiceInterface;
use Shopware\Bundle\MediaBundle\Strategy\StrategyInterface;
use Shopware\Models\Shop\Shop;

/**
 * Class FilesystemTest
 */
class FilesystemTest extends \Enlight_Components_Test_TestCase
{
    /**
     * @var MediaServiceInterface
     */
    private $mediaService;

    /**
     * @var StrategyInterface
     */
    private $strategy;

    /**
     * @var array
     */
    private $testPaths = [
        'media/unknown/_phpunit_tmp.json',
        'media/unknown/5a/ef/21/_phpunit_tmp.json',
    ];

    protected function setUp()
    {
        parent::setUp();

        $this->mediaService = Shopware()->Container()->get('shopware_media.media_service');
        $this->strategy = Shopware()->Container()->get('shopware_media.strategy');
    }

    public function testUrlGeneration()
    {
        $file = current($this->testPaths);

        /** @var Shop $shop */
        $shop = Shopware()->Container()->get('models')->getRepository(Shop::class)->getActiveDefault();
        if ($shop->getMain()) {
            $shop = $shop->getMain();
        }

        if ($shop->getSecure()) {
            $baseUrl = 'https://' . $shop->getHost() . $shop->getBasePath() . '/web/';
        } else {
            $baseUrl = 'http://' . $shop->getHost() . $shop->getBasePath() . '/web/';
        }

        $mediaUrl = $baseUrl . $this->strategy->encode($file);

        $this->assertEquals($mediaUrl, $this->mediaService->getUrl($file));
        $this->assertSame('', $this->mediaService->getUrl(''));
    }
}
