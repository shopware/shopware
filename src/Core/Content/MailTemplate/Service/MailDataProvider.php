<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsField;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\AbstractProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
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
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

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
     */
    public function __construct(
        iterable $dataProviders,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
        $this->dataProviders = $dataProviders instanceof \Traversable ? iterator_to_array($dataProviders) : $dataProviders;
    }

    /**
     * @param class-string<FlowEventAware> $flowEventClass
     *
     * @return array<string, mixed>
     */
    public function getTemplateData(string $flowEventClass, Context $context): array
    {
        $templateData = [];
        $referenceData = [];

        try {
            $flowEvent = new \ReflectionClass($flowEventClass);

            $eventDataCollection = $flowEvent->getMethod('getAvailableData')->invoke(null);
            \assert($eventDataCollection instanceof EventDataCollection);
            $eventData = $eventDataCollection->getData();
        } catch (\ReflectionException $e) {
            return [];
        }

        if (!$flowEvent->implementsInterface(MailAware::class)) {
            return [];
        }

        $templateData[MailAware::TIMEZONE] = 'UTC';

        $templateData['salesChannel'] = $this->generateEntityData(SalesChannelDefinition::class, null, $referenceData);
        $templateData['salesChannelId'] = $templateData['salesChannel']['id'];

        foreach ($eventData as $name => $type) {
            if (\array_key_exists($name, $templateData)) {
                continue;
            }

            $templateData[$name] = $this->generateEventDataTypeData($type, $referenceData, $context);
        }

        return $templateData;
    }

    /**
     * @param array<string,mixed> $referenceData
     */
    private function generateEventDataTypeData(EventDataType $dataType, array &$referenceData, Context $context): mixed
    {
        if ($dataType::class === AssociativeArrayType::class || $dataType::class === ArrayType::class) {
            return [];
        }

        if ($dataType::class === EntityCollectionType::class) {
            $entityDefinition = new ($dataType->getDefinitionClass());
            \assert($entityDefinition instanceof EntityDefinition);

            return [$this->generateEntityData($entityDefinition::class, $this->dataProviders[$entityDefinition->getEntityName()]->getCriteria('mail template test id', $context), $referenceData)];
        }

        if ($dataType::class === EntityType::class) {
            return $this->generateEntityData($dataType->getDefinitionClass(), $this->dataProviders[$dataType->getEntityName()]->getCriteria('mail template test id', $context), $referenceData);
        }

        if ($dataType::class === ForeignKeyType::class) {
            if (!\array_key_exists($dataType->getReferenceClass() . '.id', $referenceData)) {
                $referenceData[$dataType->getReferenceClass() . '.id'] = Uuid::randomHex();
            }

            return $referenceData[$dataType->getReferenceClass() . '.id'];
        }

        if ($dataType::class === ObjectType::class) {
            return array_map(function ($value) use ($referenceData, $context) {
                return $this->generateEventDataTypeData($value, $referenceData, $context);
            }, $dataType->getData());
        }

        if ($dataType::class === ScalarValueType::class) {
            switch ($dataType->getType()) {
                case ScalarValueType::TYPE_BOOL:
                    return false;
                case ScalarValueType::TYPE_FLOAT:
                    return 42.24;
                case ScalarValueType::TYPE_INT:
                    return 42;
                case ScalarValueType::TYPE_STRING:
                    return '[...]';
            }
        }

        throw MailTemplateException::unknownEventDataType($dataType::class);
    }

    /**
     * @param array<string,mixed> $referenceData
     *
     * @return array<string,mixed>
     */
    private function generateEntityData(string $class, ?Criteria $criteria, array &$referenceData): array
    {
        $entity = [];
        $unresolvedTranslatedFields = [];

        $fields = $this->definitionRegistry->get($class)->getFields();

        foreach ($fields as $field) {
            if (\array_key_exists($field->getPropertyName(), $entity)) {
                continue;
            }

            if ($field::class === TranslationsAssociationField::class) {
                $referenceData[$field->getReferenceClass()] = $this->generateEntityData($field->getReferenceClass(), $criteria, $referenceData);
                $entity[EntityDefinition::TRANSLATED_FIELD] = $referenceData[$field->getReferenceClass()];
            }

            if ($field instanceof AssociationField && !$field->getAutoload() && ($criteria === null || !$criteria->hasAssociation($field->getPropertyName()))) {
                continue;
            }

            switch ($field::class) {
                case BlobField::class:
                    $entity[$field->getPropertyName()] = $field->getPropertyName();
                    break;
                case BoolField::class:
                    $entity[$field->getPropertyName()] = false;
                    break;
                case CreatedAtField::class:
                case UpdatedAtField::class:
                    $entity[$field->getPropertyName()] = new \DateTimeImmutable();
                    break;
                case CustomFields::class:
                case JsonField::class:
                case ListField::class:
                    $entity[$field->getPropertyName()] = null;
                    break;
                case FkField::class:
                    if (!\array_key_exists($field->getReferenceClass() . '.' . $field->getReferenceField(), $referenceData)) {
                        $referenceData[$field->getReferenceClass() . '.' . $field->getReferenceField()] = Uuid::randomHex();
                    }
                    $entity[$field->getPropertyName()] = $referenceData[$field->getReferenceClass() . '.' . $field->getReferenceField()];
                    break;
                case IdField::class:
                    if (!\array_key_exists($class . '.id', $referenceData)) {
                        $referenceData[$class . '.id'] = Uuid::randomHex();
                    }
                    $entity[$field->getPropertyName()] = $referenceData[$class . '.id'];
                    break;
                case IntField::class:
                    $entity[$field->getPropertyName()] = 42;
                    break;
                case ManyToManyIdField::class:
                    $associationField = $fields->get($field->getAssociationName());
                    \assert($associationField instanceof ManyToManyAssociationField);
                    if (!\array_key_exists($associationField->getReferenceClass(), $referenceData)) {
                        $referenceData[$associationField->getReferenceClass()] = $this->generateEntityData($associationField->getReferenceClass(), $criteria, $referenceData);
                    }
                    $fkField =
                        $this->definitionRegistry
                            ->get($associationField->getReferenceClass())
                            ->getFields()
                            ->filter(fn (Field $field) => $field instanceof FkField && $field->getStorageName() === $associationField->getMappingReferenceColumn())
                            ->first();
                    \assert($fkField instanceof FkField);

                    if (!\array_key_exists($fkField->getReferenceClass(), $referenceData)) {
                        $referenceData[$fkField->getReferenceClass()] = $this->generateEntityData($fkField->getReferenceClass(), $criteria, $referenceData);
                    }

                    $entity[$field->getPropertyName()] = [$referenceData[$fkField->getReferenceClass()][$fkField->getReferenceField()]];
                    break;
                case MeasurementUnitsField::class:
                    if (!\array_key_exists(MeasurementUnits::class, $referenceData)) {
                        $referenceData[MeasurementUnits::class] = MeasurementUnits::createDefaultUnits();
                    }
                    $entity[$field->getPropertyName()] = $referenceData[MeasurementUnits::class];
                    break;
                case ReferenceVersionField::class:
                    if (!\array_key_exists($field->getVersionReferenceClass() . '.' . $field->getReferenceField(), $referenceData)) {
                        $referenceData[$field->getVersionReferenceClass() . '.' . $field->getReferenceField()] = Uuid::randomHex();
                    }
                    $entity[$field->getPropertyName()] = $referenceData[$field->getVersionReferenceClass() . '.' . $field->getReferenceField()];
                    break;
                case StringField::class:
                    $entity[$field->getPropertyName()] = '[...]';
                    break;
                case TranslatedField::class:
                    $unresolvedTranslatedFields[] = $field;
                    break;
                case TranslationsAssociationField::class:
                    if (!\array_key_exists(LanguageEntity::class . '.id', $referenceData)) {
                        $referenceData[LanguageEntity::class . '.id'] = Uuid::randomHex();
                    }
                    $entity[$field->getPropertyName()] = [$referenceData[LanguageEntity::class . '.id'] => $referenceData[$field->getReferenceClass()]];
                    break;
                default:
                    $entity[$field->getPropertyName()] = '## ERROR - UNKNOWN FIELD TYPE "' . $field::class . '" ##';
                    break;
            }
        }

        foreach ($unresolvedTranslatedFields as $field) {
            $entity[$field->getPropertyName()] = $entity[EntityDefinition::TRANSLATED_FIELD][$field->getPropertyName()];
        }

        return $entity;
    }
}
