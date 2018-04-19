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

namespace Shopware\Tests\Unit\Components;

use PHPUnit\Framework\TestCase;
use Shopware\Category\Struct\Category;
use Shopware\Storefront\Context\StorefrontContextService;
use Shopware\Context\Struct\ShopContext;
use Shopware\Shop\Struct\Shop;
use Shopware\Components\QueryAliasMapper;
use Shopware\Components\SeoTemplateSubscriber;

class SeoTemplateSubscriberTest extends TestCase
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @var array
     */
    private $queryParameters = [];

    public function resolveConfig($key)
    {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        return null;
    }

    public function resolveQuery($key)
    {
        if (array_key_exists($key, $this->queryParameters)) {
            return $this->queryParameters[$key];
        }

        return null;
    }

    public function testManufacturerListingQueryBlackList()
    {
        $this->queryParameters = [
            'sCategory' => 4,
            'sSupplier' => 1,
        ];

        $this->config = [
            'sSEOQUERYBLACKLIST' => 's,
sSupplier,
sCategory',
        ];

        $subscriber = new SeoTemplateSubscriber(
            $this->createConfig(),
            new QueryAliasMapper(['sSupplier' => 's']),
            $this->createContextService(4)
        );

        $view = new \Enlight_View_Default(
            new \Enlight_Template_Manager([])
        );

        $event = $this->createEventArgs($view);

        $subscriber->onPostDispatch($event);

        $this->assertEquals(
            [
                'SeoMetaRobots' => 'noindex,follow',
            ],
            $view->getAssign()
        );
    }

    public function testControllerBlacklist()
    {
        $this->queryParameters = [
            'sCategory' => 4,
            'sSupplier' => 1,
        ];

        $this->config = [
            'sSEOVIEWPORTBLACKLIST' => 'listing',
        ];

        $subscriber = new SeoTemplateSubscriber(
            $this->createConfig(),
            new QueryAliasMapper([]),
            $this->createContextService(3)
        );

        $view = new \Enlight_View_Default(
            new \Enlight_Template_Manager([])
        );

        $event = $this->createEventArgs($view);

        $subscriber->onPostDispatch($event);

        $this->assertEquals(
            [
                'SeoMetaRobots' => 'noindex,follow',
            ],
            $view->getAssign()
        );
    }

    public function articleDescriptionProvider()
    {
        return [
            [
                'Meta description',
                ['metaDescription' => 'Meta description'],
            ],
            [
                'Meta description',
                ['metaDescription' => null, 'description' => 'Meta description'],
            ],
            [
                'Meta description',
                ['metaDescription' => null, 'description' => null, 'description_long' => 'Meta description'],
            ],
            [
                'Fragen zum Artikel? Weitere Artikel von Beachdreams Clothes',
                ['metaDescription' => '<ul class="content--list list--unstyled">
                    <li class="list--entry"><a href="http://localhost/anfrage-formular?sInquiry=detail&amp;sOrdernumber=SW10178" rel="nofollow" class="content--link link--contact" title="Fragen zum Artikel?">
                    <i class="icon--arrow-right"></i> Fragen zum Artikel?
                    </a>
                    </li>
                    <li class="list--entry">
                    <a href="http://localhost/beachdreams-clothes/" target="_parent" class="content--link link--supplier" title="Weitere Artikel von Beachdreams Clothes">
                    <i class="icon--arrow-right"></i> Weitere Artikel von Beachdreams Clothes
                    </a>
                    </li>
                    </ul>',
                ],
            ],
        ];
    }

    /**
     * @dataProvider articleDescriptionProvider
     *
     * @param string $expected
     * @param array  $article
     */
    public function testArticleMetaDescription($expected, $article)
    {
        $this->queryParameters = [
            'sCategory' => 4,
            'sSupplier' => 1,
        ];

        $this->config = [
            'sSEOVIEWPORTBLACKLIST' => 'listing',
            'sSEOMETADESCRIPTION' => true,
        ];

        $subscriber = new SeoTemplateSubscriber(
            $this->createConfig(),
            new QueryAliasMapper([]),
            $this->createContextService(3)
        );

        $view = new \Enlight_View_Default(
            new \Enlight_Template_Manager([])
        );
        $view->assign('sArticle', $article);

        $event = $this->createEventArgs($view);

        $subscriber->onPostDispatch($event);

        $this->assertEquals(
            [
                'sArticle' => $article,
                'SeoMetaRobots' => 'noindex,follow',
                'SeoMetaDescription' => $expected,
            ],
            $view->getAssign()
        );
    }

    public function categoryMetaDescriptions()
    {
        return [
            [
                'Meta description',
                ['metaDescription' => 'Meta description'],
            ],
            [
                'Meta description',
                ['cmstext' => 'Meta description'],
            ],
            [
                'Fragen zum Artikel? Weitere Artikel von Beachdreams Clothes',
                ['metaDescription' => '<ul class="content--list list--unstyled">
                    <li class="list--entry"><a href="http://localhost/anfrage-formular?sInquiry=detail&amp;sOrdernumber=SW10178" rel="nofollow" class="content--link link--contact" title="Fragen zum Artikel?">
                    <i class="icon--arrow-right"></i> Fragen zum Artikel?
                    </a>
                    </li>
                    <li class="list--entry">
                    <a href="http://localhost/beachdreams-clothes/" target="_parent" class="content--link link--supplier" title="Weitere Artikel von Beachdreams Clothes">
                    <i class="icon--arrow-right"></i> Weitere Artikel von Beachdreams Clothes
                    </a>
                    </li>
                    </ul>',
                ],
            ],
        ];
    }

    /**
     * @dataProvider categoryMetaDescriptions
     *
     * @param $expected
     * @param $category
     */
    public function testCategoryMetaDescription($expected, $category)
    {
        $this->queryParameters = [
            'sCategory' => 4,
            'sSupplier' => 1,
        ];

        $this->config = [
            'sSEOVIEWPORTBLACKLIST' => 'listing',
            'sSEOMETADESCRIPTION' => true,
        ];

        $subscriber = new SeoTemplateSubscriber(
            $this->createConfig(),
            new QueryAliasMapper([]),
            $this->createContextService(3)
        );

        $view = new \Enlight_View_Default(
            new \Enlight_Template_Manager([])
        );
        $view->assign('sCategoryContent', $category);

        $event = $this->createEventArgs($view);

        $subscriber->onPostDispatch($event);

        $this->assertEquals(
            [
                'sCategoryContent' => $category,
                'SeoMetaRobots' => 'noindex,follow',
                'SeoMetaDescription' => $expected,
            ],
            $view->getAssign()
        );
    }

    private function createConfig()
    {
        $config = $this->createMock(\Shopware_Components_Config::class);

        $config->method('get')
            ->willReturnCallback([$this, 'resolveConfig']);

        return $config;
    }

    /**
     * @param \Enlight_View_Default $view
     *
     * @return \Enlight_Event_EventArgs
     */
    private function createEventArgs(\Enlight_View_Default $view): \Enlight_Event_EventArgs
    {
        $controller = $this->createMock(\Enlight_Controller_Action::class);

        $request = $this->createMock(\Enlight_Controller_Request_RequestHttp::class);
        $request->method('getControllerName')
            ->will($this->returnValue('listing'));

        $request->method('getQuery')
            ->willReturnCallback([$this, 'resolveQuery']);

        $request->method('getActionName')
            ->will($this->returnValue('manufacturer'));

        $controller->method('Request')
            ->will($this->returnValue($request));

        $controller->method('View')
            ->will($this->returnValue($view));

        return new \Enlight_Event_EventArgs([
            'subject' => $controller,
        ]);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function createContextService($categoryId): \PHPUnit_Framework_MockObject_MockObject
    {
        $category = new Category($categoryId, null, [null], 'Test');
        $shop = new Shop();
        $shop->setCategory($category);

        $context = $this->createMock(ShopContext::class);
        $context->method('getShop')
            ->will($this->returnValue($shop));

        $contextService = $this->createMock(StorefrontContextService::class);
        $contextService->method('getShopContext')
            ->will($this->returnValue($context));

        return $contextService;
    }
}
