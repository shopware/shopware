<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityRepositoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class ProductFeatureBuilder
{
    private const CUSTOM_FIELD_KEY_PREFIX = 'custom-field-';

    private const ENTITY_LABEL_KEY_PREFIX = 'custom-field-entity-';

    /**
     * @internal
     *
     * @param EntityRepository<CustomFieldCollection> $customFieldRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldRepository,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {
    }

    /**
     * @param iterable<LineItem> $lineItems
     */
    public function prepare(iterable $lineItems, CartDataCollection $data, SalesChannelContext $context): void
    {
        $products = [];

        foreach ($lineItems as $lineItem) {
            $productId = $lineItem->getReferencedId();
            if ($productId === null) {
                continue;
            }

            $product = $data->get($this->getDataKey($productId));
            if ($product instanceof SalesChannelProductEntity) {
                $products[$productId] = $product;
            }
        }

        $this->loadCustomFields($products, $data, $context);
        $this->loadCustomFieldEntityLabels($products, $data, $context);
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
                'features' => $this->buildFeatures($data, $lineItem, $product, $context),
            ]);
        }
    }

    /**
     * @throws CartException
     *
     * @return array<int, array{label: string, value: mixed, type: string}>
     */
    private function buildFeatures(CartDataCollection $data, LineItem $lineItem, SalesChannelProductEntity $product, SalesChannelContext $context): array
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
                $features[] = $this->getCustomField($feature['name'], $data, $product, $context);

                continue;
            }

            if ($feature['type'] === ProductFeatureSetDefinition::TYPE_PRODUCT_REFERENCE_PRICE) {
                $features[] = $this->getReferencePrice($lineItem, $product);
            }
        }

        return array_filter($features);
    }

    /**
     * @param array<string, SalesChannelProductEntity> $products
     */
    private function loadCustomFields(array $products, CartDataCollection $data, SalesChannelContext $context): void
    {
        $required = [];

        foreach ($products as $product) {
            foreach (array_keys($this->getCustomFieldValues($product)) as $name) {
                if (!$this->isRequiredCustomField($name, $product)) {
                    continue;
                }

                $key = self::CUSTOM_FIELD_KEY_PREFIX . $name;

                if ($data->has($key)) {
                    // Custom field already loaded
                    continue;
                }

                $required[] = $name;
            }
        }

        if ($required === []) {
            return;
        }

        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('name', $required));

        $customFields = $this->customFieldRepository->search($criteria, $context->getContext())->getEntities();
        foreach ($customFields as $field) {
            $key = self::CUSTOM_FIELD_KEY_PREFIX . $field->getName();
            $data->set($key, $field);
        }
    }

    /**
     * Entity select custom fields only store ids, so the label of every referenced entity is resolved
     * once per custom field and kept as an id to label map next to the custom field itself.
     *
     * @param array<string, SalesChannelProductEntity> $products
     */
    private function loadCustomFieldEntityLabels(array $products, CartDataCollection $data, SalesChannelContext $context): void
    {
        $customFields = [];
        $requiredIds = [];

        foreach ($products as $product) {
            foreach ($this->getCustomFieldValues($product) as $name => $content) {
                $customField = $data->get(self::CUSTOM_FIELD_KEY_PREFIX . $name);

                if (!$customField instanceof CustomFieldEntity || $this->getReferencedEntityName($customField) === null) {
                    continue;
                }

                $customFields[$name] = $customField;

                foreach ($this->toStringList($content) as $id) {
                    $requiredIds[$name][$id] = $id;
                }
            }
        }

        foreach ($requiredIds as $name => $ids) {
            $key = self::ENTITY_LABEL_KEY_PREFIX . $name;

            $known = $data->get($key);
            $known = \is_array($known) ? $known : [];

            $missing = array_values(array_diff_key($ids, $known));
            if ($missing === []) {
                continue;
            }

            $data->set($key, $known + $this->fetchEntityLabels($customFields[$name], $missing, $context));
        }
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, ?string>
     */
    private function fetchEntityLabels(CustomFieldEntity $customField, array $ids, SalesChannelContext $context): array
    {
        $labels = array_fill_keys($ids, null);

        $entityName = $this->getReferencedEntityName($customField);
        if ($entityName === null) {
            return $labels;
        }

        $ids = array_values(array_filter($ids, Uuid::isValid(...)));
        if ($ids === []) {
            return $labels;
        }

        try {
            $repository = $this->definitionRegistry->getRepository($entityName);
        } catch (EntityRepositoryNotFoundException) {
            return $labels;
        }

        $properties = $this->getLabelProperties($customField);

        foreach ($repository->search(new Criteria($ids), $context->getContext())->getEntities() as $entity) {
            $labels[$entity->getUniqueIdentifier()] = $this->getEntityLabel($entity, $properties);
        }

        return $labels;
    }

    /**
     * Checks whether a custom field name is part of the provided product's feature set
     */
    private function isRequiredCustomField(string $name, SalesChannelProductEntity $product): bool
    {
        $features = $product->getFeatureSet()?->getFeatures();
        if ($features === null) {
            return false;
        }

        foreach ($features as $feature) {
            if ($feature['type'] !== ProductFeatureSetDefinition::TYPE_PRODUCT_CUSTOM_FIELD) {
                continue;
            }

            if ($feature['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCustomFieldValues(SalesChannelProductEntity $product): array
    {
        $customFields = $product->getTranslation('customFields');
        if (!\is_array($customFields)) {
            return [];
        }

        $values = [];
        foreach ($customFields as $name => $content) {
            $values[(string) $name] = $content;
        }

        return $values;
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

        if ($properties === []) {
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
     * @return array{label: string, value: array{id: string, type: string, content: mixed, display?: list<string>|float}, type: string}|null
     */
    private function getCustomField(string $name, CartDataCollection $data, SalesChannelProductEntity $product, SalesChannelContext $context): ?array
    {
        $fieldKey = self::CUSTOM_FIELD_KEY_PREFIX . $name;
        $translation = $this->getCustomFieldValues($product);

        if (!\array_key_exists($name, $translation)) {
            return null;
        }

        if (!$data->has($fieldKey)) {
            return null;
        }

        $customField = $data->get($fieldKey);
        if (!$customField instanceof CustomFieldEntity) {
            throw CartException::wrongCartDataType($fieldKey, CustomFieldEntity::class);
        }

        $display = $this->getDisplayValue($customField, $translation[$name], $data, $context);
        if ($display === null && $this->needsDisplayValue($customField)) {
            return null;
        }

        $value = [
            'id' => $customField->getId(),
            'type' => $customField->getType(),
            'content' => $translation[$name],
        ];

        if ($display !== null) {
            $value['display'] = $display;
        }

        return [
            'label' => $this->getCustomFieldLabel($customField, $context),
            'value' => $value,
            'type' => ProductFeatureSetDefinition::TYPE_PRODUCT_CUSTOM_FIELD,
        ];
    }

    /**
     * @return array{label: string, value: array{price: float, purchaseUnit: float, referenceUnit: float, unitName: ?string}, type: string}|null
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
     * Select, entity select and price custom fields store raw values that carry no meaning on their own.
     * They are resolved into a rendered representation next to the untouched content of the custom field.
     *
     * @return list<string>|float|null
     */
    private function getDisplayValue(CustomFieldEntity $customField, mixed $content, CartDataCollection $data, SalesChannelContext $context): array|float|null
    {
        if ($customField->getType() === CustomFieldTypes::PRICE) {
            return $this->getPriceValue($content, $context);
        }

        if ($this->getReferencedEntityName($customField) !== null) {
            $values = $this->getEntityValues($customField, $content, $data);

            return $values === [] ? null : $values;
        }

        if ($customField->getType() === CustomFieldTypes::SELECT) {
            $values = $this->getSelectValues($customField, $content, $context);

            return $values === [] ? null : $values;
        }

        return null;
    }

    private function needsDisplayValue(CustomFieldEntity $customField): bool
    {
        return \in_array(
            $customField->getType(),
            [CustomFieldTypes::SELECT, CustomFieldTypes::ENTITY, CustomFieldTypes::PRICE],
            true
        );
    }

    /**
     * @return list<string>
     */
    private function getSelectValues(CustomFieldEntity $customField, mixed $content, SalesChannelContext $context): array
    {
        $options = $customField->getConfig()['options'] ?? null;

        $labels = [];
        foreach (\is_array($options) ? $options : [] as $option) {
            if (!\is_array($option) || !isset($option['value']) || !\is_scalar($option['value'])) {
                continue;
            }

            $label = $option['label'] ?? null;
            $labels[(string) $option['value']] = \is_array($label) ? $label : [];
        }

        $values = [];
        foreach ($this->toStringList($content) as $value) {
            $values[] = $this->getTranslatedLabel($labels[$value] ?? [], $context) ?? $value;
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function getEntityValues(CustomFieldEntity $customField, mixed $content, CartDataCollection $data): array
    {
        $labels = $data->get(self::ENTITY_LABEL_KEY_PREFIX . $customField->getName());
        if (!\is_array($labels)) {
            return [];
        }

        $values = [];
        foreach ($this->toStringList($content) as $id) {
            $label = $labels[$id] ?? null;

            if (\is_string($label) && $label !== '') {
                $values[] = $label;
            }
        }

        return $values;
    }

    private function getPriceValue(mixed $content, SalesChannelContext $context): ?float
    {
        if (!\is_array($content)) {
            return null;
        }

        $prices = new PriceCollection();
        foreach ($content as $price) {
            if (!\is_array($price) || !isset($price['currencyId'], $price['net'], $price['gross'])) {
                continue;
            }

            $prices->add(new Price(
                (string) $price['currencyId'],
                (float) $price['net'],
                (float) $price['gross'],
                (bool) ($price['linked'] ?? false)
            ));
        }

        $price = $prices->getCurrencyPrice($context->getCurrencyId());
        if ($price === null) {
            return null;
        }

        $value = $context->getTaxState() === CartPrice::TAX_STATE_GROSS ? $price->getGross() : $price->getNet();

        if ($price->getCurrencyId() !== $context->getCurrencyId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    private function getReferencedEntityName(CustomFieldEntity $customField): ?string
    {
        if (!\in_array($customField->getType(), [CustomFieldTypes::SELECT, CustomFieldTypes::ENTITY], true)) {
            return null;
        }

        $entityName = $customField->getConfig()['entity'] ?? null;

        return \is_string($entityName) && $entityName !== '' ? $entityName : null;
    }

    /**
     * @return list<string>
     */
    private function getLabelProperties(CustomFieldEntity $customField): array
    {
        $labelProperty = $customField->getConfig()['labelProperty'] ?? null;

        if (\is_string($labelProperty) && $labelProperty !== '') {
            return [$labelProperty];
        }

        if (!\is_array($labelProperty)) {
            return ['name'];
        }

        $properties = array_values(array_filter($labelProperty, \is_string(...)));

        return $properties === [] ? ['name'] : $properties;
    }

    /**
     * @param list<string> $properties
     */
    private function getEntityLabel(Entity $entity, array $properties): ?string
    {
        $parts = [];

        foreach ($properties as $property) {
            $value = $entity->getTranslation($property);

            if (!\is_string($value) && $entity->has($property)) {
                $value = $entity->get($property);
            }

            if (\is_string($value) && $value !== '') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @return list<string>
     */
    private function toStringList(mixed $content): array
    {
        if (\is_scalar($content)) {
            return [(string) $content];
        }

        if (!\is_array($content)) {
            return [];
        }

        return array_values(array_map(strval(...), array_filter($content, \is_scalar(...))));
    }

    /**
     * Custom field labels are not translated by the DAL, they are stored inside the field's config,
     * indexed by locale code. As a label is not required for every language, the language chain of
     * the context is walked, so a label inherits from the parent language and finally from the
     * system language, matching how the DAL resolves translated fields. Without a label for any of
     * them, the technical name is used, the same way a product attribute feature is labelled.
     */
    private function getCustomFieldLabel(CustomFieldEntity $customField, SalesChannelContext $context): string
    {
        $labels = $customField->getConfig()['label'] ?? null;

        return $this->getTranslatedLabel(\is_array($labels) ? $labels : [], $context) ?? $customField->getName();
    }

    /**
     * @param array<string|int, mixed> $labels
     */
    private function getTranslatedLabel(array $labels, SalesChannelContext $context): ?string
    {
        foreach ($context->getLanguageIdChain() as $languageId) {
            $localeCode = $this->languageLocaleProvider->getLocaleForLanguageId($languageId);
            $label = $labels[$localeCode] ?? null;

            if (\is_string($label) && $label !== '') {
                return $label;
            }
        }

        return null;
    }

    private function getDataKey(string $id): string
    {
        return 'product-' . $id;
    }
}
