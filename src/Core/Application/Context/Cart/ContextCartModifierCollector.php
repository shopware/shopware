<?php declare(strict_types=1);

namespace Shopware\Application\Context\Cart;

use Shopware\Application\Context\Cart\Struct\ContextCartModifierFetchDefinition;
use Shopware\Application\Context\Repository\ContextCartModifierRepository;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Checkout\Cart\Cart\CartCollectorInterface;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Query\TermsQuery;
use Shopware\Framework\Struct\StructCollection;

class ContextCartModifierCollector implements CartCollectorInterface
{
    /**
     * @var ContextCartModifierRepository
     */
    private $repository;

    public function __construct(ContextCartModifierRepository $contextCartModifierRepository)
    {
        $this->repository = $contextCartModifierRepository;
    }

    public function prepare(
        StructCollection $fetchDefinition,
        Cart $cart,
        StorefrontContext $context
    ): void {
        $contextRuleIds = $context->getContextRuleIds();

        if (!$contextRuleIds) {
            return;
        }

        $fetchDefinition->add(new ContextCartModifierFetchDefinition($contextRuleIds));
    }

    public function fetch(
        StructCollection $dataCollection,
        StructCollection $fetchCollection,
        StorefrontContext $context
    ): void {
        $definitions = $fetchCollection->filterInstance(ContextCartModifierFetchDefinition::class);

        if ($definitions->count() === 0) {
            return;
        }

        $ids = [];
        /** @var ContextCartModifierFetchDefinition[] $definitions */
        foreach ($definitions as $definition) {
            $ids = array_merge($ids, $definition->getContextRuleIds());
        }

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('context_cart_modifier.contextRuleId', $ids));
        $contextCartModifiers = $this->repository->search($criteria, $context->getApplicationContext());

        $dataCollection->add($contextCartModifiers, ContextCartModifierProcessor::TYPE);
    }
}
