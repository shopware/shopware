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

namespace Shopware\Components\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Components\Theme\PathResolver;
use Shopware\Models\Shop\Shop;
use Shopware\Models\Shop\Template;

class PathResolverTest extends TestCase
{
    /**
     * @var PathResolver
     */
    private $pathResolver;

    protected function setUp()
    {
        $this->pathResolver = new PathResolver(
            '/my/root/dir',
            [],
            $this->createTemplateManagerMock()
        );
    }

    public function testFiles()
    {
        $timestamp = '200000';
        $templateId = 5;
        $shopId = 4;

        $templateMock = $this->createTemplateMock($templateId);
        $shopMock = $this->createShopMock($shopId, $templateMock);

        $filenameHash = $timestamp . '_' . md5($timestamp . $templateId . $shopId . \Shopware::REVISION);

        $expected = '/my/root/dir/web/cache/' . $filenameHash . '.css';
        $this->assertEquals($expected, $this->pathResolver->getCssFilePath($shopMock, $timestamp));

        $expected = '/my/root/dir/web/cache/' . $filenameHash . '.js';
        $this->assertEquals($expected, $this->pathResolver->getJsFilePath($shopMock, $timestamp));
    }

    /**
     * @return \Enlight_Template_Manager
     */
    private function createTemplateManagerMock()
    {
        return $this->createMock(\Enlight_Template_Manager::class);
    }

    /**
     * @param int $templateId
     *
     * @return Template
     */
    private function createTemplateMock($templateId)
    {
        return $this->createConfiguredMock(Template::class, ['getId' => $templateId]);
    }

    /**
     * @param int      $shopId
     * @param Template $templateStub
     *
     * @return Shop
     */
    private function createShopMock($shopId, $templateStub)
    {
        return $this->createConfiguredMock(Shop::class, [
            'getMain' => null,
            'getid' => $shopId,
            'getTemplate' => $templateStub,
        ]);
    }
}
