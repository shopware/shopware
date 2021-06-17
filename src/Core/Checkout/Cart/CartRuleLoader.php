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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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

    private EventDispatcherInterface $dispatcher;

    /**
     * @internal (FEATURE_NEXT_14114)
     */
    private Connection $connection;

    /**
     * @internal (FEATURE_NEXT_14114)
     */
    private array $currencyFactor = [];

    public function __construct(
        CartPersisterInterface $cartPersister,
        Processor $processor,
        LoggerInterface $logger,
        TagAwareAdapterInterface $cache,
        AbstractRuleLoader $loader,
        TaxDetector $taxDetector,
        Connection $connection,
        EventDispatcherInterface $dispatcher
    ) {
        $this->cartPersister = $cartPersister;
        $this->processor = $processor;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->ruleLoader = $loader;
        $this->taxDetector = $taxDetector;
        $this->dispatcher = $dispatcher;
        $this->connection = $connection;
    }

    public function loadByToken(SalesChannelContext $context, string $cartToken): RuleLoaderResult
    {
        try {
            $cart = $this->cartPersister->load($cartToken, $context);

            return $this->load($context, $cart, new CartBehavior($context->getPermissions()), false);
        } catch (CartTokenNotFoundException $e) {
            $cart = new Cart($context->getSalesChannel()->getTypeId(), $cartToken);
            $this->dispatcher->dispatch(new CartCreatedEvent($cart));

            return $this->load($context, $cart, new CartBehavior($context->getPermissions()), true);
        }
    }

    public function loadByCart(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext): RuleLoaderResult
    {
        return $this->load($context, $cart, $behaviorContext, false);
    }

    public function reset(): void
    {
        $this->rules = null;
        $this->cache->deleteItem(CachedRuleLoader::CACHE_KEY);
    }

    private function load(SalesChannelContext $context, Cart $cart, CartBehavior $behaviorContext, bool $new): RuleLoaderResult
    {
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

        if (Feature::isActive('FEATURE_NEXT_14114')) {
            $totalCartNetAmount = $cart->getPrice()->getPositionPrice();

            if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
                $totalCartNetAmount = $totalCartNetAmount - $cart->getLineItems()->getPrices()->getCalculatedTaxes()->getAmount();
            }

            $taxState = $this->detectTaxType($context, $totalCartNetAmount);
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
        if ($cart->getErrors()->count() > 0 || $this->updated($cart, $timestamps)) {
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

    /**
     * @internal (FEATURE_NEXT_14114)
     */
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

    /**
     * @internal (FEATURE_NEXT_14114)
     */
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
}
