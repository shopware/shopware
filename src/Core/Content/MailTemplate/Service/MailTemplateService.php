<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationResponseArrayAccess;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationResponseCollection;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationResponseComplexElement;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationResponseSyntax;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateValidationResponseUnknownVariable;
use Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsField;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
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
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\AssociativeArrayType;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\EventDataType;
use Shopware\Core\Framework\Event\EventData\ForeignKeyType;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Twig\Environment;
use Twig\Error\SyntaxError;

#[Package('after-sales')]
class MailTemplateService
{
    private readonly TwigVariableParser $twigVariableParser;

    /**
     * @internal
     */
    public function __construct(
        Environment $twig,
        private readonly TwigVariableParserFactory $twigVariableParserFactory,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly StringTemplateRenderer $templateRenderer,
    ) {
        $this->twigVariableParser = $this->twigVariableParserFactory->getParser($twig);
    }

    public function validateTemplate(string $mailTemplateField, string $mailTemplate, EventDataCollection $availableVariables, MailTemplateValidationResponseCollection $validationResponses): void
    {
        try {
            $usedVariables = $this->twigVariableParser->parse($mailTemplate);
            $this->validateVariables($mailTemplateField, $usedVariables, $availableVariables, $validationResponses);
        } catch (SyntaxError $exception) {
            $validationResponses->add(
                new MailTemplateValidationResponseSyntax($mailTemplateField, $exception->getRawMessage(), $exception->getTemplateLine())
            );
        }
    }

    public function render(string $mailTemplate, string $flowEventClass, Context $context): void
    {
        $result = $this->templateRenderer->render($mailTemplate, $this->generateTemplateData($flowEventClass), $context, false);
    }

    public function generateTemplateData(string $flowEventClass): array
    {
        $templateData = [];
        $referenceData = [];

        try {
            $flowEvent = new \ReflectionClass($flowEventClass);

            if (!$flowEvent->implementsInterface(FlowEventAware::class)) {
                return [];
            }

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

        $templateData['salesChannel'] = $this->generateEntityData(SalesChannelDefinition::class, $referenceData);
        $templateData['salesChannelId'] = $templateData['salesChannel']['id'];

        foreach ($eventData as $name => $type) {
            if (\array_key_exists($name, $templateData)) {
                continue;
            }

            $templateData[$name] = $this->generateEventDataTypeData($type, $referenceData);
        }

        return $templateData;
    }

    /**
     * @param array<string, string> $usedVariables
     */
    public function validateVariables(string $mailTemplateField, array $usedVariables, EventDataCollection $availableVariables, MailTemplateValidationResponseCollection $validationResponses): void
    {
        foreach ($usedVariables as $var) {
            if ($field = $availableVariables->get($var)) {
                if (!($field instanceof ScalarValueType)) {
                    $validationResponses->add(
                        new MailTemplateValidationResponseUnknownVariable(
                            $mailTemplateField,
                            $var
                        )
                    );
                }
                continue;
            }

            $this->validateComplexVariable($mailTemplateField, $var, $availableVariables, $validationResponses);
        }
    }

    private function generateEventDataTypeData(EventDataType $dataType, array &$referenceData): mixed
    {
        if ($dataType::class === AssociativeArrayType::class || $dataType::class === ArrayType::class) {
            return [];
        }

        if ($dataType::class === EntityCollectionType::class) {
            return [$this->generateEntityData($dataType->getDefinitionClass(), $referenceData)];
        }

        if ($dataType::class === EntityType::class) {
            return $this->generateEntityData($dataType->getDefinitionClass(), $referenceData);
        }

        if ($dataType::class === EntityType::class) {
            return $this->generateEntityData($dataType->getDefinitionClass(), $referenceData);
        }

        if ($dataType::class === ForeignKeyType::class) {
            if (!\array_key_exists($dataType->getReferenceClass() . '.id', $referenceData)) {
                $referenceData[$dataType->getReferenceClass() . '.id'] = Uuid::randomHex();
            }

            return $referenceData[$dataType->getReferenceClass() . '.id'];
        }

        if ($dataType::class === MailRecipientStruct::class) {
            return [
                'recipients' => 'testing@shopware.com',
                'bcc' => null,
                'cc' => null,
            ];
        }

        if ($dataType::class === ObjectType::class) {
            $object = [];

            foreach ($dataType->getData() as $key => $value) {
                $object[$key] = $this->generateEventDataTypeData($value, $referenceData);
            }

            return $object;
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
                    return 'foobar';
            }
        }

        throw new \InvalidArgumentException('Unknown event data type: ' . $dataType::class);
    }

