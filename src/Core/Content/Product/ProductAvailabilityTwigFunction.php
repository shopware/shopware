<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Order and cart line items keep referencing a product by id long after it was deactivated or deleted,
 * so templates need to resolve current availability live instead of relying on a loaded association.
 */
#[Package('inventory')]
class ProductAvailabilityTwigFunction extends AbstractExtension
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(private readonly EntityRepository $productRepository)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('sw_product_available', $this->isAvailable(...)),
        ];
    }

    public function isAvailable(?string $productId, Context $context): bool
    {
        if ($productId === null) {
            return false;
        }

        $criteria = (new Criteria([$productId]))->addFilter(new EqualsFilter('active', true));

        return $this->productRepository->searchIds($criteria, $context)->getTotal() > 0;
    }
}
