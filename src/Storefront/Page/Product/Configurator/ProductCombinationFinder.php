<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductCombinationFinder
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    public function __construct(SalesChannelRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * @throws ProductNotFoundException
     */
    public function find(string $productId, string $wishedGroupId, array $options, SalesChannelContext $salesChannelContext): FoundCombination
    {
        $variantId = $this->searchForOptions($productId, $salesChannelContext, $options);

        if ($variantId !== null) {
            return new FoundCombination($variantId, $options);
        }

        while (\count($options) > 1) {
            foreach ($options as $groupId => $_optionId) {
                if ($groupId !== $wishedGroupId) {
                    unset($options[$groupId]);

                    break;
                }
            }

            $variantId = $this->searchForOptions($productId, $salesChannelContext, $options);

            if ($variantId) {
                return new FoundCombination($variantId, $options);
            }
        }

        throw new ProductNotFoundException($productId);
    }

    private function searchForOptions(
        string $productId,
        SalesChannelContext $salesChannelContext,
        array $options
    ): ?string {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('product.parentId', $productId))
            ->setLimit(1);

        foreach ($options as $optionId) {
            $criteria->addFilter(new EqualsFilter('product.optionIds', $optionId));
        }

        $ids = $this->productRepository->searchIds($criteria, $salesChannelContext)->getIds();

        return array_shift($ids);
    }
}
