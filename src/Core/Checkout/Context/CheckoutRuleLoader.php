<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Context;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehaviorContext;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CheckoutRuleLoader
{
    private const MAX_ITERATION = 5;
    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var RuleCollection
     */
    private $rules;

    /**
     * @var CartService
     */
    private $storeFrontCartService;

    /**
     * @var Processor
     */
    private $processor;

    public function __construct(
        CartPersisterInterface $cartPersister,
        Processor $processor,
        CacheItemPoolInterface $cache,
        EntityRepositoryInterface $repository,
        CartService $storeFrontCartService
    ) {
        $this->cartPersister = $cartPersister;
        $this->cache = $cache;
        $this->repository = $repository;
        $this->storeFrontCartService = $storeFrontCartService;
        $this->processor = $processor;
    }

    public function loadMatchingRules(CheckoutContext $context, ?string $cartToken)
    {
        try {
            $cart = $this->cartPersister->load(
                (string) $cartToken,
                $context
            );
        } catch (CartTokenNotFoundException $e) {
            $cart = new Cart($context->getSalesChannel()->getTypeId(), $cartToken);
        }

        $rules = $this->loadRules($context->getContext());

        $rules->sortByPriority();

        $context->setRuleIds($rules->getIds());

        $iteration = 1;

        $valid = true;
        do {
            if ($iteration > self::MAX_ITERATION) {
                break;
            }

            //find rules which matching current cart and context state
            $rules = $rules->filterMatchingRules($cart, $context);

            //place rules into context for further usages
            $context->setRuleIds($rules->getIds());

            //recalculate cart for new context rules
            $new = $this->processor->process($cart, $context, new CartBehaviorContext());

            if ($this->cartChanged($cart, $new)) {
                $valid = false;
            }

            $cart = $new;

            ++$iteration;
        } while ($valid);

        $this->storeFrontCartService->setCart($cart);

        return $rules;
    }

    private function loadRules(Context $context): RuleCollection
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        /** @var RuleCollection $rules */
        $rules = $this->repository->search(new Criteria(), $context)->getEntities();

        /** @var RuleEntity $rule */
        foreach ($rules as $key => $rule) {
            if ($rule->isInvalid() || !$rule->getPayload()) {
                $rules->remove($key);
            }
        }

        return $this->rules = $rules;
    }

    private function cartChanged(Cart $previous, Cart $current): bool
    {
        $previousLineItems = $previous->getLineItems();
        $currentLineItems = $current->getLineItems();

        return
            $previousLineItems->count() !== $currentLineItems->count()
            || $previous->getPrice()->getTotalPrice() !== $current->getPrice()->getTotalPrice()
            || $previousLineItems->getKeys() !== $currentLineItems->getKeys()
            || $previousLineItems->getTypes() !== $currentLineItems->getTypes()
        ;
    }
}
