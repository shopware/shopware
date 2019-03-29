<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Util\Tree;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Util\Tree\TreeAwareInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Util\Tree\TreeBuilder;

class TreeBuilderTest extends TestCase
{
    public function testTreeBuilderwithSimpleTree()
    {
        $treeItems = (new TreeBuilder())->buildTree('1', $this->createSimpleTreeEntityCollection());

        static::assertCount(3, $treeItems);
        static::assertCount(2, $treeItems['1.1']->getChildren());
        static::assertCount(0, $treeItems['1.1']->getChildren()['1.1.1']->getChildren());
        static::assertCount(0, $treeItems['1.1']->getChildren()['1.1.2']->getChildren());
        static::assertCount(2, $treeItems['1.2']->getChildren());
        static::assertCount(1, $treeItems['1.2']->getChildren()['1.2.1']->getChildren());
        static::assertCount(1, $treeItems['1.2']->getChildren()['1.2.2']->getChildren());
        static::assertCount(0, $treeItems['1.3']->getChildren());
    }

    private function createSimpleTreeEntityCollection(): EntityCollection
    {
        return new EntityCollection([
            new TestTreeAware('1.1', '1'),
            new TestTreeAware('1.1.1', '1.1'),
            new TestTreeAware('1.1.2', '1.1'),
            new TestTreeAware('1.2', '1'),
            new TestTreeAware('1.2.1', '1.2'),
            new TestTreeAware('1.2.1.1', '1.2.1'),
            new TestTreeAware('1.2.2', '1.2'),
            new TestTreeAware('1.2.2.1', '1.2.2'),
            new TestTreeAware('1.3', '1'),
        ]);
    }
}

class TestTreeAware extends Entity implements TreeAwareInterface
{
    /**
     * @var string
     */
    private $id;
    /**
     * @var string
     */
    private $parentId;

    public function __construct(string $id, string $parentId)
    {
        $this->id = $id;
        $this->parentId = $parentId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPath(): ?string
    {
        throw new \Exception('Should not be called');
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getUniqueIdentifier(): string
    {
        return $this->getId();
    }
}