    private function generateEntityData(string $class, array &$referenceData): array
    {
        $entity = [];
        $unresolvedTranslatedFields = [];

        $fields = $this->definitionRegistry->get($class)->getFields();

        foreach ($fields as $field) {
            if (\array_key_exists($field->getPropertyName(), $entity)) {
                continue;
            }

            if ($field::class === TranslationsAssociationField::class) {
                $referenceData[$field->getReferenceClass()] = $this->generateEntityData($field->getReferenceClass(), $referenceData);
                $entity[EntityDefinition::TRANSLATED_FIELD] = $referenceData[$field->getReferenceClass()];
            }

            if ($field instanceof AssociationField && !$field->getAutoload()) {
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
                        $referenceData[$associationField->getReferenceClass()] = $this->generateEntityData($associationField->getReferenceClass(), $referenceData);
                    }
                    $fkField =
                        $this->definitionRegistry
                            ->get($associationField->getReferenceClass())
                            ->getFields()
                            ->filter(fn (Field $field) => $field instanceof FkField && $field->getStorageName() === $associationField->getMappingReferenceColumn())
                            ->first();
                    \assert($fkField instanceof FkField);

                    if (!\array_key_exists($fkField->getReferenceClass(), $referenceData)) {
                        $referenceData[$fkField->getReferenceClass()] = $this->generateEntityData($fkField->getReferenceClass(), $referenceData);
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
                    $entity[$field->getPropertyName()] = 'foobar';
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

    private function validateComplexVariable(string $mailTemplateField, string $var, EventDataCollection $availableVariables, MailTemplateValidationResponseCollection $validationResponses): void
    {
        if (!str_contains($var, '.')) {
            $validationResponses->add(new MailTemplateValidationResponseUnknownVariable($mailTemplateField, $var));

            return;
        }

        $varParts = explode('.', $var);

        $nestedAvVars = $availableVariables->get($varParts[0]);

        for ($i = 0; $i < \count($varParts); ++$i) {
            if (!$nestedAvVars) {
                $validationResponses->add(new MailTemplateValidationResponseUnknownVariable($mailTemplateField, $var));
                break;
            }

            if ($nestedAvVars instanceof ScalarValueType && $i === \count($varParts) - 1) {
                break;
            }

            if ($nestedAvVars instanceof EntityType) {
                $prefix = $varParts[$i];
                for ($j = 0; $j <= $i; ++$j) {
                    unset($varParts[$j]);
                }

                $path = \implode('.', $varParts);

                $this->validateEntityField($mailTemplateField, $path, $prefix, $nestedAvVars->getDefinitionClass(), $validationResponses);

                break;
            }

            if ($nestedAvVars instanceof ArrayType) {
                if ($i === \count($varParts) - 1) {
                    if (!($nestedAvVars->getType() instanceof ScalarValueType)) {
                        $validationResponses->add(new MailTemplateValidationResponseComplexElement($mailTemplateField, $var));
                    }
                    break;
                }
                if (!($varParts[$i + 1] === 'first' || $varParts[$i + 1] === 'last' || \is_numeric($varParts[$i + 1]))) {
                    $validationResponses->add(new MailTemplateValidationResponseArrayAccess($mailTemplateField, $var));
                    break;
                }

                // Skipping the array access
                unset($varParts[$i + 1]);
                $varParts = \array_values($varParts);
                $nestedAvVars = $nestedAvVars->getType();
            }

            if ($nestedAvVars instanceof ObjectType) {
                $nestedAvVars = $nestedAvVars->get($varParts[$i + 1]);
                continue;
            }

            $validationResponses->add(new MailTemplateValidationResponseUnknownVariable($mailTemplateField, $var));
            break;
        }
    }

    private function validateEntityField(string $mailTemplateField, string $fieldName, string $prefix, string $entityDefinition, MailTemplateValidationResponseCollection $validationResponses): void
    {
        $parts = \explode('.', $fieldName);

        $currentFieldName = '';
        $field = null;

        for ($i = 0; $i < \count($parts); ++$i) {
            // an empty part at the end could be used for a warning -> "requested an array, should be handled accordingly"
            if (empty($parts[$i])) {
                continue;
            }

            $currentFieldName .= (empty($currentFieldName) ? '' : '.') . $parts[$i];
            $field = EntityDefinitionQueryHelper::getField($currentFieldName, $this->definitionRegistry->get($entityDefinition), $prefix);

            if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField || $field instanceof ListField) {
                if ($i === \count($parts) - 1) {
                    $validationResponses->add(new MailTemplateValidationResponseComplexElement($mailTemplateField, $currentFieldName));

                    return;
                }

                if (!($parts[$i + 1] === 'first' || $parts[$i + 1] === 'last' || \is_numeric($parts[$i + 1]))) {
                    $validationResponses->add(new MailTemplateValidationResponseArrayAccess($mailTemplateField, $currentFieldName));

                    return;
                }

                ++$i;
            }
        }

        if (!$field) {
            $validationResponses->add(new MailTemplateValidationResponseUnknownVariable($mailTemplateField, $currentFieldName));
        }
    }
}
