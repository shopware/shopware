<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Cart;

use Shopware\Core\Checkout\Cart\Cart\CartCollectorInterface;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Core\Framework\Struct\StructCollection;

class DiscountSurchargeCollector implements CartCollectorInterface
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    public function __construct(RepositoryInterface $discountSurchargeRepository)
    {
        $this->repository = $discountSurchargeRepository;
    }

    public function prepare(
        StructCollection $fetchDefinition,
        Cart $cart,
        CheckoutContext $context
    ): void {
        $ruleIds = $context->getRuleIds();

        if (!$ruleIds) {
            return;
        }

        $fetchDefinition->add(new DiscountSurchargeFetchDefinition($ruleIds));
    }

    public function fetch(
        StructCollection $dataCollection,
        StructCollection $fetchCollection,
        CheckoutContext $context
    ): void {
        $definitions = $fetchCollection->filterInstance(DiscountSurchargeFetchDefinition::class);

        if ($definitions->count() === 0) {
            return;
        }

        $ids = [];
        /** @var DiscountSurchargeFetchDefinition[] $definitions */
        foreach ($definitions as $definition) {
            $ids = array_merge($ids, $definition->getRuleIds());
        }

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('discount_surcharge.ruleId', $ids));
        $discountSurcharges = $this->repository->search($criteria, $context->getContext());

        $dataCollection->add($discountSurcharges, DiscountSurchargeProcessor::TYPE);
    }
}
