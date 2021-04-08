<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class CartRuleLoader
{
    private const MAX_ITERATION = 7;

    private CartPersisterInterface $cartPersister;

    private ?RuleCollection $rules = null;

    private Processor $processor;

    private LoggerInterface $logger;

    private TagAwareAdapterInterface $cache;

    private AbstractRuleLoader $ruleLoader;

    /**
     * @internal (FEATURE_NEXT_14114)
     */
    private TaxDetector $taxDetector;

    public function __construct(
        CartPersisterInterface $cartPersister,
        Processor $processor,
        LoggerInterface $logger,
        TagAwareAdapterInterface $cache,
        AbstractRuleLoader $loader,
        TaxDetector $taxDetector
    ) {
        $this->cartPersister = $cartPersister;
        $this->processor = $processor;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->ruleLoader = $loader;
        $this->taxDetector = $taxDetector;
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

        if (Feature::isActive('FEATURE_NEXT_14114')) {
            $taxState = $this->getCartTaxType($context, $cart->getPrice()->getNetPrice());
            $previous = $context->getTaxState();

            $context->setTaxState($taxState);
            $cart->setData(null);

            $cart = $this->processor->process($cart, $context, $behaviorContext);
            if ($previous !== CartPrice::TAX_STATE_FREE) {
                $context->setTaxState($previous);
            }
        }

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

    /**
     * @internal (FEATURE_NEXT_14114)
     */
    private function getCartTaxType(SalesChannelContext $context, float $cartNetAmount = 0): string
    {
        $currency = $context->getCurrency();
        $currencyTaxFreeAmount = $currency->getTaxFreeFrom();
        $isReachedCurrencyTaxFreeAmount = $currencyTaxFreeAmount > 0 && $cartNetAmount >= $currencyTaxFreeAmount;

        if ($isReachedCurrencyTaxFreeAmount) {
            return CartPrice::TAX_STATE_FREE;
        }

        $countryTaxFreeFrom = $context->getShippingLocation()->getCountry()->getTaxFreeFrom();

        if ($currency->getId() !== Defaults::CURRENCY) {
            $cartNetAmount /= $context->getCurrency()->getFactor();
        }

        // $currencyTaxFreeAmount === 0.0 mean currency taxFreeFrom is disabled
        $isReachedCountryTaxFreeAmount = $currencyTaxFreeAmount === 0.0 && $cartNetAmount >= $countryTaxFreeFrom;

        if ($this->taxDetector->isNetDelivery($context) && $isReachedCountryTaxFreeAmount) {
            return CartPrice::TAX_STATE_FREE;
        }

        if ($this->taxDetector->useGross($context)) {
            return CartPrice::TAX_STATE_GROSS;
        }

        return CartPrice::TAX_STATE_NET;
    }
}
