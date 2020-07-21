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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Decoratable()
 */
class NavigationLoader implements NavigationLoaderInterface
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

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
        SalesChannelRepositoryInterface $repository,
        EventDispatcherInterface $eventDispatcher,
        AbstractNavigationRoute $navigationRoute
    ) {
        $this->categoryRepository = $repository;
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
        $request->query->set('buildTree', false);
        $request->query->set('depth', $depth);

        $categories = $this->navigationRoute
            ->load($activeId, $rootId, $request, $context, new Criteria())
            ->getCategories();

        $navigation = $this->getTree($rootId, $categories, $categories->get($activeId));

        $event = new NavigationLoadedEvent($navigation, $context);

        $this->eventDispatcher->dispatch($event);

        return $event->getNavigation();
    }

    /**
     * {@inheritdoc}
     *
     * @throws CategoryNotFoundException
     */
    public function loadLevel(string $categoryId, SalesChannelContext $context): Tree
    {
        $active = $this->loadCategories([$categoryId], $context)
            ->get($categoryId);

        if (!$active) {
            throw new CategoryNotFoundException($categoryId);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.parentId', $categoryId));
        $criteria->addAssociation('media');

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($criteria, $context)->getEntities();
        $categories->add($active);

        $navigation = $this->getTree($active->getId(), $categories, $active);

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

    private function loadCategories(array $ids, SalesChannelContext $context): CategoryCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        $criteria->addAssociation('media');

        /** @var CategoryCollection $missing */
        $missing = $this->categoryRepository->search($criteria, $context)->getEntities();

        return $missing;
    }
}
