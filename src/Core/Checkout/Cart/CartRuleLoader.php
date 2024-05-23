<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Tax\CountryTaxCalculator;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @deprecated tag:v6.7.0 - #cache_rework_rule_reason#
 */
#[Package('checkout')]
class CartRuleLoader implements ResetInterface
{
    private const MAX_ITERATION = 7;

    private ?RuleCollection $rules = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartPersister $persister,
        private readonly Processor $processor,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly AbstractRuleLoader $ruleLoader,
        private readonly CartFactory $cartFactory,
        private readonly CountryTaxCalculator $taxCalculator
    ) {
    }

    public function loadByToken(SalesChannelContext $context, string $cartToken): RuleLoaderResult
    {
        // todo@skroblin deprecate
        // todo@skroblin upgrade guide
        try {
            $cart = $this->persister->load($cartToken, $context);

            return $this->load($context, $cart, new CartBehavior($context->getPermissions()), false);
        } catch (CartTokenNotFoundException) {
            $cart = $this->cartFactory->createNew($cartToken);

            return $this->load($context, $cart, new CartBehavior($context->getPermissions()), true);
        }
    }

    public function loadByCart(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext, bool $isNew = false): RuleLoaderResult
    {
        return $this->load($context, $cart, $behaviorContext, $isNew);
    }

    public function reset(): void
    {
        if (Feature::isActive('cache_rework')) {
            return;
        }
        $this->rules = null;
    }

    public function invalidate(): void
    {
        if (Feature::isActive('cache_rework')) {
            return;
        }

        $this->reset();
        $this->cache->delete(CachedRuleLoader::CACHE_KEY);
    }

    private function load(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext, bool $new): RuleLoaderResult
    {
        if (Feature::isActive('cache_rework')) {
            return new RuleLoaderResult(cart: $cart, matchingRules: new RuleCollection());
        }

        return Profiler::trace('cart-rule-loader', function () use ($context, $cart, $behaviorContext, $new) {
            $rules = $this->loadRules($context->getContext());

            // save all rules for later usage
            $all = $rules;

            // For existing carts filter rules to only contain the rules from the current cart
            if ($new === false) {
                $rules = $rules->filter(
                    fn (RuleEntity $rule) => \in_array($rule->getId(), $cart->getRuleIds(), true)
                );
            }

            // update rules in current context
            $context->setRuleIds($rules->getIds());
            $context->setAreaRuleIds($rules->getIdsByArea());

            $iteration = 1;

            $timestamps = $cart->getLineItems()->fmap(function (LineItem $lineItem) {
                if ($lineItem->getDataTimestamp() === null) {
                    return null;
                }

                return $lineItem->getDataTimestamp()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            });

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
                $context->setAreaRuleIds($rules->getIdsByArea());

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

            $cart = $this->taxCalculator->calculate($cart, $context, $behaviorContext);

            $index = 0;
            foreach ($rules as $rule) {
                ++$index;
                $this->logger->info(
                    sprintf('#%d Rule detection: %s with priority %d (id: %s)', $index, $rule->getName(), $rule->getPriority(), $rule->getId())
                );
            }

            $context->setRuleIds($rules->getIds());
            $context->setAreaRuleIds($rules->getIdsByArea());

            // save the cart if errors exist, so the errors get persisted
            if ($cart->getErrors()->count() > 0 || $this->updated($cart, $timestamps)) {
                $this->persister->save($cart, $context);
            }

            return new RuleLoaderResult($cart, $rules);
        });
    }

    private function loadRules(Context $context): RuleCollection
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        return $this->rules = $this->ruleLoader
            ->load($context)
            ->filterForContext();
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

    /**
     * @param array<string, string> $timestamps
     */
    private function updated(Cart $cart, array $timestamps): bool
    {
        foreach ($cart->getLineItems() as $lineItem) {
            if (!isset($timestamps[$lineItem->getId()])) {
                return true;
            }

            $original = $timestamps[$lineItem->getId()];

            $timestamp = $lineItem->getDataTimestamp() !== null ? $lineItem->getDataTimestamp()->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null;

            if ($original !== $timestamp) {
                return true;
            }
        }

        return \count($timestamps) !== $cart->getLineItems()->count();
    }
}
