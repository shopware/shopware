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

namespace Shopware\Tests\Unit\Components\Model;

use PHPUnit\Framework\TestCase;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Configurator\Template\Template;
use Shopware\Models\Article\Link;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Tax\Tax;

/**
 * @covers \Shopware\Components\Model\ModelEntity
 *
 * @uses \Shopware\Models\Article\Article
 * @uses \Shopware\Models\Article\Link
 * @uses \Shopware\Models\Article\Supplier
 * @uses \Shopware\Models\Article\Configurator\Template\Template
 * @uses \Shopware\Models\Tax\Tax
 *
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class ModelEntityTest extends TestCase
{
    public function testCanAssignProperties()
    {
        $article = new Article();

        $data = [
            'name' => 'foo',
            'description' => 'bar',
        ];

        $article->fromArray($data);

        $this->assertEquals('foo', $article->getName());
        $this->assertEquals('bar', $article->getDescription());
    }

    public function testCanReAssignProperties()
    {
        $article = new Article();
        $article->setName('lorem');
        $article->setDescription('bar');

        $data = [
            'name' => 'foo',
        ];

        $article->fromArray($data);

        $this->assertEquals('foo', $article->getName());
        $this->assertEquals('bar', $article->getDescription());
    }

    public function testCanAssignOneToOne()
    {
        $article = new Article();

        $data = [
            'configuratorTemplate' => [
                'active' => true,
                'ean' => 'baz',
            ],
        ];

        $article->fromArray($data);

        $this->assertEquals(true, $article->getConfiguratorTemplate()->getActive());
        $this->assertEquals('baz', $article->getConfiguratorTemplate()->getEan());

        // configuratorTemplate is the owning side of relation, so article has to be set
        $this->assertSame($article, $article->getConfiguratorTemplate()->getArticle());
    }

    public function testLoopsArePreventedOneToOne()
    {
        $article = new Article();

        $data = [
            'name' => 'foo',
            'configuratorTemplate' => [
                'ean' => 'foo',
                'article' => [
                    'name' => 'bar',
                ],
            ],
        ];

        $article->fromArray($data);

        $this->assertSame($article, $article->getConfiguratorTemplate()->getArticle());
    }

    public function testCanAssignOneToOneByInstance()
    {
        $article = new Article();

        $tax = new Tax();
        $tax->setName('foobar');

        $template = new Template();
        $template->setEan('foo');

        $data = [
            'tax' => $tax,
            'configuratorTemplate' => $template,
        ];

        $article->fromArray($data);

        $this->assertSame($tax, $article->getTax());
        $this->assertSame($template, $article->getConfiguratorTemplate());
    }

    public function testCanReAssignOneToOne()
    {
        $article = new Article();

        $template = new Template();
        $template->setEan('foo');

        $article->setConfiguratorTemplate($template);

        $data = [
            'configuratorTemplate' => [
                'active' => true,
            ],
        ];

        $article->fromArray($data);

        $this->assertEquals(true, $article->getConfiguratorTemplate()->getActive());
        $this->assertEquals('foo', $article->getConfiguratorTemplate()->getEan());
    }

    public function testCanEmptyArrayDoesNotOverrideOneToOne()
    {
        $article = new Article();

        $template = new Template();
        $template->setEan('foo');
        $template->setActive(true);

        $article->setConfiguratorTemplate($template);

        $data = [
            'configuratorTemplate' => [],
        ];

        $article->fromArray($data);

        $this->assertEquals(true, $article->getConfiguratorTemplate()->getActive());
        $this->assertEquals('foo', $article->getConfiguratorTemplate()->getEan());
    }

    public function testCanRemoveOneToOne()
    {
        $article = new Article();
        $article->setName('Dummy');

        $template = new Template();
        $template->setEan('foo');

        $article->setConfiguratorTemplate($template);
        $template->setArticle($article);

        $data = [
            'configuratorTemplate' => null,
        ];

        $article->fromArray($data);

        $this->assertNull($article->getConfiguratorTemplate());
        $this->assertNull($template->getArticle());
    }

    public function testCanAssignManyToOne()
    {
        $article = new Article();

        $data = [
            'supplier' => [
                'name' => 'foo',
            ],
        ];

        $article->fromArray($data);

        $this->assertEquals('foo', $article->getSupplier()->getName());
    }

    public function testCanAssignManyToOneByInstance()
    {
        $article = new Article();

        $supplier = new Supplier();
        $supplier->setName('test');

        $data = [
            'supplier' => $supplier,
        ];

        $article->fromArray($data);

        $this->assertSame($supplier, $article->getSupplier());
    }

    public function testCanReAssignManyToOne()
    {
        $article = new Article();

        $supplier = new Supplier();
        $supplier->setName('test');
        $supplier->setDescription('description');

        $article->setSupplier($supplier);

        $this->assertSame($supplier, $article->getSupplier());

        $data = [
            'supplier' => [
                'name' => 'foo',
            ],
        ];

        $article->fromArray($data);

        $this->assertEquals('foo', $article->getSupplier()->getName());

        // 19 taxrate shoud be preserved
        $this->assertEquals('description', $article->getSupplier()->getDescription());
    }

    public function testCanEmptyArrayDoesNotOverrideManyToOne()
    {
        $article = new Article();

        $supplier = new Supplier();
        $supplier->setName('test');
        $supplier->setDescription('description');

        $article->setSupplier($supplier);

        $this->assertSame($supplier, $article->getSupplier());

        $data = [
            'supplier' => [],
        ];

        $article->fromArray($data);

        $this->assertEquals('test', $article->getSupplier()->getName());
        $this->assertEquals('description', $article->getSupplier()->getDescription());
    }

    public function testCanRemoveManyToOne()
    {
        $article = new Article();

        $supplier = new Supplier();
        $supplier->setName('test');
        $supplier->setDescription('description');

        $article->setSupplier($supplier);

        $this->assertSame($supplier, $article->getSupplier());

        $data = [
            'supplier' => null,
        ];

        $article->fromArray($data);

        $this->assertEquals(null, $article->getSupplier());
    }

    public function testCanReAssignWithAnotherIdThrowsExceptionManyToOne()
    {
        $article = new Article();

        $supplier = new Supplier();
        $supplier->setName('test');
        $supplier->setDescription('description');
        $this->setProperty($supplier, 'id', 1);

        $article->setSupplier($supplier);

        $this->assertSame($supplier, $article->getSupplier());

        $data = [
            'supplier' => [
                'id' => '2',
                'name' => 'foo',
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $article->fromArray($data);
    }

    public function testCanAssignOneToMany()
    {
        $article = new Article();

        $data = [
            'links' => [
                [
                    'id' => 4,
                    'name' => 'batz',
                ],
                [
                    'name' => 'foobar',
                ],
            ],
        ];

        $article->fromArray($data);

        $this->assertCount(2, $article->getLinks());
    }

    public function testCanAssignOneToManyByInstance()
    {
        $article = new Article();

        $link0 = new Link();
        $link0->setName('dummy');

        $data = [
            'links' => [
                $link0,
                [
                    'name' => 'batz',
                ],
            ],
        ];

        $article->fromArray($data);

        $this->assertCount(2, $article->getLinks());

        $this->assertContains($link0, $article->getLinks());
    }

    public function testCanOverWriteAssignOneToMany()
    {
        $article = new Article();

        $link0 = new Link();
        $link0->setName('dummy');
        $link0->setLink('lorem');

        $article->getLinks()->add($link0);

        $this->assertContains($link0, $article->getLinks());

        $data = [
            'links' => [
                [
                    'name' => 'batz',
                ],
            ],
        ];

        $article->fromArray($data);

        $this->assertCount(1, $article->getLinks());
        $this->assertNotContains($link0, $article->getLinks());

        $this->assertEquals('batz', $article->getLinks()->current()->getName());
    }

    public function testCanRemoveOneToMany()
    {
        $article = new Article();

        $link0 = new Link();
        $link0->setName('dummy');
        $link0->setLink('lorem');

        $article->getLinks()->add($link0);

        $this->assertContains($link0, $article->getLinks());

        $data = [
            'links' => null,
        ];

        $article->fromArray($data);

        $this->assertCount(0, $article->getLinks());
    }

    public function testCanUpdateOneToManyById()
    {
        $article = new Article();

        $link0 = new Link();
        $link0->setName('dummy');
        $link0->setLink('lorem');
        $this->setProperty($link0, 'id', 1);

        $article->getLinks()->add($link0);

        $this->assertContains($link0, $article->getLinks());

        $data = [
            'links' => [
                [
                    'id' => 1,
                    'name' => 'batz',
                ],
                [
                    'name' => 'foo',
                ],
            ],
        ];

        $article->fromArray($data);

        $this->assertCount(2, $article->getLinks());
        $this->assertContains($link0, $article->getLinks());

        $this->assertEquals('batz', $article->getLinks()->first()->getName());
        $this->assertEquals('foo', $article->getLinks()->next()->getName());
    }

    public function testCanUpdateOneToMany()
    {
        $article = new Article();

        $link0 = new Link();
        $link0->setName('dummy');
        $link0->setLink('lorem');
        $this->setProperty($link0, 'id', 1);

        $article->getLinks()->add($link0);

        $this->assertContains($link0, $article->getLinks());

        $data = [
            'links' => [
                [
                    'id' => 2,
                    'name' => 'batz',
                ],
            ],
        ];

        $article->fromArray($data);

        $this->assertCount(1, $article->getLinks());
        $this->assertNotContains($link0, $article->getLinks());

        $this->assertEquals('batz', $article->getLinks()->first()->getName());
    }

    /**
     * @param object $entity
     * @param string $key
     * @param mixed  $value
     */
    protected function setProperty($entity, $key, $value)
    {
        $reflectionClass = new \ReflectionClass($entity);
        $property = $reflectionClass->getProperty($key);

        $property->setAccessible(true);
        $property->setValue($entity, $value);
    }
}
