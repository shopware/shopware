<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductCombinationFinder
{
    /**
     * @var SalesChannelRepository
     */
    private $repository;

    public function __construct(SalesChannelRepository $repository)
    {
        $this->repository = $repository;
    }

    public function find(string $productId, string $wishedGroupId, array $options, SalesChannelContext $context): FoundCombination
    {
        $variantId = $this->searchForOptions($productId, $context, $options);

        if ($variantId !== null) {
            return new FoundCombination($variantId, $options);
        }

        while (count($options) > 1) {
            foreach ($options as $groupId => $optionId) {
                if ($groupId !== $wishedGroupId) {
                    unset($options[$groupId]);
                    break;
                }
            }

            $variantId = $this->searchForOptions($productId, $context, $options);

            if ($variantId) {
                return new FoundCombination($variantId, $options);
            }
        }

        throw new ProductNotFoundException($productId);
    }

    private function searchForOptions(string $productId, SalesChannelContext $context, $options): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $productId));
        $criteria->setLimit(1);

        foreach ($options as $optionId) {
            $criteria->addFilter(new EqualsFilter('product.optionIds', $optionId));
        }

        $ids = $this->repository->searchIds($criteria, $context)->getIds();

        return array_shift($ids);
    }
}
