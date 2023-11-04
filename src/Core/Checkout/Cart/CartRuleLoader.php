<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

#[Package('checkout')]
class CartRuleLoader implements ResetInterface
{
    private const MAX_ITERATION = 7;

    private ?RuleCollection $rules = null;

    /**
     * @var array<string, float>
     */
    private array $currencyFactor = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartPersister $cartPersister,
        private readonly Processor $processor,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly AbstractRuleLoader $ruleLoader,
        private readonly TaxDetector $taxDetector,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function loadByToken(SalesChannelContext $context, string $cartToken): RuleLoaderResult
    {
        try {
            $cart = $this->cartPersister->load($cartToken, $context);

            return $this->load($context, $cart, new CartBehavior($context->getPermissions()), false);
        } catch (CartTokenNotFoundException) {
            $cart = new Cart($cartToken);
            $this->dispatcher->dispatch(new CartCreatedEvent($cart));

            return $this->load($context, $cart, new CartBehavior($context->getPermissions()), true);
        }
    }

    public function loadByCart(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext, bool $isNew = false): RuleLoaderResult
    {
        return $this->load($context, $cart, $behaviorContext, $isNew);
    }

    public function reset(): void
    {
        $this->rules = null;
    }

    public function invalidate(): void
    {
        $this->reset();
        $this->cache->delete(CachedRuleLoader::CACHE_KEY);
    }

    private function load(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext, bool $new): RuleLoaderResult
    {
        return Profiler::trace('cart-rule-loader', function () use ($context, $cart, $behaviorContext, $new) {
            $rules = $this->loadRules($context->getContext());

            // save all rules for later usage
            $all = $rules;

            $ids = $new ? $rules->getIds() : $cart->getRuleIds();

            // update rules in current context
            $context->setRuleIds($ids);

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

            $cart = $this->validateTaxFree($context, $cart, $behaviorContext);

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
                $this->cartPersister->save($cart, $context);
            }

            return new RuleLoaderResult($cart, $rules);
        });
    }

    private function loadRules(Context $context): RuleCollection
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        return $this->rules = $this->ruleLoader->load($context)->filterForContext();
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

    private function detectTaxType(SalesChannelContext $context, float $cartNetAmount = 0): string
    {
        $currency = $context->getCurrency();
        $currencyTaxFreeAmount = $currency->getTaxFreeFrom();
        $isReachedCurrencyTaxFreeAmount = $currencyTaxFreeAmount > 0 && $cartNetAmount >= $currencyTaxFreeAmount;

        if ($isReachedCurrencyTaxFreeAmount) {
            return CartPrice::TAX_STATE_FREE;
        }

        $country = $context->getShippingLocation()->getCountry();

        $isReachedCustomerTaxFreeAmount = $country->getCustomerTax()->getEnabled() && $this->isReachedCountryTaxFreeAmount($context, $country, $cartNetAmount);
        $isReachedCompanyTaxFreeAmount = $this->taxDetector->isCompanyTaxFree($context, $country) && $this->isReachedCountryTaxFreeAmount($context, $country, $cartNetAmount, CountryDefinition::TYPE_COMPANY_TAX_FREE);
        if ($isReachedCustomerTaxFreeAmount || $isReachedCompanyTaxFreeAmount) {
            return CartPrice::TAX_STATE_FREE;
        }

        if ($this->taxDetector->useGross($context)) {
            return CartPrice::TAX_STATE_GROSS;
        }

        return CartPrice::TAX_STATE_NET;
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

    private function isReachedCountryTaxFreeAmount(
        SalesChannelContext $context,
        CountryEntity $country,
        float $cartNetAmount = 0,
        string $taxFreeType = CountryDefinition::TYPE_CUSTOMER_TAX_FREE
    ): bool {
        $countryTaxFreeLimit = $taxFreeType === CountryDefinition::TYPE_CUSTOMER_TAX_FREE ? $country->getCustomerTax() : $country->getCompanyTax();
        if (!$countryTaxFreeLimit->getEnabled()) {
            return false;
        }

        $countryTaxFreeLimitAmount = $countryTaxFreeLimit->getAmount() / $this->fetchCurrencyFactor($countryTaxFreeLimit->getCurrencyId(), $context);

        $currency = $context->getCurrency();

        $cartNetAmount /= $this->fetchCurrencyFactor($currency->getId(), $context);

        // currency taxFreeAmount === 0.0 mean currency taxFreeFrom is disabled
        return $currency->getTaxFreeFrom() === 0.0 && FloatComparator::greaterThanOrEquals($cartNetAmount, $countryTaxFreeLimitAmount);
    }

    private function fetchCurrencyFactor(string $currencyId, SalesChannelContext $context): float
    {
        if ($currencyId === Defaults::CURRENCY) {
            return 1;
        }

        $currency = $context->getCurrency();
        if ($currencyId === $currency->getId()) {
            return $currency->getFactor();
        }

        if (\array_key_exists($currencyId, $this->currencyFactor)) {
            return $this->currencyFactor[$currencyId];
        }

        $currencyFactor = $this->connection->fetchOne(
            'SELECT `factor` FROM `currency` WHERE `id` = :currencyId',
            ['currencyId' => Uuid::fromHexToBytes($currencyId)]
        );

        if (!$currencyFactor) {
            throw new EntityNotFoundException('currency', $currencyId);
        }

        return $this->currencyFactor[$currencyId] = (float) $currencyFactor;
    }

    private function validateTaxFree(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext): Cart
    {
        $totalCartNetAmount = $cart->getPrice()->getPositionPrice();
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $totalCartNetAmount = $totalCartNetAmount - $cart->getLineItems()->getPrices()->getCalculatedTaxes()->getAmount();
        }
        $taxState = $this->detectTaxType($context, $totalCartNetAmount);
        $previous = $context->getTaxState();
        if ($taxState === $previous) {
            return $cart;
        }

        $context->setTaxState($taxState);
        $cart->setData(null);
        $cart = $this->processor->process($cart, $context, $behaviorContext);
        if ($previous !== CartPrice::TAX_STATE_FREE) {
            $context->setTaxState($previous);
        }

        return $cart;
    }
}
