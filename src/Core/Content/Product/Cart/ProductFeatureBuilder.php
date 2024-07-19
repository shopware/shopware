<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class ProductFeatureBuilder
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomFieldCollection> $customFieldRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldRepository,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider
    ) {
    }

    /**
     * @param iterable<LineItem> $lineItems
     */
    public function prepare(iterable $lineItems, CartDataCollection $data, SalesChannelContext $context): void
    {
        $this->loadCustomFields($lineItems, $data, $context);
    }

    /**
     * @param iterable<LineItem> $lineItems
     *
     * @throws CartException
     */
    public function add(iterable $lineItems, CartDataCollection $data, SalesChannelContext $context): void
    {
        foreach ($lineItems as $lineItem) {
            $productId = $lineItem->getReferencedId();
            if ($productId === null) {
                continue;
            }

            $product = $data->get($this->getDataKey($productId));
            if (!$product instanceof SalesChannelProductEntity) {
                continue;
            }

            $lineItem->replacePayload([
                'features' => $this->buildFeatures($data, $lineItem, $product),
            ]);
        }
    }

    /**
     * @throws CartException
     *
     * @return array<int, array{label: string, value: mixed, type: string}>
     */
    private function buildFeatures(CartDataCollection $data, LineItem $lineItem, SalesChannelProductEntity $product): array
    {
        $sortedFeatures = $product->getFeatureSet()?->getFeatures();
        if ($sortedFeatures === null) {
            return [];
        }

        usort($sortedFeatures, static fn (array $a, array $b) => $a['position'] <=> $b['position']);

        $features = [];
        foreach ($sortedFeatures as $feature) {
            if ($feature['type'] === ProductFeatureSetDefinition::TYPE_PRODUCT_ATTRIBUTE) {
                $features[] = $this->getAttribute($feature['name'], $product);

                continue;
            }

            if ($feature['type'] === ProductFeatureSetDefinition::TYPE_PRODUCT_PROPERTY) {
                $features[] = $this->getProperty($feature['id'], $product);

                continue;
            }

            if ($feature['type'] === ProductFeatureSetDefinition::TYPE_PRODUCT_CUSTOM_FIELD) {
                $features[] = $this->getCustomField($feature['name'], $data, $product);

                continue;
            }

            if ($feature['type'] === ProductFeatureSetDefinition::TYPE_PRODUCT_REFERENCE_PRICE) {
                $features[] = $this->getReferencePrice($lineItem, $product);
            }
        }

        return array_filter($features);
    }

    /**
     * @param iterable<LineItem> $lineItems
     */
    private function loadCustomFields(iterable $lineItems, CartDataCollection $data, SalesChannelContext $context): void
    {
        $required = [];

        foreach ($lineItems as $lineItem) {
            $productId = $lineItem->getReferencedId();
            if ($productId === null) {
                continue;
            }

            $product = $data->get($this->getDataKey($productId));
            if (!$product instanceof SalesChannelProductEntity || $product->getCustomFields() === null) {
                continue;
            }

            foreach (array_keys($product->getCustomFields()) as $name) {
                if (!$this->isRequiredCustomField($name, $product)) {
                    continue;
                }

                $key = 'custom-field-' . $name;

                if ($data->has($key)) {
                    // Custom field already loaded
                    continue;
                }

                $required[] = $name;
            }
        }

        if (empty($required)) {
            return;
        }

        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('name', $required));

        $customFields = $this->customFieldRepository->search($criteria, $context->getContext())->getEntities();
        foreach ($customFields as $field) {
            $key = 'custom-field-' . $field->getName();
            $data->set($key, $field);
        }
    }

    /**
     * Checks wether a custom field name is part of the provided product's feature set
     */
    private function isRequiredCustomField(string $name, SalesChannelProductEntity $product): bool
    {
        if ($product->getFeatureSet()?->getFeatures() === null) {
            return false;
        }

        foreach ($product->getFeatureSet()->getFeatures() as $feature) {
            if ($feature['type'] !== ProductFeatureSetDefinition::TYPE_PRODUCT_CUSTOM_FIELD) {
                continue;
            }

            if ($feature['name'] === $name && \array_key_exists($name, $product->getTranslation('customFields'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{label: string, value: mixed, type: string}
     */
    private function getAttribute(string $name, SalesChannelProductEntity $product): array
    {
        $translated = $product->getTranslated();
        $value = $product->get($name);

        if (\array_key_exists($name, $translated)) {
            $value = $translated[$name];
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        return [
            'label' => $name,
            'value' => $value,
            'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_ATTRIBUTE,
        ];
    }

    /**
     * @return ?array{label: string, value: mixed, type: string}
     */
    private function getProperty(string $id, SalesChannelProductEntity $product): ?array
    {
        $properties = $product->getProperties();
        if ($properties === null) {
            return null;
        }

        $group = $properties->getGroups()->get($id);
        if ($group === null) {
            return null;
        }

        $properties = $properties->fmap(
            static function (PropertyGroupOptionEntity $property) use ($id) {
                if ($property->getGroupId() !== $id) {
                    return null;
                }

                return [
                    'id' => $property->getId(),
                    'name' => $property->getTranslation('name'),
                    'mediaId' => $property->getMediaId(),
                    'colorHexCode' => $property->getColorHexCode(),
                ];
            }
        );

        if (empty($properties)) {
            return null;
        }

        $label = $group->getTranslation('name');
        if (!\is_string($label)) {
            return null;
        }

        return [
            'label' => $label,
            'value' => $properties,
            'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_PROPERTY,
        ];
    }

    /**
     * @throws CartException
     *
     * @return array{label: string, value: array{id: string, type: string, content: mixed}, type: string}
     */
    private function getCustomField(string $name, CartDataCollection $data, SalesChannelProductEntity $product): ?array
    {
        $fieldKey = sprintf('custom-field-%s', $name);
        $translation = $product->getTranslation('customFields');

        if ($translation === null || !\array_key_exists($name, $translation)) {
            return null;
        }

        if (!$data->has($fieldKey)) {
            return null;
        }

        $customField = $data->get($fieldKey);
        if (!$customField instanceof CustomFieldEntity) {
            throw CartException::wrongCartDataType($fieldKey, CustomFieldEntity::class);
        }

        $label = $this->getCustomFieldLabel($customField);
        if (!\is_string($label)) {
            return null;
        }

        return [
            'label' => $label,
            'value' => [
                'id' => $customField->getId(),
                'type' => $customField->getType(),
                'content' => $translation[$name],
            ],
            'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_CUSTOM_FIELD,
        ];
    }

    /**
     * @return array{label: string, value: array{price: float, purchaseUnit: float, referenceUnit: float, unitName: ?string}, type: string}
     */
    private function getReferencePrice(LineItem $lineItem, SalesChannelProductEntity $product): ?array
    {
        $referencePrice = $lineItem->getPrice()?->getReferencePrice();
        if ($referencePrice === null) {
            return null;
        }

        $unit = $product->getUnit();
        if ($unit === null) {
            return null;
        }

        return [
            'label' => ProductFeatureSetDefinition::TYPE_PRODUCT_REFERENCE_PRICE,
            'value' => [
                'price' => $referencePrice->getPrice(),
                'purchaseUnit' => $referencePrice->getPurchaseUnit(),
                'referenceUnit' => $referencePrice->getReferenceUnit(),
                'unitName' => $unit->getTranslation('name'),
            ],
            'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_REFERENCE_PRICE,
        ];
    }

    /**
     * Since it's not intended to display custom field labels outside of the admin at the moment,
     * their labels are indexed by the locale code of the system language (fixed value, not translated).
     *
     * @see https://issues.shopware.com/issues/NEXT-9321
     */
    private function getCustomFieldLabel(CustomFieldEntity $customField): ?string
    {
        $localeCode = $this->languageLocaleProvider->getLocaleForLanguageId(Defaults::LANGUAGE_SYSTEM);

        return $customField->getConfig()['label'][$localeCode] ?? null;
    }

    private function getDataKey(string $id): string
    {
        return 'product-' . $id;
    }
}
