<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;

/**
 * @package checkout
 */
class DiscountLineItem
{
    private string $label;

    private PriceDefinitionInterface $priceDefinition;

    /**
     * @var array<mixed>
     */
    private array $payload;

    private ?string $code;

    private string $scope;

    private string $type;

    private string $filterSorterKey;

    private string $filterApplierKey;

    private string $filterUsageKey;

    private string $filterPickerKey;

    /**
     * @param array<mixed> $payload
     */
    public function __construct(string $label, PriceDefinitionInterface $priceDefinition, array $payload, ?string $code)
    {
        $this->label = $label;
        $this->priceDefinition = $priceDefinition;
        $this->code = $code;
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
    public function getPayloadValue(string $key)
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
