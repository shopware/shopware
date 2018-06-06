<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Cart;


use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeRepository;
use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Cart\Cart\CartCollectorInterface;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Core\Framework\Struct\StructCollection;

class DiscountSurchargeCollector implements CartCollectorInterface
{
    /**
     * @var \Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeRepository
     */
    private $repository;

    public function __construct(DiscountSurchargeRepository $discountSurchargeRepository)
    {
        $this->repository = $discountSurchargeRepository;
    }

    public function prepare(
        StructCollection $fetchDefinition,
        Cart $cart,
        CustomerContext $context
    ): void {
        $contextRuleIds = $context->getContextRuleIds();

        if (!$contextRuleIds) {
            return;
        }

        $fetchDefinition->add(new DiscountSurchargeFetchDefinition($contextRuleIds));
    }

    public function fetch(
        StructCollection $dataCollection,
        StructCollection $fetchCollection,
        CustomerContext $context
    ): void {
        $definitions = $fetchCollection->filterInstance(DiscountSurchargeFetchDefinition::class);

        if ($definitions->count() === 0) {
            return;
        }

        $ids = [];
        /** @var DiscountSurchargeFetchDefinition[] $definitions */
        foreach ($definitions as $definition) {
            $ids = array_merge($ids, $definition->getContextRuleIds());
        }

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('discount_surcharge.contextRuleId', $ids));
        $discountSurcharges = $this->repository->search($criteria, $context->getContext());

        $dataCollection->add($discountSurcharges, DiscountSurchargeProcessor::TYPE);
    }
}
