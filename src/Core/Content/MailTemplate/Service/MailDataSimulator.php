<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Cms\DataAbstractionLayer\Field\SlotConfigField;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Service\Event\MailDataSimulatorFieldEvent;
use Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsField;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\MailFlowDataProviderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BreadcrumbField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CronIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TaxFreeConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeBreadcrumbField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VariantListingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldType\DateInterval;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class MailDataSimulator
{
    /**
     * @var array<string, MailFlowDataProviderInterface<Entity>>
     */
    private array $dataProviders;

    /**
     * @param iterable<string, MailFlowDataProviderInterface<Entity>> $dataProviders
     */
    public function __construct(
        private readonly BusinessEventCollector $businessEventCollector,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
        iterable $dataProviders,
        private readonly ClockInterface $clock,
    ) {
        $this->dataProviders = $dataProviders instanceof \Traversable ? iterator_to_array($dataProviders) : $dataProviders;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplateData(
        string $flowEvent,
        Context $context,
        ?SalesChannelEntity $salesChannel = null,
    ): array {
        $definition = $this->businessEventCollector->collect($context)->get($flowEvent);
        if ($definition === null) {
            return [];
        }

        $eventClass = $definition->getClass();
        if (!class_exists($eventClass)) {
            return [];
        }

        if (!is_a($eventClass, MailAware::class, true)) {
            return [];
        }

        $eventData = $definition->getData();

        $templateData = [];
        $entityCache = [];

        $templateData['salesChannel'] = $salesChannel ?? $this->getEntityData(
            SalesChannelDefinition::class,
            $this->dataProviders[SalesChannelDefinition::ENTITY_NAME]->getCriteria('mail template test id', $context),
            $entityCache,
            $context
        );

        foreach ($eventData as $name => $type) {
            if (\array_key_exists($name, $templateData)) {
                continue;
            }

            $templateData[$name] = $this->generateEventDataTypeData($type, $entityCache, $context);
        }

        return $templateData;
    }

    /**
     * @param array<string,mixed> $dataType
     * @param array<string, Entity> $entityCache
     */
    private function generateEventDataTypeData(array $dataType, array &$entityCache, Context $context): mixed
    {
        if ($dataType['type'] === ArrayType::TYPE) {
            return [];
        }

        if ($dataType['type'] === EntityCollectionType::TYPE || $dataType['type'] === EntityType::TYPE) {
            $definition = $this->definitionRegistry->getByClassOrEntityName($dataType['entityClass']);

            $dataProvider = $this->dataProviders[$definition->getEntityName()] ?? null;

            return $this->getEntityData(
                $definition,
                $dataProvider?->getCriteria('mail template test id', $context),
                $entityCache,
                $context
            );
        }

        if ($dataType['type'] === ObjectType::TYPE) {
            return array_map(function ($value) use ($entityCache, $context) {
                return $this->generateEventDataTypeData($value, $entityCache, $context);
            }, $dataType['data'] ?? []);
        }

        if (\in_array($dataType['type'], ScalarValueType::VALID_TYPES, true)) {
            switch ($dataType['type']) {
                case ScalarValueType::TYPE_BOOL:
                    return Random::getBoolean();
                case ScalarValueType::TYPE_FLOAT:
                    // Cast first: int / int is an int in PHP when evenly divisible (e.g. 982400 / 100 === 9824).
                    return (float) Random::getInteger(100, 1000000) / 100;
                case ScalarValueType::TYPE_INT:
                    return Random::getInteger(0, 100000);
                case ScalarValueType::TYPE_STRING:
                    return 'Lorem ipsum dolor';
            }
        }

        throw MailTemplateException::unknownEventDataType($dataType['type']);
    }

    /**
     * @param array<string, Entity> $entityCache
     */
    private function getEntityData(
        EntityDefinition|string $definition,
        ?Criteria $criteria,
        array &$entityCache,
        Context $context
    ): Entity {
        if (\is_string($definition)) {
            $definition = $this->definitionRegistry->getByClassOrEntityName($definition);
        }

        $cacheKey = $this->getEntityCacheKey($definition);

        if (!\array_key_exists($cacheKey, $entityCache)) {
            $this->generateEntityData($definition, $entityCache, $context);
        }

        $cachedEntity = $entityCache[$cacheKey];

        $entity = $this->getEntity($definition);

        $fields = $definition->getFields();

        foreach ($fields as $field) {
            $entity->assign([$field->getPropertyName() => $this->getEntityField(
                $definition,
                $field,
                $cachedEntity,
                $criteria,
                $entityCache,
                $context
            )]);
        }

        return $entity;
    }

    /**
     * @param array<string, Entity> $entityCache
     */
    private function getEntityField(
        EntityDefinition $definition,
        Field $field,
        Entity $cachedEntity,
        ?Criteria $criteria,
        array &$entityCache,
        Context $context
    ): mixed {
        $propertyName = $field->getPropertyName();

        if (
            $field->getFlag(ApiAware::class) === null
            || (
                $field instanceof AssociationField
                && !$field->getAutoload()
                && ($criteria === null || !$criteria->hasAssociation($propertyName))
            )
        ) {
            return null;
        }

        if ($propertyName === EntityDefinition::TRANSLATED_FIELD) {
            $translatedFields = $definition->getTranslatedFields();

            $data = [];

            foreach ($translatedFields as $translatedField) {
                $data[$translatedField->getPropertyName()] = $this->getEntityField(
                    $definition,
                    $translatedField,
                    $cachedEntity,
                    $criteria?->getAssociation(EntityDefinition::TRANSLATED_FIELD),
                    $entityCache,
                    $context
                );
            }

            return $data;
        } elseif ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
            $referenceDefinition = $field instanceof ManyToManyAssociationField ? $field->getToManyReferenceDefinition() : $field->getReferenceDefinition();

            $entity = $this->getEntityData(
                $referenceDefinition,
                $criteria?->getAssociation($propertyName),
                $entityCache,
                $context
            );
            $this->ensureEntityIdentifier($entity);

            $collection = $this->getCollection($referenceDefinition);
            $collection->add($entity);

            return $collection;
        } elseif ($field instanceof AssociationField) {
            return $this->getEntityData(
                $field->getReferenceDefinition(),
                $criteria?->getAssociation($propertyName),
                $entityCache,
                $context
            );
        }

        return $cachedEntity->get($propertyName);
    }

    /**
     * @param array<string, Entity> $entityCache
     */
    private function generateEntityData(
        EntityDefinition|string $definition,
        array &$entityCache,
        Context $context
    ): Entity {
        if (\is_string($definition)) {
            $definition = $this->definitionRegistry->getByClassOrEntityName($definition);
        }

        $cacheKey = $this->getEntityCacheKey($definition);

        if (\array_key_exists($cacheKey, $entityCache)) {
            return $entityCache[$cacheKey];
        }

        $fields = $definition->getFields();

        $entity = $this->getEntity($definition);

        $entityCache[$cacheKey] = $entity;

        $translatedFields = [];

        foreach ($fields as $field) {
            $propertyName = $field->getPropertyName();

            if ($field instanceof TranslationsAssociationField) {
                $entity->assign([
                    EntityDefinition::TRANSLATED_FIELD => $this->generateEntityData(
                        $field->getReferenceDefinition(),
                        $entityCache,
                        $context,
                    )->jsonSerialize(),
                ]);
            }

            if (
                $field->getFlag(ApiAware::class) === null
                || ($field instanceof JsonField && $propertyName === EntityDefinition::TRANSLATED_FIELD)
            ) {
                continue;
            }

            if ($field instanceof TranslatedField) {
                $translatedFields[] = $field;
                continue;
            }

            if ($definition::class === CurrencyDefinition::class && $propertyName === 'isoCode') {
                $entity->assign([$propertyName => 'EUR']);
                continue;
            }

            if ($field instanceof ManyToManyIdField) {
                $associationField = $fields->get($field->getAssociationName());
                \assert($associationField instanceof ManyToManyAssociationField);

                $mappingDefinition = $associationField->getMappingDefinition();

                $fkField =
                    $mappingDefinition
                        ->getFields()
                        ->filter(
                            fn (Field $field) => $field instanceof FkField && $field->getStorageName() === $associationField->getMappingReferenceColumn()
                        )
                        ->first();
                \assert($fkField instanceof FkField);

                $referencedEntity = $this->generateEntityData(
                    $fkField->getReferenceDefinition(),
                    $entityCache,
                    $context
                );

                $entity->assign([$propertyName => [$referencedEntity->get($fkField->getReferenceField())]]);
                continue;
            }

            $entity->assign([$propertyName => $this->generateFieldData($field, $entityCache, $context, $definition->getEntityName())]);
        }

        foreach ($translatedFields as $field) {
            $entity->assign([$field->getPropertyName() => $entity->get(EntityDefinition::TRANSLATED_FIELD)[$field->getPropertyName()]]);
        }

        return $entity;
    }

    /**
     * @param array<string, Entity> $entityCache
     */
    private function generateFieldData(Field $field, array &$entityCache, Context $context, ?string $entityName = null): mixed
    {
        $propertyName = $field->getPropertyName();

        $event = new MailDataSimulatorFieldEvent($field, $context);
        $this->eventDispatcher->dispatch($event);

        if ($event->hasValue()) {
            return $event->getValue();
        }

        switch (true) {
            case $field instanceof AutoIncrementField:
                return Random::getInteger(0, 1000);

            case $field instanceof BlobField:
                return $propertyName;

            case $field instanceof BoolField:
            case $field instanceof LockedField:
                return false;

            case $field instanceof ParentAssociationField:
            case $field instanceof ParentFkField:
                return null;

            case $field instanceof CalculatedPriceField:
                return new CalculatedPrice(
                    Random::getInteger(100, 1000000) / 100,
                    Random::getInteger(100, 1000000) / 100,
                    new CalculatedTaxCollection([new CalculatedTax(
                        Random::getInteger(100, 100000) / 100,
                        Random::getInteger(100, 1000000) / 100,
                        19.0,
                    )]),
                    new TaxRuleCollection([new TaxRule(
                        19.0,
                    )]),
                );

            case $field instanceof CartPriceField:
                return new CartPrice(
                    Random::getInteger(100, 1000000) / 100,
                    Random::getInteger(100, 1000000) / 100,
                    Random::getInteger(100, 1000000) / 100,
                    new CalculatedTaxCollection([new CalculatedTax(
                        Random::getInteger(100, 100000) / 100,
                        Random::getInteger(100, 1000000) / 100,
                        19.0,
                    )]),
                    new TaxRuleCollection([new TaxRule(
                        19.0,
                    )]),
                    CartPrice::TAX_STATE_GROSS,
                );

            case $field instanceof CashRoundingConfigField:
                return new CashRoundingConfig(
                    2,
                    0.01,
                    false
                );

            case $field instanceof ChildCountField:
            case $field instanceof IntField:
            case $field instanceof TreeLevelField:
                return Random::getInteger(1, 999999);

            case $field instanceof ChildrenAssociationField:
            case $field instanceof ManyToOneAssociationField:
            case $field instanceof OneToOneAssociationField:
                return $this->generateEntityData($field->getReferenceDefinition(), $entityCache, $context);

            case $field instanceof MeasurementUnitsField:
                return MeasurementUnits::createDefaultUnits();

            case $field instanceof ConfigJsonField:
            case $field instanceof CustomFields:
            case $field instanceof ObjectField:
            case $field instanceof PriceDefinitionField:
            case $field instanceof PriceField:
            case $field instanceof SlotConfigField:
            case $field instanceof TaxFreeConfigField:
            case $field instanceof TreeBreadcrumbField:
            case $field instanceof VariantListingConfigField:
            case $field instanceof VersionDataPayloadField:
            case $field instanceof JsonField:
                $jsonFields = $field->getPropertyMapping();

                $data = [];

                foreach ($jsonFields as $jsonField) {
                    $data[$jsonField->getPropertyName()] = $this->generateFieldData($jsonField, $entityCache, $context, $entityName);
                }

                try {
                    return $field->getSerializer()->decode($field, \json_encode($data));
                } catch (\Throwable $e) {
                }

                return $data;

            case $field instanceof CreatedAtField:
            case $field instanceof DateField:
            case $field instanceof DateTimeField:
            case $field instanceof UpdatedAtField:
                return $this->clock->now();

            case $field instanceof CreatedByField:
            case $field instanceof ReferenceVersionField:
            case $field instanceof StateMachineStateField:
            case $field instanceof UpdatedByField:
            case $field instanceof VersionField:
            case $field instanceof FkField:
                return $this->generateEntityData($field->getReferenceDefinition(), $entityCache, $context)->get($field->getReferenceField());

            case $field instanceof CronIntervalField:
                return '8 * * * *';

            case $field instanceof DateIntervalField:
                return (string) (new DateInterval('PT30M'));

            case $field instanceof EmailField:
                return 'max.mustermann@example.com';

            case $field instanceof FloatField:
                return Random::getInteger(100, 1000000) / 100;

            case $field instanceof IdField:
                return Uuid::randomHex();

            case $field instanceof TreePathField:
            case $field instanceof LongTextField:
                return 'Lorem ipsum dolor sit amet.';

            case $field instanceof OneToManyAssociationField:
            case $field instanceof ManyToManyAssociationField:
                $referenceDefinition = $field instanceof ManyToManyAssociationField ? $field->getToManyReferenceDefinition() : $field->getReferenceDefinition();

                $entity = $this->generateEntityData($referenceDefinition, $entityCache, $context);
                $this->ensureEntityIdentifier($entity);

                $collection = $this->getCollection($referenceDefinition);
                $collection->add($entity);

                return $collection;

            case $field instanceof ManyToManyIdField:
                return null;

            case $field instanceof NumberRangeField:
                return '"' . Random::getInteger(1, 999999) . '"';

            case $field instanceof PasswordField:
                return 'P@ssw0rd!';

            case $field instanceof RemoteAddressField:
                return '"' . IpUtils::anonymize('192.0.2.1') . '"';

            case $field instanceof TimeZoneField:
                return 'UTC';

            case $field instanceof StringField:
                return $this->randomString($propertyName, $entityName);

            case $field instanceof TranslationsAssociationField:
                $entity = $this->generateEntityData($field->getReferenceDefinition(), $entityCache, $context);
                $language = $this->generateEntityData(LanguageDefinition::class, $entityCache, $context);
                $this->ensureEntityIdentifier($language);

                // Use the language id as the simulated translation key so each language gets a distinct entry
                $entity->setUniqueIdentifier($language->getUniqueIdentifier());

                $collection = $this->getCollection($field->getReferenceDefinition());
                $collection->add($entity);

                return $collection;

            case $field instanceof BreadcrumbField:
            case $field instanceof EnumField:
            case $field instanceof ListField:
                return [];
        }

        return null;
    }

    private function ensureEntityIdentifier(Entity $entity): void
    {
        try {
            $entity->getUniqueIdentifier();
        } catch (\Throwable) {
            $identifier = Uuid::randomHex();

            if ($entity->has('id')) {
                $entity->assign(['id' => $identifier]);
            }

            $entity->setUniqueIdentifier($identifier);
        }
    }

    private function getEntity(EntityDefinition $definition): Entity
    {
        try {
            $entityClass = $definition->getEntityClass();

            $entity = new $entityClass();
            \assert($entity instanceof Entity);

            return $entity;
        } catch (\Throwable) {
            // MappingEntityDefinition throws for example, so we need to catch that and return a default entity.
            return new Entity();
        }
    }

    /**
     * @return EntityCollection<Entity>
     */
    private function getCollection(EntityDefinition $definition): EntityCollection
    {
        try {
            /** @var class-string<EntityCollection<Entity>> $collectionClass */
            $collectionClass = $definition->getCollectionClass();

            $collection = new $collectionClass();
            \assert($collection instanceof EntityCollection);

            return $collection;
        } catch (\Throwable) {
            // MappingEntityDefinition throws for example, so we need to catch that and return a default collection.
            return new EntityCollection();
        }
    }

    private function getEntityCacheKey(EntityDefinition $definition): string
    {
        return $definition::class . ':' . $definition->getEntityName();
    }

    private function randomString(string $propertyName, ?string $entityName = null): string
    {
        // Generate a deterministic substring of the base text, so different fields get different values,
        // while the same field keeps the same value and length on every execution.
        $baseText = 'Lorem ipsum dolor sit amet consectetur adipiscing elit.';

        $offsetSeed = $entityName !== null ? $entityName . '.' . $propertyName : $propertyName;
        $offset = abs(crc32($offsetSeed)) % 20;
        $length = 12 + (abs(crc32('length.' . $offsetSeed)) % 9);

        return mb_ucfirst(trim(mb_substr($baseText, $offset, $length)));
    }
}
