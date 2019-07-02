<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartRuleLoader
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

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        CartPersisterInterface $cartPersister,
        Processor $processor,
        EntityRepositoryInterface $repository,
        LoggerInterface $logger
    ) {
        $this->cartPersister = $cartPersister;
        $this->repository = $repository;
        $this->processor = $processor;
        $this->logger = $logger;
    }

    public function loadByToken(SalesChannelContext $context, string $cartToken): RuleLoaderResult
    {
        try {
            $cart = $this->cartPersister->load($cartToken, $context);
        } catch (CartTokenNotFoundException $e) {
            $cart = new Cart($context->getSalesChannel()->getTypeId(), $cartToken);
        }

        return $this->loadByCart($context, $cart, new CartBehavior());
    }

    public function loadByCart(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext): RuleLoaderResult
    {
        return $this->load($context, $cart, $behaviorContext);
    }

    private function load(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext): RuleLoaderResult
    {
        $rules = $this->loadRules($context->getContext());

        $rules->sortByPriority();

        $context->setRuleIds($rules->getIds());

        $iteration = 1;

        $recalculate = false;
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
                $recalculate = true;
            }

            $cart = $new;

            ++$iteration;
        } while ($recalculate);

        $index = 0;
        foreach ($this->rules as $rule) {
            ++$index;
            $this->logger->debug(
                sprintf('#%s Rule detection: %s with priority %s', $index, $rule->getName(), $rule->getPriority())
            );
        }

        $context->setRuleIds($rules->getIds());

        return new RuleLoaderResult($cart, $rules);
    }

    private function loadRules(Context $context): RuleCollection
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        /** @var RuleCollection $rules */
        $rules = $this->repository
            ->search(new Criteria(), $context)
            ->getEntities();

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
