<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\StateAwareTrait;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class Cart extends Struct
{
    use StateAwareTrait;

    protected CartPrice $price;

    protected LineItemCollection $lineItems;

    protected ErrorCollection $errors;

    protected DeliveryCollection $deliveries;

    protected TransactionCollection $transactions;

    protected bool $modified = false;

    protected ?string $customerComment = null;

    protected ?string $affiliateCode = null;

    protected ?string $campaignCode = null;

    private ?CartDataCollection $data = null;

    /**
     * @var array<string>
     */
    private array $ruleIds = [];

    private ?CartBehavior $behavior = null;

    /**
     * @internal
     */
    public function __construct(protected string $token)
    {
        $this->lineItems = new LineItemCollection();
        $this->transactions = new TransactionCollection();
        $this->errors = new ErrorCollection();
        $this->deliveries = new DeliveryCollection();
        $this->price = new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS);
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getLineItems(): LineItemCollection
    {
        return $this->lineItems;
    }

    public function setLineItems(LineItemCollection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    public function getErrors(): ErrorCollection
    {
        return $this->errors;
    }

    public function setErrors(ErrorCollection $errors): void
    {
        $this->errors = $errors;
    }

    public function getDeliveries(): DeliveryCollection
    {
        return $this->deliveries;
    }

    public function setDeliveries(DeliveryCollection $deliveries): void
    {
        $this->deliveries = $deliveries;
    }

    /**
     * @throws CartException
     */
    public function addLineItems(LineItemCollection $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            $this->add($lineItem);
        }
    }

    public function addDeliveries(DeliveryCollection $deliveries): void
    {
        foreach ($deliveries as $delivery) {
            $this->deliveries->add($delivery);
        }
    }

    public function addErrors(Error ...$errors): void
    {
        foreach ($errors as $error) {
            $this->errors->add($error);
        }
    }

    public function getPrice(): CartPrice
    {
        return $this->price;
    }

    public function setPrice(CartPrice $price): void
    {
        $this->price = $price;
    }

    /**
     * @throws CartException
     */
    public function add(LineItem $lineItem): self
    {
        $this->lineItems->add($lineItem);

        return $this;
    }

    /**
     * @return LineItem|null
     */
    public function get(string $lineItemKey)
    {
        return $this->lineItems->get($lineItemKey);
    }

    public function has(string $lineItemKey): bool
    {
        return $this->lineItems->has($lineItemKey);
    }

    /**
     * @throws CartException
     */
    public function remove(string $key): void
    {
        $item = $this->get($key);

        if (!$item) {
            throw CartException::lineItemNotFound($key);
        }

        if (!$item->isRemovable()) {
            throw CartException::lineItemNotRemovable($key);
        }

        $this->lineItems->remove($key);
    }

    public function getTransactions(): TransactionCollection
    {
        return $this->transactions;
    }

    public function setTransactions(TransactionCollection $transactions): self
    {
        $this->transactions = $transactions;

        return $this;
    }

    public function getShippingCosts(): CalculatedPrice
    {
        return $this->deliveries->getShippingCosts()->sum();
    }

    public function getData(): CartDataCollection
    {
        if (!$this->data) {
            $this->data = new CartDataCollection();
        }

        return $this->data;
    }

    public function setData(?CartDataCollection $data): void
    {
        $this->data = $data;
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    public function markModified(): void
    {
        $this->modified = true;
    }

    public function markUnmodified(): void
    {
        $this->modified = false;
    }

    public function getCustomerComment(): ?string
    {
        return $this->customerComment;
    }

    public function setCustomerComment(?string $customerComment): void
    {
        $this->customerComment = $customerComment;
    }

    public function getAffiliateCode(): ?string
    {
        return $this->affiliateCode;
    }

    public function setAffiliateCode(?string $affiliateCode): void
    {
        $this->affiliateCode = $affiliateCode;
    }

    public function getCampaignCode(): ?string
    {
        return $this->campaignCode;
    }

    public function setCampaignCode(?string $campaignCode): void
    {
        $this->campaignCode = $campaignCode;
    }

    public function getApiAlias(): string
    {
        return 'cart';
    }

    /**
     * @param array<string> $ruleIds
     */
    public function setRuleIds(array $ruleIds): void
    {
        $this->ruleIds = $ruleIds;
    }

    /**
     * @return array<string>
     */
    public function getRuleIds(): array
    {
        return $this->ruleIds;
    }

    /**
     * Will be available after the cart gets calculated
     * The `\Shopware\Core\Checkout\Cart\Processor::process` will set this
     */
    public function getBehavior(): ?CartBehavior
    {
        return $this->behavior;
    }

    /**
     * @internal These function is reserved for the `\Shopware\Core\Checkout\Cart\Processor::process`
     */
    public function setBehavior(?CartBehavior $behavior): void
    {
        $this->behavior = $behavior;
    }
}
