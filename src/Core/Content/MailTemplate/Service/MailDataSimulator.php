<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Faker\Factory;
use Faker\Generator;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
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
     * @param EntityRepository<LanguageCollection> $languageRepository
     * @param iterable<string, MailFlowDataProviderInterface<Entity>> $dataProviders
     */
    public function __construct(
        private readonly BusinessEventCollector $businessEventCollector,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly EntityRepository $languageRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        iterable $dataProviders,
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
        ?int $seed = null
    ): array {
        $faker = $this->createFaker($context, $seed);

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
            $faker,
            $context
        );

        foreach ($eventData as $name => $type) {
            if (\array_key_exists($name, $templateData)) {
                continue;
            }

            $templateData[$name] = $this->generateEventDataTypeData($type, $entityCache, $context, $faker);
        }

        return $templateData;
    }

    /**
     * @param array<string,mixed> $dataType
     * @param array<string, Entity> $entityCache
     */
    private function generateEventDataTypeData(array $dataType, array &$entityCache, Context $context, Generator $faker): mixed
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
                $faker,
                $context
            );
        }

        if ($dataType['type'] === ObjectType::TYPE) {
            return array_map(function ($value) use ($entityCache, $context, $faker) {
                return $this->generateEventDataTypeData($value, $entityCache, $context, $faker);
            }, $dataType['data'] ?? []);
        }

        if (\in_array($dataType['type'], ScalarValueType::VALID_TYPES, true)) {
            switch ($dataType['type']) {
                case ScalarValueType::TYPE_BOOL:
                    return $faker->boolean();
                case ScalarValueType::TYPE_FLOAT:
                    return $faker->randomFloat(2, 1, 10000);
                case ScalarValueType::TYPE_INT:
                    return $faker->randomNumber();
                case ScalarValueType::TYPE_STRING:
                    return '"' . $faker->text(20) . '"';
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
        Generator $faker,
        Context $context
    ): Entity {
        if (\is_string($definition)) {
            $definition = $this->definitionRegistry->getByClassOrEntityName($definition);
        }

        $cacheKey = $definition->getEntityName();

        if (!\array_key_exists($cacheKey, $entityCache)) {
            $this->generateEntityData($definition, $entityCache, $faker, $context);
        }

        $cachedEntity = $entityCache[$cacheKey];

        $entity = new ($definition->getEntityClass());

        $fields = $definition->getFields();

        foreach ($fields as $field) {
            $entity->assign([$field->getPropertyName() => $this->getEntityField(
                $definition,
                $field,
                $cachedEntity,
                $criteria,
                $entityCache,
                $faker,
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
        Generator $faker,
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
                    $faker,
                    $context
                );
            }

            return $data;
        } elseif ($field instanceof ManyToManyAssociationField) {
            return $this->getEntityData(
                $field->getToManyReferenceDefinition(),
                $criteria?->getAssociation($propertyName),
                $entityCache,
                $faker,
                $context
            );
        } elseif ($field instanceof OneToManyAssociationField) {
            $toManyDefinition = $field->getReferenceDefinition();

            $collection = new ($toManyDefinition->getCollectionClass());
            \assert($collection instanceof EntityCollection);
            $collection->add($this->getEntityData(
                $toManyDefinition,
                $criteria?->getAssociation($propertyName),
                $entityCache,
                $faker,
                $context
            ));

            return $collection;
        } elseif ($field instanceof AssociationField) {
            return $this->getEntityData(
                $field->getReferenceDefinition(),
                $criteria?->getAssociation($propertyName),
                $entityCache,
                $faker,
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
        Generator $faker,
        Context $context
    ): Entity {
        if (\is_string($definition)) {
            $definition = $this->definitionRegistry->getByClassOrEntityName($definition);
        }

        $cacheKey = $definition->getEntityName();

        if (\array_key_exists($cacheKey, $entityCache)) {
            return $entityCache[$cacheKey];
        }

        $fields = $definition->getFields();

        $entity = new ($definition->getEntityClass());

        $entityCache[$cacheKey] = $entity;

        $translatedFields = [];

        foreach ($fields as $field) {
            $propertyName = $field->getPropertyName();

            if ($field instanceof TranslationsAssociationField) {
                $entity->assign([
                    EntityDefinition::TRANSLATED_FIELD => $this->generateEntityData(
                        $field->getReferenceDefinition(),
                        $entityCache,
                        $faker,
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
                    $faker,
                    $context
                );

                $entity->assign([$propertyName => [$referencedEntity->get($fkField->getReferenceField())]]);
                continue;
            }

            $entity->assign([$propertyName => $this->generateFieldData($field, $entityCache, $faker, $context)]);
        }

        foreach ($translatedFields as $field) {
            $entity->assign([$field->getPropertyName() => $entity->get(EntityDefinition::TRANSLATED_FIELD)[$field->getPropertyName()]]);
        }

        return $entity;
    }

    /**
     * @param array<string, Entity> $entityCache
     */
    private function generateFieldData(Field $field, array &$entityCache, Generator $faker, Context $context): mixed
    {
        $propertyName = $field->getPropertyName();

        $event = new MailDataSimulatorFieldEvent($field, $context, $faker);
        $this->eventDispatcher->dispatch($event);

        if ($event->hasValue()) {
            return $event->getValue();
        }

        switch (true) {
            case $field instanceof AutoIncrementField:
                return $faker->numberBetween();

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
                    $faker->randomFloat(2, 1, 10000),
                    $faker->randomFloat(2, 1, 10000),
                    new CalculatedTaxCollection([new CalculatedTax(
                        $faker->randomFloat(2, 1, 1000),
                        $faker->randomFloat(2, 1, 10000),
                        $faker->randomElement([7.0, 19.0]),
                    )]),
                    new TaxRuleCollection([new TaxRule(
                        $faker->randomElement([7.0, 19.0]),
                    )]),
                );

            case $field instanceof CartPriceField:
                return new CartPrice(
                    $faker->randomFloat(2, 1, 10000),
                    $faker->randomFloat(2, 1, 10000),
                    $faker->randomFloat(2, 1, 10000),
                    new CalculatedTaxCollection([new CalculatedTax(
                        $faker->randomFloat(2, 1, 1000),
                        $faker->randomFloat(2, 1, 10000),
                        $faker->randomElement([7.0, 19.0]),
                    )]),
                    new TaxRuleCollection([new TaxRule(
                        $faker->randomElement([7.0, 19.0]),
                    )]),
                    $faker->word(),
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
                return $faker->randomNumber();

            case $field instanceof ChildrenAssociationField:
            case $field instanceof ManyToOneAssociationField:
            case $field instanceof OneToOneAssociationField:
                return $this->generateEntityData($field->getReferenceDefinition(), $entityCache, $faker, $context);

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
                    $data[$jsonField->getPropertyName()] = $this->generateFieldData($jsonField, $entityCache, $faker, $context);
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
                return $this->randomDateTime($faker);

            case $field instanceof CreatedByField:
            case $field instanceof ReferenceVersionField:
            case $field instanceof StateMachineStateField:
            case $field instanceof UpdatedByField:
            case $field instanceof VersionField:
            case $field instanceof FkField:
                return $this->generateEntityData($field->getReferenceDefinition(), $entityCache, $faker, $context)->get($field->getReferenceField());

            case $field instanceof CronIntervalField:
                return '8 * * * *';

            case $field instanceof DateIntervalField:
                return (string) (new DateInterval('PT30M'));

            case $field instanceof EmailField:
                return $faker->email();

            case $field instanceof FloatField:
                return $faker->randomFloat(2, 1, 10000);

            case $field instanceof IdField:
                return Uuid::fromStringToHex($faker->uuid());

            case $field instanceof TreePathField:
            case $field instanceof LongTextField:
                return '"' . $faker->text() . '"';

            case $field instanceof ManyToManyAssociationField:
                $entity = $this->generateEntityData($field->getToManyReferenceDefinition(), $entityCache, $faker, $context);

                $collection = new ($this->getCollectionClass($entity))();
                \assert($collection instanceof EntityCollection);
                $this->ensureEntityIdentifier($entity, $faker);
                $collection->add($entity);

                return $collection;

            case $field instanceof ManyToManyIdField:
                return null;

            case $field instanceof NumberRangeField:
                return '"' . $faker->randomNumber() . '"';

            case $field instanceof OneToManyAssociationField:
                $entity = $this->generateEntityData($field->getReferenceDefinition(), $entityCache, $faker, $context);

                $collection = new ($this->getCollectionClass($entity))();
                \assert($collection instanceof EntityCollection);
                $this->ensureEntityIdentifier($entity, $faker);
                $collection->add($entity);

                return $collection;

            case $field instanceof PasswordField:
                return '"' . $faker->password() . '"';

            case $field instanceof RemoteAddressField:
                return '"' . IpUtils::anonymize($faker->ipv4()) . '"';

            case $field instanceof TimeZoneField:
                return '"' . $faker->timezone() . '"';

            case $field instanceof StringField:
                return '"' . $faker->text(20) . '"';

            case $field instanceof TranslationsAssociationField:
                $entity = $this->generateEntityData($field->getReferenceDefinition(), $entityCache, $faker, $context);
                $language = $this->generateEntityData(LanguageDefinition::class, $entityCache, $faker, $context);
                $this->ensureEntityIdentifier($language, $faker);

                $entity->setUniqueIdentifier($language->getUniqueIdentifier());

                $collection = new ($this->getCollectionClass($entity))();
                \assert($collection instanceof EntityCollection);
                $collection->add($entity);

                return $collection;

            case $field instanceof BreadcrumbField:
            case $field instanceof EnumField:
            case $field instanceof ListField:
                return [];
        }

        return null;
    }

    private function ensureEntityIdentifier(Entity $entity, Generator $faker): void
    {
        try {
            $entity->getUniqueIdentifier();
        } catch (\Throwable) {
            $identifier = Uuid::fromStringToHex($faker->uuid());

            if ($entity->has('id')) {
                $entity->assign(['id' => $identifier]);
            }

            $entity->setUniqueIdentifier($identifier);
        }
    }

    private function createFaker(Context $context, ?int $seed = null): Generator
    {
        $criteria = (new Criteria([$context->getLanguageId()]))->addAssociation('locale');
        $language = $this->languageRepository->search($criteria, $context)->first();
        \assert($language instanceof LanguageEntity);

        $localeCode = \str_replace('-', '_', $language->getLocale()?->getCode() ?? Factory::DEFAULT_LOCALE);

        $faker = Factory::create($localeCode);
        $faker->seed($seed);

        return $faker;
    }

    private function randomDateTime(Generator $faker): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())
            ->setDate(
                $faker->numberBetween(1900, 2100),
                $faker->numberBetween(1, 12),
                $faker->numberBetween(1, 28),
            )
            ->setTime(
                $faker->numberBetween(0, 23),
                $faker->numberBetween(0, 59),
                $faker->numberBetween(0, 59),
                $faker->numberBetween(0, 999),
            );
    }

    /**
     * @return ?class-string
     */
    private function getCollectionClass(Entity $class): ?string
    {
        return $this->definitionRegistry->getByEntityClass($class)?->getCollectionClass();
    }
}
