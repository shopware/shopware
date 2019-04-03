<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Context;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Processor;
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
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var RuleCollection
     */
    private $rules;

    /**
     * @var Processor
     */
    private $processor;

    public function __construct(
        CartPersisterInterface $cartPersister,
        Processor $processor,
        EntityRepositoryInterface $repository
    ) {
        $this->cartPersister = $cartPersister;
        $this->repository = $repository;
        $this->processor = $processor;
    }

    public function loadByToken(CheckoutContext $context, string $cartToken): RuleLoaderResult
    {
        try {
            $cart = $this->cartPersister->load($cartToken, $context);
        } catch (CartTokenNotFoundException $e) {
            $cart = new Cart($context->getSalesChannel()->getTypeId(), $cartToken);
        }

        return $this->loadByCart($context, $cart, new CartBehavior());
    }

    public function loadByCart(CheckoutContext $context, Cart $cart, CartBehavior $behaviorContext): RuleLoaderResult
    {
        return $this->load($context, $cart, $behaviorContext);
    }

    private function load(CheckoutContext $context, Cart $cart, CartBehavior $behaviorContext): RuleLoaderResult
    {
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
            $new = $this->processor->process($cart, $context, $behaviorContext);

            if ($this->cartChanged($cart, $new)) {
                $valid = false;
            }

            $cart = $new;

            ++$iteration;
        } while ($valid);

        $context->setRuleIds($rules->getIds());

        return new RuleLoaderResult($cart, $rules);
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

        return $previousLineItems->count() !== $currentLineItems->count()
            || $previous->getPrice()->getTotalPrice() !== $current->getPrice()->getTotalPrice()
            || $previousLineItems->getKeys() !== $currentLineItems->getKeys()
            || $previousLineItems->getTypes() !== $currentLineItems->getTypes()
        ;
    }
}
