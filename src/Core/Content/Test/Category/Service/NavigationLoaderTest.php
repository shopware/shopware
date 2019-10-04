<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

class NavigationLoaderTest extends TestCase
{
    public function testTreeBuilderwithSimpleTree(): void
    {
        $loader = new NavigationLoader(
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(EventDispatcher::class)
        );

        $method = ReflectionHelper::getMethod(NavigationLoader::class, 'buildTree');

        /** @var TreeItem[] $treeItems */
        $treeItems = $method->invoke($loader, '1', $this->createSimpleTree());

        static::assertCount(3, $treeItems);
        static::assertCount(2, $treeItems['1.1']->getChildren());
        static::assertCount(0, $treeItems['1.1']->getChildren()['1.1.1']->getChildren());
        static::assertCount(0, $treeItems['1.1']->getChildren()['1.1.2']->getChildren());
        static::assertCount(2, $treeItems['1.2']->getChildren());
        static::assertCount(1, $treeItems['1.2']->getChildren()['1.2.1']->getChildren());
        static::assertCount(1, $treeItems['1.2']->getChildren()['1.2.2']->getChildren());
        static::assertCount(0, $treeItems['1.3']->getChildren());
    }

    private function createSimpleTree(): array
    {
        return [
            new TestTreeAware('1.1', '1'),
            new TestTreeAware('1.1.1', '1.1'),
            new TestTreeAware('1.1.2', '1.1'),
            new TestTreeAware('1.2', '1'),
            new TestTreeAware('1.2.1', '1.2'),
            new TestTreeAware('1.2.1.1', '1.2.1'),
            new TestTreeAware('1.2.2', '1.2'),
            new TestTreeAware('1.2.2.1', '1.2.2'),
            new TestTreeAware('1.3', '1'),
        ];
    }
}

class TestTreeAware extends CategoryEntity
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $parentId;

    public function __construct(string $id, string $parentId)
    {
        $this->id = $id;
        $this->parentId = $parentId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getActive(): bool
    {
        return true;
    }

    public function getVisible(): bool
    {
        return true;
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
