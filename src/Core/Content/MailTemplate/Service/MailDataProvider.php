<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Faker\Factory;
use Faker\Generator;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsField;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\AbstractProvider;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
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

        $salesChannelFields = $this->definitionRegistry->getByClassOrEntityName(SalesChannelDefinition::class)->getFields();

        $templateData['salesChannel'] = $this->generateFieldData(SalesChannelDefinition::class, $salesChannelFields, null, $referenceData, $faker);
        $templateData['salesChannelId'] = $templateData['salesChannel']['id'];

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
            $fields = $definition->getFields();

            $dataProvider = $this->dataProviders[$definition->getEntityName()] ?? null;

            $referenceData[$definition::class] = $this->generateFieldData($definition::class, $fields, $dataProvider?->getCriteria('mail template test id', $context), $referenceData, $faker);

            return $referenceData[$definition::class];
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
     * @param CompiledFieldCollection|list<Field> $fields
     * @param array<string,mixed> $referenceData
     *
     * @return array<string,mixed>
     */
    private function generateFieldData(string $parent, CompiledFieldCollection|array $fields, ?Criteria $criteria, array &$referenceData, Generator $faker): array
    {
        $fieldData = [];
        $unresolvedTranslatedFields = [];

        if (!\array_key_exists($parent, $referenceData)) {
            $referenceData[$parent] = [];
        }

        foreach ($fields as $field) {
            if (\array_key_exists($field->getPropertyName(), $fieldData) || $field->getFlag(ApiAware::class) === null) {
                continue;
            }

            if ($field::class === TranslationsAssociationField::class) {
                $definition = $this->definitionRegistry->getByClassOrEntityName($field->getReferenceClass());

                if (!\array_key_exists($definition::class, $referenceData)) {
                    $translationFields = $definition->getFields();
                    $referenceData[$definition::class] = $this->generateFieldData($definition::class, $translationFields, $criteria, $referenceData, $faker);
                }

                $fieldData[EntityDefinition::TRANSLATED_FIELD] = $referenceData[$definition::class];
            }

            if ($field instanceof AssociationField && !$field->getAutoload() && ($criteria === null || !$criteria->hasAssociation($field->getPropertyName()))) {
                continue;
            }

            $propertyName = $field->getPropertyName();

            switch ($field::class) {
                case AutoIncrementField::class:
                    $fieldData[$propertyName] = $faker->numberBetween();
                    break;
                case BlobField::class:
                    $fieldData[$propertyName] = $propertyName;
                    break;
                case BoolField::class:
                case LockedField::class:
                    $fieldData[$propertyName] = false;
                    break;
                case BreadcrumbField::class:
                case EnumField::class:
                case ListField::class:
                    $fieldData[$propertyName] = [];
                    break;
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
                    $fieldData[$propertyName] = $this->generateFieldData($field::class, $field->getPropertyMapping(), null, $referenceData, $faker);
                    break;
                case ChildCountField::class:
                case IntField::class:
                case TreeLevelField::class:
                    $fieldData[$propertyName] = $faker->randomNumber();
                    break;
                case ChildrenAssociationField::class:
                case ManyToOneAssociationField::class:
                case OneToOneAssociationField::class:
                case ParentAssociationField::class:
                    $definition = $this->definitionRegistry->getByClassOrEntityName($field->getReferenceClass());

                    if (!\array_key_exists($definition::class, $referenceData)) {
                        $referenceFields = $definition->getFields();

                        $referenceData[$definition::class] = $this->generateFieldData($definition::class, $referenceFields, $criteria?->getAssociation($propertyName), $referenceData, $faker);
                    }

                    $fieldData[$propertyName] = $referenceData[$definition::class];
                    break;
                case CreatedAtField::class:
                case DateTimeField::class:
                case UpdatedAtField::class:
                    $fieldData[$propertyName] = $this->randomDateTime($faker)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
                    break;
                case CreatedByField::class:
                case FkField::class:
                case ParentFkField::class:
                case ReferenceVersionField::class:
                case StateMachineStateField::class:
                case UpdatedByField::class:
                case VersionField::class:
                    $definition = $this->definitionRegistry->getByClassOrEntityName($field->getReferenceClass());

                    if (!\array_key_exists($definition::class, $referenceData)) {
                        $referenceFields = $definition->getFields();

                        $referenceData[$definition::class] = $this->generateFieldData($definition::class, $referenceFields, $criteria?->getAssociation($propertyName), $referenceData, $faker);
                    }

                    $fieldData[$propertyName] =
                        $referenceData[$definition::class][$field->getReferenceField()]
                        ?? $referenceData[$definition::class . '.' . $field->getReferenceField()]
                        ?? null;
                    break;
                case CronIntervalField::class:
                    $fieldData[$propertyName] = '8 * * * *';
                    break;
                case DateField::class:
                    $fieldData[$propertyName] = $this->randomDateTime($faker)->format(Defaults::STORAGE_DATE_FORMAT);
                    break;
                case DateIntervalField::class:
                    $fieldData[$propertyName] = (string) (new DateInterval('PT30M'));
                    break;
                case EmailField::class:
                    $fieldData[$propertyName] = $faker->email();
                    break;
                case FloatField::class:
                    $fieldData[$propertyName] = $faker->randomFloat();
                    break;
                case IdField::class:
                    if (!\array_key_exists($parent . '.id', $referenceData)) {
                        $referenceData[$parent . '.id'] = Uuid::fromStringToHex($faker->uuid());
                    }

                    $fieldData[$propertyName] = $referenceData[$parent . '.id'];
                    break;
                case LongTextField::class:
                case TreePathField::class:
                    $fieldData[$propertyName] = '"' . $faker->text() . '"';
                    break;
                case ManyToManyAssociationField::class:
                    $definition = $field->getToManyReferenceDefinition();
                    $referenceFields = $definition->getFields();

                    if (!\array_key_exists($definition::class, $referenceData)) {
                        $referenceData[$definition::class] = $this->generateFieldData($definition::class, $referenceFields, $criteria?->getAssociation($propertyName), $referenceData, $faker);
                    }

                    $fieldData[$propertyName] = [$referenceData[$definition::class . '.id'] => $referenceData[$definition::class]];
                    break;
                case ManyToManyIdField::class:
                    if (!$fields instanceof CompiledFieldCollection) {
                        break;
                    }

                    $associationField = $fields->get($field->getAssociationName());
                    \assert($associationField instanceof ManyToManyAssociationField);

                    $definition = $this->definitionRegistry->getByClassOrEntityName($associationField->getReferenceClass());
                    $referenceFields = $definition->getFields();

                    if (!\array_key_exists($definition::class, $referenceData)) {
                        $referenceData[$definition::class] = $this->generateFieldData($definition::class, $referenceFields, $criteria?->getAssociation($propertyName), $referenceData, $faker);
                    }

                    $fkField =
                        $definition
                            ->getFields()
                            ->filter(fn (Field $field) => $field instanceof FkField && $field->getStorageName() === $associationField->getMappingReferenceColumn())
                            ->first();
                    \assert($fkField instanceof FkField);

                    $fkFieldReferenceDefinition = $this->definitionRegistry->getByClassOrEntityName($fkField->getReferenceClass());
                    $fkFieldReferenceFields = $fkFieldReferenceDefinition->getFields();

                    if (!\array_key_exists($fkFieldReferenceDefinition::class, $referenceData)) {
                        $referenceData[$fkFieldReferenceDefinition::class] = $this->generateFieldData($fkFieldReferenceDefinition::class, $fkFieldReferenceFields, $criteria?->getAssociation($propertyName), $referenceData, $faker);
                    }

                    $fieldData[$propertyName] = [$referenceData[$fkFieldReferenceDefinition::class][$fkField->getReferenceField()]];
                    break;
                case MeasurementUnitsField::class:
                    if (!\array_key_exists(MeasurementUnits::class, $referenceData)) {
                        $referenceData[MeasurementUnits::class] = MeasurementUnits::createDefaultUnits()->jsonSerialize();
                    }
                    $fieldData[$propertyName] = $referenceData[MeasurementUnits::class];
                    break;
                case NumberRangeField::class:
                    $fieldData[$propertyName] = '"' . $faker->randomNumber() . '"';
                    break;
                case OneToManyAssociationField::class:
                    $definition = $this->definitionRegistry->getByClassOrEntityName($field->getReferenceClass());

                    if (!\array_key_exists($definition::class, $referenceData)) {
                        $referenceFields = $this->definitionRegistry->getByClassOrEntityName($definition::class)->getFields();

                        $referenceData[$definition::class] = $this->generateFieldData($definition::class, $referenceFields, $criteria?->getAssociation($propertyName), $referenceData, $faker);
                    }

                    $fieldData[$propertyName] = [$referenceData[$definition::class . '.id'] => $referenceData[$definition::class]];
                    break;
                case PasswordField::class:
                    $fieldData[$propertyName] = '"' . $faker->password() . '"';
                    break;
                case RemoteAddressField::class:
                    $fieldData[$propertyName] = '"' . IpUtils::anonymize($faker->ipv4()) . '"';
                    break;
                case StringField::class:
                    $fieldData[$propertyName] = '"' . $faker->text(20) . '"';
                    break;
                case TimeZoneField::class:
                    $fieldData[$propertyName] = '"' . $faker->timezone() . '"';
                    break;
                case TranslatedField::class:
                    $unresolvedTranslatedFields[] = $field;
                    break;
                case TranslationsAssociationField::class:
                    if (!\array_key_exists(LanguageEntity::class . '.id', $referenceData)) {
                        $referenceData[LanguageEntity::class . '.id'] = Uuid::fromStringToHex($faker->uuid());
                    }

                    $fieldData[$propertyName] = [$referenceData[LanguageEntity::class . '.id'] => $referenceData[$field->getReferenceClass()]];
                    break;
                default:
                    throw MailTemplateException::unknownFieldDataType($field::class);
            }
        }

        foreach ($unresolvedTranslatedFields as $field) {
            $fieldData[$field->getPropertyName()] = $fieldData[EntityDefinition::TRANSLATED_FIELD][$field->getPropertyName()];
        }

        return $fieldData;
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
}
