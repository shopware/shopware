<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CountryTaxCalculator
{
    /**
     * @var array<string, float>
     */
    private array $currencyFactor = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly Processor $processor,
        private readonly AbstractTaxDetector $taxDetector,
        private readonly Connection $connection
    ) {
    }

    public function calculate(Cart $cart, SalesChannelContext $context, CartBehavior $behavior): Cart
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

        $cart = $this->processor->process($cart, $context, $behavior);

        if ($previous !== CartPrice::TAX_STATE_FREE) {
            $context->setTaxState($previous);
        }

        return $cart;
    }

    private function detectTaxType(SalesChannelContext $context, float $cartNetAmount = 0): string
    {
        $currencyTaxFreeAmount = $context->getCurrency()->getTaxFreeFrom();

        $isReached = $currencyTaxFreeAmount > 0 && $cartNetAmount >= $currencyTaxFreeAmount;

        if ($isReached) {
            return CartPrice::TAX_STATE_FREE;
        }

        $country = $context->getShippingLocation()->getCountry();

        $isReached = $country->getCustomerTax()->getEnabled() && $this->isReached($context, $country, $cartNetAmount);
        if ($isReached) {
            return CartPrice::TAX_STATE_FREE;
        }

        $isReached = $this->taxDetector->isCompanyTaxFree($context, $country)
            && $this->isReached($context, $country, $cartNetAmount, CountryDefinition::TYPE_COMPANY_TAX_FREE);

        if ($isReached) {
            return CartPrice::TAX_STATE_FREE;
        }

        if ($this->taxDetector->useGross($context)) {
            return CartPrice::TAX_STATE_GROSS;
        }

        return CartPrice::TAX_STATE_NET;
    }

    private function isReached(
        SalesChannelContext $context,
        CountryEntity $country,
        float $cartNetAmount = 0,
        string $taxFreeType = CountryDefinition::TYPE_CUSTOMER_TAX_FREE
    ): bool {
        $freeLimit = $taxFreeType === CountryDefinition::TYPE_CUSTOMER_TAX_FREE ? $country->getCustomerTax() : $country->getCompanyTax();
        if (!$freeLimit->getEnabled()) {
            return false;
        }

        $countryTaxFreeLimitAmount = $freeLimit->getAmount() / $this->fetchCurrencyFactor($freeLimit->getCurrencyId(), $context);

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
}
