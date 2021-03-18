<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Event\NavigationLoadedEvent;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\AbstractNavigationRoute;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Decoratable()
 */
class NavigationLoader implements NavigationLoaderInterface
{
    /**
     * @var TreeItem
     */
    private $treeItem;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractNavigationRoute
     */
    private $navigationRoute;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        AbstractNavigationRoute $navigationRoute
    ) {
        $this->treeItem = new TreeItem(null, []);
        $this->eventDispatcher = $eventDispatcher;
        $this->navigationRoute = $navigationRoute;
    }

    /**
     * {@inheritdoc}
     *
     * @throws CategoryNotFoundException
     */
    public function load(string $activeId, SalesChannelContext $context, string $rootId, int $depth = 2): Tree
    {
        $request = new Request();
        $request->query->set('buildTree', 'false');
        $request->query->set('depth', (string) $depth);

        $criteria = new Criteria();
        $criteria->setTitle('header::navigation');

        $categories = $this->navigationRoute
            ->load($activeId, $rootId, $request, $context, $criteria)
            ->getCategories();

        $navigation = $this->getTree($rootId, $categories, $categories->get($activeId));

        $event = new NavigationLoadedEvent($navigation, $context);

        $this->eventDispatcher->dispatch($event);

        return $event->getNavigation();
    }

    private function getTree(?string $parentId, CategoryCollection $categories, ?CategoryEntity $active): Tree
    {
        $tree = $this->buildTree($parentId, $categories->getElements());

        return new Tree($active, $tree);
    }

    /**
     * @param CategoryEntity[] $categories
     *
     * @return TreeItem[]
     */
    private function buildTree(?string $parentId, array $categories): array
    {
        $children = new CategoryCollection();
        foreach ($categories as $key => $category) {
            if ($category->getParentId() !== $parentId) {
                continue;
            }

            unset($categories[$key]);

            $children->add($category);
        }

        $children->sortByPosition();

        $items = [];
        foreach ($children as $child) {
            if (!$child->getActive() || !$child->getVisible()) {
                continue;
            }

            $item = clone $this->treeItem;
            $item->setCategory($child);

            $item->setChildren(
                $this->buildTree($child->getId(), $categories)
            );

            $items[$child->getId()] = $item;
        }

        return $items;
    }
}
