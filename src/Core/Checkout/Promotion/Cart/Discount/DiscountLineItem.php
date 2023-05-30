<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class DiscountLineItem
{
    /**
     * @var array<mixed>
     */
    private array $payload;

    private readonly string $scope;

    private readonly string $type;

    private readonly string $filterSorterKey;

    private readonly string $filterApplierKey;

    private readonly string $filterUsageKey;

    private readonly string $filterPickerKey;

    /**
     * @param array<mixed> $payload
     */
    public function __construct(
        private readonly string $label,
        private readonly PriceDefinitionInterface $priceDefinition,
        array $payload,
        private readonly ?string $code
    ) {
        $this->scope = $payload['discountScope'];
        $this->type = $payload['discountType'];
        $this->payload = $payload;

        $this->filterSorterKey = $payload['filter']['sorterKey'] ?? '';
        $this->filterApplierKey = $payload['filter']['applierKey'] ?? '';
        $this->filterUsageKey = $payload['filter']['usageKey'] ?? '';
        $this->filterPickerKey = $payload['filter']['pickerKey'] ?? '';
    }

    /**
     * Gets the text label of the discount item
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Gets the original price definition
     * of this discount item
     */
    public function getPriceDefinition(): PriceDefinitionInterface
    {
        return $this->priceDefinition;
    }

    /**
     * Gets the scope of the discount
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * Gets the type of the discount
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Gets the discount payload data
     *
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @throws CartException
     *
     * @return string|array<mixed>
     */
    public function getPayloadValue(string $key): string|array
    {
        if (!$this->hasPayloadValue($key)) {
            throw CartException::payloadKeyNotFound($key, (string) $this->getCode());
        }

        return $this->payload[$key];
    }

    public function hasPayloadValue(string $key): bool
    {
        return isset($this->payload[$key]);
    }

    /**
     * Gets the code of the discount if existing.
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * Gets the assigned sorter key
     * of the filter settings.
     */
    public function getFilterSorterKey(): string
    {
        return $this->filterSorterKey;
    }

    /**
     * Gets the assigned applier key
     * of the filter settings.
     */
    public function getFilterApplierKey(): string
    {
        return $this->filterApplierKey;
    }

    /**
     * Gets the assigned usage key
     * of the filter settings.
     */
    public function getFilterUsageKey(): string
    {
        return $this->filterUsageKey;
    }

    public function getFilterPickerKey(): string
    {
        return $this->filterPickerKey;
    }
}
