<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Faker\Factory;
use Faker\Generator;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsField;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\AbstractProvider;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\AssociativeArrayType;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\EventDataType;
use Shopware\Core\Framework\Event\EventData\ForeignKeyType;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * @internal
 */
#[Package('after-sales')]
class MailDataProvider
{
    /**
     * @var array<string, AbstractProvider<Entity, EntityCollection<Entity>>>
     */
    private array $dataProviders;

    /**
     * @param iterable<string, AbstractProvider<Entity, EntityCollection<Entity>>> $dataProviders
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        iterable $dataProviders,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly EntityRepository $languageRepository,
    ) {
        $this->dataProviders = $dataProviders instanceof \Traversable ? iterator_to_array($dataProviders) : $dataProviders;
    }

    /**
     * @param class-string<FlowEventAware> $flowEventClass
     *
     * @return array<string, mixed>
     */
    public function getTemplateData(string $flowEventClass, Context $context, ?int $seed = null): array
    {
        $faker = $this->createFaker($context, $seed);

        $templateData = [];
        $referenceData = [];

        try {
            $flowEvent = new \ReflectionClass($flowEventClass);

            $eventDataCollection = $flowEvent->getMethod('getAvailableData')->invoke(null);
            \assert($eventDataCollection instanceof EventDataCollection);
            $eventData = $eventDataCollection->getData();
        } catch (\ReflectionException $e) {
            return $templateData;
        }

        if (!$flowEvent->implementsInterface(MailAware::class)) {
            return $templateData;
        }

        $templateData[MailAware::TIMEZONE] = 'UTC';

        $templateData['salesChannel'] = $this->generateEntityData(
            SalesChannelDefinition::class,
            (new Criteria())
                ->addAssociation('mailHeaderFooter')
                ->addAssociation('domains'),
            $referenceData,
            $faker
        );
        $templateData['salesChannelId'] = $templateData['salesChannel']->get('id');

        foreach ($eventData as $name => $type) {
            if (\array_key_exists($name, $templateData)) {
                continue;
            }

            $templateData[$name] = $this->generateEventDataTypeData($type, $referenceData, $context, $faker);
        }

        return $templateData;
    }

    /**
     * @param array<string,mixed> $referenceData
     */
    private function generateEventDataTypeData(EventDataType $dataType, array &$referenceData, Context $context, Generator $faker): mixed
    {
        if ($dataType::class === AssociativeArrayType::class || $dataType::class === ArrayType::class) {
            return [];
        }

        if ($dataType::class === EntityCollectionType::class || $dataType::class === EntityType::class) {
            $definition = $this->definitionRegistry->getByClassOrEntityName($dataType->getDefinitionClass());

            $dataProvider = $this->dataProviders[$definition->getEntityName()] ?? null;

            return $this->generateEntityData(
                $definition,
                $dataProvider?->getCriteria('mail template test id', $context),
                $referenceData,
                $faker
            );
        }

        if ($dataType::class === ForeignKeyType::class) {
            if (!\array_key_exists($dataType->getReferenceClass() . '.id', $referenceData)) {
                $referenceData[$dataType->getReferenceClass() . '.id'] = Uuid::fromStringToHex($faker->uuid());
            }

            return $referenceData[$dataType->getReferenceClass() . '.id'];
        }

        if ($dataType::class === ObjectType::class) {
            return array_map(function ($value) use ($referenceData, $context, $faker) {
                return $this->generateEventDataTypeData($value, $referenceData, $context, $faker);
            }, $dataType->getData());
        }

        if ($dataType::class === ScalarValueType::class) {
            switch ($dataType->getType()) {
                case ScalarValueType::TYPE_BOOL:
                    return $faker->boolean();
                case ScalarValueType::TYPE_FLOAT:
                    return $faker->randomFloat();
                case ScalarValueType::TYPE_INT:
                    return $faker->randomNumber();
                case ScalarValueType::TYPE_STRING:
                    return '"' . $faker->text(20) . '"';
            }
        }

        throw MailTemplateException::unknownEventDataType($dataType::class);
    }

    /**
     * @param array<string,Entity> $referenceData
     */
    private function generateEntityData(EntityDefinition|string $definition, ?Criteria $criteria, array &$referenceData, Generator $faker): Entity
    {
        if (\is_string($definition)) {
            $definition = $this->definitionRegistry->getByClassOrEntityName($definition);
        }

        if (\array_key_exists($definition::class, $referenceData)) {
            return $referenceData[$definition::class];
        }

        $fields = $definition->getFields();

        $entity = new ($definition->getEntityClass());

        $referenceData[$definition::class] = $entity;

        $translatedFields = [];

        foreach ($fields as $field) {
            $propertyName = $field->getPropertyName();

            if ($field::class === TranslationsAssociationField::class) {
                $entity->assign([
                    EntityDefinition::TRANSLATED_FIELD => $this->generateEntityData(
                        $field->getReferenceClass(),
                        $criteria?->getAssociation($propertyName),
                        $referenceData,
                        $faker,
                    )->jsonSerialize(),
                ]);
            }

            if (
                $field->getFlag(ApiAware::class) === null
                || (
                    $field instanceof AssociationField
                    && !$field->getAutoload()
                    && ($criteria === null || !$criteria->hasAssociation($propertyName))
                )
                || ($field::class === JsonField::class && $propertyName === EntityDefinition::TRANSLATED_FIELD)
            ) {
                continue;
            }

            if ($field instanceof TranslatedField) {
                $translatedFields[] = $field;
                continue;
            }

            if ($field::class === ManyToManyIdField::class) {
                $associationField = $fields->get($field->getAssociationName());
                \assert($associationField instanceof ManyToManyAssociationField);

                $definition = $this->definitionRegistry->getByClassOrEntityName($associationField->getReferenceClass());

                $fkField =
                    $definition
                        ->getFields()
                        ->filter(
                            fn (Field $field) => $field instanceof FkField && $field->getStorageName() === $associationField->getMappingReferenceColumn()
                        )
                        ->first();
                \assert($fkField instanceof FkField);

                $referencedEntity = $this->generateEntityData(
                    $fkField->getReferenceClass(),
                    $criteria?->getAssociation($propertyName),
                    $referenceData,
                    $faker
                );

                $entity->assign([$propertyName => [$referencedEntity->get($fkField->getReferenceField())]]);
                continue;
            }

            $entity->assign([$propertyName => $this->generateFieldData($field, $criteria, $referenceData, $faker)]);
        }

        foreach ($translatedFields as $field) {
            $entity->assign([$field->getPropertyName() => $entity->get(EntityDefinition::TRANSLATED_FIELD)[$field->getPropertyName()]]);
        }

        return $entity;
    }

