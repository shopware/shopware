<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class CartRuleLoader
{
    private const MAX_ITERATION = 7;

    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var RuleCollection|null
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

    /**
     * @var AbstractRuleLoader
     */
    private $ruleLoader;

    public function __construct(
        CartPersisterInterface $cartPersister,
        Processor $processor,
        LoggerInterface $logger,
        TagAwareAdapterInterface $cache,
        AbstractRuleLoader $loader
    ) {
        $this->cartPersister = $cartPersister;
        $this->processor = $processor;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->ruleLoader = $loader;
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

    public function reset(): void
    {
        $this->rules = null;
        $this->cache->deleteItem(CachedRuleLoader::CACHE_KEY);
    }

    private function load(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext): RuleLoaderResult
    {
        $rules = $this->loadRules($context->getContext());

        // save all rules for later usage
        $all = $rules;

        // update rules in current context
        $context->setRuleIds($rules->getIds());

        $iteration = 1;

        // start first cart calculation to have all objects enriched
        $cart = $this->processor->process($cart, $context, $behaviorContext);

        do {
            $compare = $cart;

            if ($iteration > self::MAX_ITERATION) {
                break;
            }

            // filter rules which matches to current scope
            $rules = $rules->filterMatchingRules($cart, $context);

            // update matching rules in context
            $context->setRuleIds($rules->getIds());

            // calculate cart again
            $cart = $this->processor->process($cart, $context, $behaviorContext);

            // check if the cart changed, in this case we have to recalculate the cart again
            $recalculate = $this->cartChanged($cart, $compare);

            // check if rules changed for the last calculated cart, in this case we have to recalculate
            $ruleCompare = $all->filterMatchingRules($cart, $context);

            if (!$rules->equals($ruleCompare)) {
                $recalculate = true;
                $rules = $ruleCompare;
            }

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

        return $this->rules = $this->ruleLoader->load($context);
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
