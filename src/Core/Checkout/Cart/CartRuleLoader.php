<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class CartRuleLoader
{
    public const CHECKOUT_RULE_LOADER_CACHE_KEY = 'all-rules';
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

    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    public function __construct(
        CartPersisterInterface $cartPersister,
        Processor $processor,
        EntityRepositoryInterface $repository,
        LoggerInterface $logger,
        TagAwareAdapterInterface $cache
    ) {
        $this->cartPersister = $cartPersister;
        $this->repository = $repository;
        $this->processor = $processor;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    public function loadByToken(SalesChannelContext $context, string $cartToken): RuleLoaderResult
    {
        try {
            $cart = $this->cartPersister->load($cartToken, $context);
        } catch (CartTokenNotFoundException $e) {
            $cart = new Cart($context->getSalesChannel()->getTypeId(), $cartToken);
        }

        return $this->loadByCart($context, $cart, new CartBehavior($context->getPermissions()));
    }

    public function loadByCart(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext): RuleLoaderResult
    {
        return $this->load($context, $cart, $behaviorContext);
    }

    private function load(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext): RuleLoaderResult
    {
        $rules = $this->loadRules($context->getContext());

        $context->setRuleIds($rules->getIds());

        $iteration = 1;

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

            $recalculate = $this->cartChanged($cart, $new);

            $cart = $new;

            ++$iteration;
        } while ($recalculate);

        $index = 0;
        foreach ($rules as $rule) {
            ++$index;
            $this->logger->info(
                sprintf('#%s Rule detection: %s with priority %s (id: %s)', $index, $rule->getName(), $rule->getPriority(), $rule->getId())
            );
        }

        $context->setRuleIds($rules->getIds());

        // save the cart if errors exist, so the errors get persisted
        if ($cart->getErrors()->count() > 0) {
            $this->cartPersister->save($cart, $context);
        }

        return new RuleLoaderResult($cart, $rules);
    }

    private function loadRules(Context $context): RuleCollection
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $item = $this->cache->getItem(self::CHECKOUT_RULE_LOADER_CACHE_KEY);

        $rules = $item->get();
        if ($item->isHit() && $rules) {
            return $this->rules = $rules;
        }

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));

        /** @var RuleCollection $rules */
        $rules = $this->repository
            ->search($criteria, $context)
            ->getEntities();

        foreach ($rules as $key => $rule) {
            if ($rule->isInvalid() || !$rule->getPayload()) {
                $rules->remove($key);
            }
        }

        $item->set($rules);

        $this->cache->save($item);

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