    /**
     * @param array<string,Entity> $referenceData
     */
    private function generateFieldData(Field $field, ?Criteria $criteria, array &$referenceData, Generator $faker): mixed
    {
        $propertyName = $field->getPropertyName();

        switch ($field::class) {
            case AutoIncrementField::class:
                return $faker->numberBetween();
            case BlobField::class:
                return $propertyName;
            case BoolField::class:
            case LockedField::class:
                return false;
            case BreadcrumbField::class:
            case EnumField::class:
            case ListField::class:
                return [];
            case CalculatedPriceField::class:
            case CartPriceField::class:
            case CashRoundingConfigField::class:
            case ConfigJsonField::class:
            case CustomFields::class:
            case JsonField::class:
            case ObjectField::class:
            case PriceDefinitionField::class:
            case PriceField::class:
            case TaxFreeConfigField::class:
            case TreeBreadcrumbField::class:
            case VariantListingConfigField::class:
            case VersionDataPayloadField::class:
                $jsonFields = $field->getPropertyMapping();

                $result = [];

                foreach ($jsonFields as $jsonField) {
                    $result[$jsonField->getPropertyName()] = $this->generateFieldData($jsonField, null, $referenceData, $faker);
                }

                return $result;
            case ChildCountField::class:
            case IntField::class:
            case TreeLevelField::class:
                return $faker->randomNumber();
            case ChildrenAssociationField::class:
            case ManyToOneAssociationField::class:
            case OneToOneAssociationField::class:
            case ParentAssociationField::class:
                return $this->generateEntityData($field->getReferenceClass(), $criteria?->getAssociation($propertyName), $referenceData, $faker);
            case CreatedAtField::class:
            case DateField::class:
            case DateTimeField::class:
            case UpdatedAtField::class:
                return $this->randomDateTime($faker);
            case CreatedByField::class:
            case FkField::class:
            case ParentFkField::class:
            case ReferenceVersionField::class:
            case StateMachineStateField::class:
            case UpdatedByField::class:
            case VersionField::class:
                return $this->generateEntityData($field->getReferenceClass(), $criteria?->getAssociation($propertyName), $referenceData, $faker)->get($field->getReferenceField());
            case CronIntervalField::class:
                return '8 * * * *';
            case DateIntervalField::class:
                return (string) (new DateInterval('PT30M'));
            case EmailField::class:
                return $faker->email();
            case FloatField::class:
                return $faker->randomFloat();
            case IdField::class:
                return Uuid::fromStringToHex($faker->uuid());
            case LongTextField::class:
            case TreePathField::class:
                return '"' . $faker->text() . '"';
            case ManyToManyAssociationField::class:
                $entity = $this->generateEntityData($field->getToManyReferenceDefinition(), $criteria?->getAssociation($propertyName), $referenceData, $faker);

                $collection = new ($this->getCollectionClass($entity))();
                \assert($collection instanceof EntityCollection);
                $collection->add($entity);

                return $collection;
            case ManyToManyIdField::class:
                break;
            case MeasurementUnitsField::class:
                return MeasurementUnits::createDefaultUnits();
            case NumberRangeField::class:
                return '"' . $faker->randomNumber() . '"';
            case OneToManyAssociationField::class:
                $entity = $this->generateEntityData($field->getReferenceClass(), $criteria?->getAssociation($propertyName), $referenceData, $faker);

                $collection = new ($this->getCollectionClass($entity))();
                \assert($collection instanceof EntityCollection);
                $collection->add($entity);

                return $collection;
            case PasswordField::class:
                return '"' . $faker->password() . '"';
            case RemoteAddressField::class:
                return '"' . IpUtils::anonymize($faker->ipv4()) . '"';
            case StringField::class:
                return '"' . $faker->text(20) . '"';
            case TimeZoneField::class:
                return '"' . $faker->timezone() . '"';
            case TranslationsAssociationField::class:
                $entity = $this->generateEntityData($field->getReferenceClass(), $criteria?->getAssociation($propertyName), $referenceData, $faker);
                $language = $this->generateEntityData(LanguageDefinition::class, null, $referenceData, $faker);

                $entity->setUniqueIdentifier($language->getUniqueIdentifier());

                $collection = new ($this->getCollectionClass($entity))();
                \assert($collection instanceof EntityCollection);
                $collection->add($entity);

                return $collection;
        }

        throw MailTemplateException::unknownFieldDataType($field::class);
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
     * @return class-string
     */
    private function getCollectionClass(Entity $class): string
    {
        return $this->definitionRegistry->getByEntityClass($class)->getCollectionClass();
    }
}
