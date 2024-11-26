<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Exception\ContextPermissionsLockedException;
use Shopware\Core\System\Tax\Exception\TaxNotFoundException;
use Shopware\Core\System\Tax\TaxCollection;

#[Package('core')]
class SalesChannelContext extends Context
{
    /**
     * Unique token for context, e.g. stored in session or provided in request headers
     *
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $token;

    /**
     * @var SalesChannelEntity
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannel;

    /**
     * @var CurrencyEntity
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $currency;

    /**
     * @var CustomerGroupEntity
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $currentCustomerGroup;

    /**
     * @var TaxCollection
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $taxRules;

    /**
     * @var CustomerEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $customer;

    /**
     * @var PaymentMethodEntity
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $paymentMethod;

    /**
     * @var ShippingMethodEntity
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $shippingMethod;

    /**
     * @var ShippingLocation
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $shippingLocation;

    /**
     * @var array<string, bool>
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $permissions = [];

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $permisionsLocked = false;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $imitatingUserId;

    /**
     * @var Context
     *
     * @deprecated tag:v6.7.0 - Will be removed, use the SalesChannelContext object directly
     */
    protected $context;

    /**
     * @internal
     *
     * @param array<string> $ruleIds
     * @param array<string> $languageIdChain
     * @param array<string, string[]> $areaRuleIds
     */
    public function __construct(
        ContextSource $source,
        string $token,
        private ?string $domainId,
        SalesChannelEntity $salesChannel,
        CurrencyEntity $currency,
        CustomerGroupEntity $currentCustomerGroup,
        TaxCollection $taxRules,
        ?CustomerEntity $customer,
        PaymentMethodEntity $paymentMethod,
        ShippingMethodEntity $shippingMethod,
        ShippingLocation $shippingLocation,
        protected CashRoundingConfig $itemRounding = new CashRoundingConfig(2, 0.01, true),
        protected CashRoundingConfig $totalRounding = new CashRoundingConfig(2, 0.01, true),
        array $ruleIds = [],
        array $languageIdChain = [Defaults::LANGUAGE_SYSTEM],
        string $versionId = Defaults::LIVE_VERSION,
        float $currencyFactor = 1.0,
        bool $considerInheritance = false,
        string $taxState = CartPrice::TAX_STATE_GROSS,
        CashRoundingConfig $rounding = new CashRoundingConfig(2, 0.01, true),
        protected array $areaRuleIds = []
    ) {
        parent::__construct(
            $source,
            $ruleIds,
            $currency->getId(),
            $languageIdChain,
            $versionId,
            $currencyFactor,
            $considerInheritance,
            $taxState,
            $rounding
        );
        $this->token = $token;
        $this->salesChannel = $salesChannel;
        $this->currency = $currency;
        $this->currentCustomerGroup = $currentCustomerGroup;
        $this->taxRules = $taxRules;
        $this->customer = $customer;
        $this->paymentMethod = $paymentMethod;
        $this->shippingMethod = $shippingMethod;
        $this->shippingLocation = $shippingLocation;
    }

    public function getCurrentCustomerGroup(): CustomerGroupEntity
    {
        return $this->currentCustomerGroup;
    }

    public function getCurrency(): CurrencyEntity
    {
        return $this->currency;
    }

    public function getSalesChannel(): SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function getTaxRules(): TaxCollection
    {
        return $this->taxRules;
    }

    /**
     * Get the tax rules depend on the customer billing address
     * respectively the shippingLocation if there is no customer
     */
    public function buildTaxRules(string $taxId): TaxRuleCollection
    {
        $tax = $this->taxRules->get($taxId);

        if ($tax?->getRules() === null) {
            throw new TaxNotFoundException($taxId);
        }

        if ($tax->getRules()->first() !== null) {
            // NEXT-21735 - This is covered randomly
            // @codeCoverageIgnoreStart
            return new TaxRuleCollection([
                new TaxRule($tax->getRules()->first()->getTaxRate(), 100),
            ]);
            // @codeCoverageIgnoreEnd
        }

        return new TaxRuleCollection([
            new TaxRule($tax->getTaxRate(), 100),
        ]);
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function getPaymentMethod(): PaymentMethodEntity
    {
        return $this->paymentMethod;
    }

    public function getShippingMethod(): ShippingMethodEntity
    {
        return $this->shippingMethod;
    }

    public function getShippingLocation(): ShippingLocation
    {
        return $this->shippingLocation;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use the SalesChannelContext object directly
     */
    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'The "context" property is deprecated and will be removed. Use the SalesChannelContext object directly.');

        return $this;
    }

