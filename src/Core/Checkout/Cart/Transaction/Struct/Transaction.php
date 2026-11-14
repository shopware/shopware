<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Transaction\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class Transaction extends Struct
{
    final public const DEFAULT_IDENTIFIER = 'default';

    protected ?string $identifier = null;

    protected ?Struct $validationStruct = null;

    /**
     * @param string|null $identifier names the transaction within the cart: unique in the cart and the same for the same transaction in the next calculation
     */
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'identifier', newType: 'string', description: 'The parameter loses its null default and becomes required.')]
    public function __construct(
        protected CalculatedPrice $amount,
        protected string $paymentMethodId,
        ?string $identifier = null,
    ) {
        if ($identifier === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Not passing $identifier to ' . static::class . ' is deprecated, it will be required in v6.8.0.0.');
        }

        $this->identifier = $identifier;
    }

    /**
     * Null for transactions built without an identifier, which can not be addressed
     */
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getAmount(): CalculatedPrice
    {
        return $this->amount;
    }

    public function setAmount(CalculatedPrice $amount): void
    {
        $this->amount = $amount;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getValidationStruct(): ?Struct
    {
        return $this->validationStruct;
    }

    public function setValidationStruct(?Struct $validationStruct): void
    {
        $this->validationStruct = $validationStruct;
    }

    public function getApiAlias(): string
    {
        return 'cart_transaction';
    }
}
