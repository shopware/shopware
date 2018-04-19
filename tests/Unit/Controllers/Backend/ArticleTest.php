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

namespace Shopware\tests\Unit\Controllers\Backend;

use PHPUnit\Framework\TestCase;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;

class ArticleTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $controller;

    /**
     * @var \ReflectionMethod
     */
    private $prepareNumberSyntaxMethod;

    /**
     * @var \ReflectionMethod
     */
    private $interpretNumberSyntaxMethod;

    protected function setUp()
    {
        $this->controller = $this->createPartialMock(\Shopware_Controllers_Backend_Article::class, []);

        $class = new \ReflectionClass($this->controller);

        $this->prepareNumberSyntaxMethod = $class->getMethod('prepareNumberSyntax');
        $this->prepareNumberSyntaxMethod->setAccessible(true);

        $this->interpretNumberSyntaxMethod = $class->getMethod('interpretNumberSyntax');
        $this->interpretNumberSyntaxMethod->setAccessible(true);
    }

    public function testinterpretNumberSyntax()
    {
        $article = new Article();

        $detail = new Detail();
        $detail->setNumber('SW500');
        $article->setMainDetail($detail);

        $commands = $this->prepareNumberSyntaxMethod->invokeArgs($this->controller, ['{mainDetail.number}.{n}']);

        $result = $this->interpretNumberSyntaxMethod->invokeArgs($this->controller, [
            $article,
            $detail,
            $commands,
            2,
        ]);

        $this->assertSame('SW500.2', $result);
    }
}