    /**
     * @internal
     *
     * @return array<string, string[]>
     */
    public function getAreaRuleIds(): array
    {
        return $this->areaRuleIds;
    }

    /**
     * @internal
     *
     * @param string[] $areas
     *
     * @return string[]
     */
    public function getRuleIdsByAreas(array $areas): array
    {
        $ruleIds = [];

        foreach ($areas as $area) {
            if (empty($this->areaRuleIds[$area])) {
                continue;
            }

            $ruleIds = array_unique(array_merge($ruleIds, $this->areaRuleIds[$area]));
        }

        return array_values($ruleIds);
    }

    /**
     * @internal
     *
     * @param array<string, string[]> $areaRuleIds
     */
    public function setAreaRuleIds(array $areaRuleIds): void
    {
        $this->areaRuleIds = $areaRuleIds;
    }

    public function lockPermissions(): void
    {
        $this->permisionsLocked = true;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getTaxCalculationType(): string
    {
        return $this->salesChannel->getTaxCalculationType();
    }

    /**
     * @return array<string, bool>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * @param array<string, bool> $permissions
     */
    public function setPermissions(array $permissions): void
    {
        if ($this->permisionsLocked) {
            throw new ContextPermissionsLockedException();
        }

        $this->permissions = array_filter($permissions);
    }

    public function getApiAlias(): string
    {
        return 'sales_channel_context';
    }

    public function hasPermission(string $permission): bool
    {
        return \array_key_exists($permission, $this->permissions) && $this->permissions[$permission];
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannel->getId();
    }

    public function getDomainId(): ?string
    {
        return $this->domainId;
    }

    public function setDomainId(?string $domainId): void
    {
        $this->domainId = $domainId;
    }

    public function getTotalRounding(): CashRoundingConfig
    {
        return $this->totalRounding;
    }

    public function setTotalRounding(CashRoundingConfig $totalRounding): void
    {
        $this->totalRounding = $totalRounding;
    }

    public function getItemRounding(): CashRoundingConfig
    {
        return $this->itemRounding;
    }

    public function setItemRounding(CashRoundingConfig $itemRounding): void
    {
        $this->itemRounding = $itemRounding;
    }

    public function getCurrencyId(): string
    {
        return $this->currency->getId();
    }

    public function ensureLoggedIn(bool $allowGuest = true): void
    {
        if ($this->customer === null) {
            throw CartException::customerNotLoggedIn();
        }

        if (!$allowGuest && $this->customer->getGuest()) {
            throw CartException::customerNotLoggedIn();
        }
    }

    public function getCustomerId(): ?string
    {
        return $this->customer?->getId();
    }

    public function getImitatingUserId(): ?string
    {
        return $this->imitatingUserId;
    }

    public function setImitatingUserId(?string $imitatingUserId): void
    {
        $this->imitatingUserId = $imitatingUserId;
    }

    public function createWithVersionId(string $versionId): self
    {
        $salesChannelContext = new self(
            $this->source,
            $this->token,
            $this->domainId,
            $this->salesChannel,
            $this->currency,
            $this->currentCustomerGroup,
            $this->taxRules,
            $this->customer,
            $this->paymentMethod,
            $this->shippingMethod,
            $this->shippingLocation,
            $this->itemRounding,
            $this->totalRounding,
            $this->ruleIds,
            $this->languageIdChain,
            $versionId,
            $this->currencyFactor,
            $this->considerInheritance,
            $this->taxState,
            $this->rounding,
            $this->areaRuleIds
        );

        $salesChannelContext->scope = $this->scope;

        foreach ($this->getExtensions() as $key => $extension) {
            $salesChannelContext->addExtension($key, $extension);
        }

        return $salesChannelContext;
    }

    /**
     * @template TReturn of mixed
     *
     * @param callable(SalesChannelContext): TReturn $callback
     *
     * @return TReturn the return value of the provided callback function
     */
    public function live(callable $callback): mixed
    {
        $versionId = $this->versionId;

        $this->versionId = Defaults::LIVE_VERSION;

        $result = $callback($this);

        $this->versionId = $versionId;

        return $result;
    }

    public function getCountryId(): string
    {
        return $this->shippingLocation->getCountry()->getId();
    }

    public function getCustomerGroupId(): string
    {
        return $this->currentCustomerGroup->getId();
    }
}
