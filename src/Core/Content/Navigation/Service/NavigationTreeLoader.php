<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation\Service;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Navigation\Exception\NavigationNotFoundException;
use Shopware\Core\Content\Navigation\NavigationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Util\Tree\Tree;
use Shopware\Core\Framework\DataAbstractionLayer\Util\Tree\TreeBuilder;

class NavigationTreeLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $navigationRepository;

    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->navigationRepository = $repository;
    }

    public function read(string $activeId, CheckoutContext $context): ?Tree
    {
        $criteria = new Criteria([$activeId]);

        $active = $this->navigationRepository
            ->search($criteria, $context->getContext())
            ->first();

        if (!$active) {
            throw new NavigationNotFoundException($activeId);
        }

        $parentIds = [$activeId];
        $rootId = $activeId;

        /** @var NavigationEntity $active */
        if ($active->getPath() !== null) {
            $path = array_filter(explode('|', $active->getPath()));

            $parentIds = array_merge($parentIds, $path);

            $rootId = array_shift($path);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('navigation.parentId', $parentIds));

        $navigations = $this->navigationRepository->search($criteria, $context->getContext());
        $navigations->add($active);

        $tree = TreeBuilder::buildTree($rootId, $navigations);

        return new Tree($active, $tree);
    }
}
