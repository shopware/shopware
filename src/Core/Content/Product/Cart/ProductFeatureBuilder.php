<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

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
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class ProductFeatureBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customFieldRepository,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider
    ) {
    }

    public function prepare(iterable $lineItems, CartDataCollection $data, SalesChannelContext $context): void
    {
        $this->loadCustomFields($lineItems, $data, $context);
    }

    public function add(iterable $lineItems, CartDataCollection $data, SalesChannelContext $context): void
    {
        foreach ($lineItems as $lineItem) {
            $product = $data->get(
                $this->getDataKey($lineItem->getReferencedId())
            );

            if (!($product instanceof SalesChannelProductEntity)) {
                continue;
            }

            $lineItem->replacePayload([
                'features' => $this->buildFeatures($data, $lineItem, $product),
            ]);
        }
    }

    private function buildFeatures(CartDataCollection $data, LineItem $lineItem, SalesChannelProductEntity $product): array
    {
        $features = [];
        $featureSet = $product->getFeatureSet();

        if ($featureSet === null) {
            return $features;
        }

        $sorted = $featureSet->getFeatures();

        if (empty($sorted)) {
            return $features;
        }

        usort($sorted, static fn (array $a, array $b) => $a['position'] <=> $b['position']);

        foreach ($sorted as $feature) {
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

    private function loadCustomFields(iterable $lineItems, CartDataCollection $data, SalesChannelContext $context): void
    {
        $required = [];

        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            $product = $data->get(
                $this->getDataKey((string) $lineItem->getReferencedId())
            );

            if ($product === null || $product->getCustomFields() === null) {
                continue;
            }

            $names = array_keys($product->getCustomFields());

            foreach ($names as $name) {
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
        if ($product->getFeatureSet() === null || $product->getFeatureSet()->getFeatures() === null) {
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

    private function getProperty(string $id, SalesChannelProductEntity $product): ?array
    {
        if ($product->getProperties() === null) {
            return null;
        }

        $group = $product->getProperties()->getGroups()->get($id);

        if ($group === null) {
            return null;
        }

        $properties = $product->getProperties()->fmap(
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

        if (empty($label)) {
            return null;
        }

        return [
            'label' => $label,
            'value' => $properties,
            'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_PROPERTY,
        ];
    }

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
        $label = $this->getCustomFieldLabel($customField);

        if (empty($label)) {
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

    private function getReferencePrice(LineItem $lineItem, SalesChannelProductEntity $product): ?array
    {
        if ($lineItem->getPrice() === null) {
            return null;
        }

        $referencePrice = $lineItem->getPrice()->getReferencePrice();
        $unit = $product->getUnit();

        if ($referencePrice === null || $unit === null) {
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
        if ($customField->getConfig() === null || !\array_key_exists('label', $customField->getConfig())) {
            return null;
        }

        $labels = $customField->getConfig()['label'];
        $localeCode = $this->languageLocaleProvider->getLocaleForLanguageId(Defaults::LANGUAGE_SYSTEM);

        return $labels[$localeCode] ?? null;
    }

    private function getDataKey(string $id): string
    {
        return 'product-' . $id;
    }
}
