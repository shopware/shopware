<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\ExtendableInterface;
use Shopware\Core\Framework\Struct\ExtendableTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @deprecated tag:v6.7.0 - will be removed, PaymentTransactionStruct instead with new payment handlers
 */
#[Package('checkout')]
class SyncPaymentTransactionStruct implements \JsonSerializable, ExtendableInterface
{
    use CloneTrait;
    use ExtendableTrait {
        addExtension as private traitAddExtension;
        addArrayExtension as private traitAddArrayExtension;
        addExtensions as private traitAddExtensions;
        getExtension as private traitGetExtension;
        getExtensionOfType as private traitGetExtensionOfType;
        hasExtension as private traitHasExtension;
        hasExtensionOfType as private traitHasExtensionOfType;
        getExtensions as private traitGetExtensions;
        setExtensions as private traitSetExtensions;
        removeExtension as private traitRemoveExtension;
    }
    use JsonSerializableTrait {
        jsonSerialize as private traitJsonSerialize;
        convertDateTimePropertiesToJsonStringRepresentation as private traitConvertDateTimePropertiesToJsonStringRepresentation;
    }

    public function __construct(
        protected OrderTransactionEntity $orderTransaction,
        protected OrderEntity $order,
        protected ?RecurringDataStruct $recurring = null
    ) {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');

        return $this->orderTransaction;
    }

    public function getOrder(): OrderEntity
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');

        return $this->order;
    }

    public function getRecurring(): ?RecurringDataStruct
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');

        return $this->recurring;
    }

    public function isRecurring(): bool
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');

        return $this->recurring !== null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');

        return $this->traitJsonSerialize();
    }

    /**
     * @param array<string, mixed> $array
     */
    protected function convertDateTimePropertiesToJsonStringRepresentation(array &$array): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');
        $this->traitConvertDateTimePropertiesToJsonStringRepresentation($array);
    }

    public function addExtension(string $name, Struct $extension): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');
        $this->traitAddExtension($name, $extension);
    }

    /**
     * @param array<string|int, mixed> $extension
     */
    public function addArrayExtension(string $name, array $extension): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');
        $this->traitAddArrayExtension($name, $extension);
    }

    /**
     * @param Struct[] $extensions
     */
    public function addExtensions(array $extensions): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');
        $this->traitAddExtensions($extensions);
    }

    public function getExtension(string $name): ?Struct
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');

        return $this->traitGetExtension($name);
    }

    /**
     * @template T of Struct
     *
     * @param class-string<T> $type
     *
     * @return T|null
     */
    public function getExtensionOfType(string $name, string $type): ?Struct
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');

        return $this->traitGetExtensionOfType($name, $type);
    }

    public function hasExtension(string $name): bool
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');

        return $this->traitHasExtension($name);
    }

    public function hasExtensionOfType(string $name, string $type): bool
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');

        return $this->traitHasExtensionOfType($name, $type);
    }

    /**
     * @return Struct[]
     */
    public function getExtensions(): array
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');

        return $this->traitGetExtensions();
    }

    /**
     * @param Struct[] $extensions
     */
    public function setExtensions(array $extensions): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');
        $this->traitSetExtensions($extensions);
    }

    public function removeExtension(string $name): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The specific payment structs will be removed, use PaymentTransactionStruct instead with new payment handlers');
        $this->traitRemoveExtension($name);
    }
}
