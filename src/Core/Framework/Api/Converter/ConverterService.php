<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

use Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException;
use Shopware\Core\Framework\Api\Converter\Exceptions\QueryFutureEntityException;
use Shopware\Core\Framework\Api\Converter\Exceptions\QueryFutureFieldException;
use Shopware\Core\Framework\Api\Converter\Exceptions\QueryRemovedEntityException;
use Shopware\Core\Framework\Api\Converter\Exceptions\QueryRemovedFieldException;
use Shopware\Core\Framework\Api\Converter\Exceptions\WriteFutureFieldException;
use Shopware\Core\Framework\Api\Converter\Exceptions\WriteRemovedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ConverterService
{
    /**
     * @var ConverterRegistry
     */
    private $converterRegistry;

    private $deprecations;

    private $fromFuture;

    public function __construct(ConverterRegistry $converterRegistry)
    {
        $this->converterRegistry = $converterRegistry;
    }

    public function isAllowed(string $entityName, ?string $fieldName, int $apiVersion): bool
    {
        if ($this->isFromFuture($entityName, $fieldName, $apiVersion)) {
            return false;
        }

        if ($this->isDeprecated($entityName, $fieldName, $apiVersion)) {
            return false;
        }

        return true;
    }

    public function convertCollection(EntityDefinition $definition, EntityCollection $collection, int $apiVersion): array
    {
        $entities = [];
        foreach ($collection->getElements() as $key => $entity) {
            $entities[$key] = $this->convertEntity($definition, $entity, $apiVersion);
        }

        return $entities;
    }

    public function convertEntity(EntityDefinition $definition, Entity $entity, int $apiVersion): array
    {
        $payload = $entity->jsonSerialize();

        $payload = $this->stripNotAllowedFields($definition, $payload, $apiVersion);

        return $payload;
    }

    public function convertPayload(EntityDefinition $entity, array $payload, int $apiVersion, ApiConversionException $conversionException, string $pointer = ''): array
    {
        $payload = $this->validateFields($entity, $payload, $apiVersion, $conversionException, $pointer);

        $toOneFields = $entity->getFields()->filter(function (Field $field) {
            return $field instanceof OneToOneAssociationField || $field instanceof ManyToOneAssociationField;
        });

        /** @var OneToOneAssociationField|OneToManyAssociationField $field */
        foreach ($toOneFields as $field) {
            if (array_key_exists($field->getPropertyName(), $payload) && is_array($payload[$field->getPropertyName()])) {
                $payload[$field->getPropertyName()] = $this->convertPayload(
                    $field->getReferenceDefinition(),
                    $payload[$field->getPropertyName()],
                    $apiVersion,
                    $conversionException,
                    $pointer . '/' . $field->getPropertyName()
                );
            }
        }

        $toManyFields = $entity->getFields()->filter(function (Field $field) {
            return $field instanceof OneToManyAssociationField || $field instanceof ManyToManyAssociationField;
        });

        /** @var OneToManyAssociationField|ManyToManyAssociationField $field */
        foreach ($toManyFields as $field) {
            if (array_key_exists($field->getPropertyName(), $payload) && is_array($payload[$field->getPropertyName()])) {
                foreach ($payload[$field->getPropertyName()] as $key => $entityPayload) {
                    $payload[$field->getPropertyName()][$key] = $this->convertPayload(
                        $field instanceof ManyToManyAssociationField ? $field->getToManyReferenceDefinition() : $field->getReferenceDefinition(),
                        $entityPayload,
                        $apiVersion,
                        $conversionException,
                        $pointer . '/' . $key . '/' . $field->getPropertyName()
                    );
                }
            }
        }

        return $payload;
    }

    public function validateEntityPath(array $entities, int $apiVersion): void
    {
        $rootEntity = $entities[0];

        /** @var EntityDefinition $rootDefinition */
        $rootDefinition = $rootEntity['definition'];

        foreach ($entities as $entity) {
            if ($this->isFromFuture($rootDefinition->getEntityName(), null, $apiVersion)) {
                throw new QueryFutureEntityException($rootDefinition->getEntityName(), $apiVersion);
            }

            if ($this->isFromFuture($rootDefinition->getEntityName(), $entity['entity'], $apiVersion)) {
                throw new QueryFutureFieldException($entity['entity'], $rootDefinition->getEntityName(), $apiVersion);
            }

            if ($this->isDeprecated($rootDefinition->getEntityName(), null, $apiVersion)) {
                throw new QueryRemovedEntityException($rootDefinition->getEntityName(), $apiVersion);
            }

            if ($this->isDeprecated($rootDefinition->getEntityName(), $entity['entity'], $apiVersion)) {
                throw new QueryRemovedFieldException($entity['entity'], $rootDefinition->getEntityName(), $apiVersion);
            }

            $rootDefinition = $entity['field'] instanceof ManyToManyAssociationField ? $entity['field']->getToManyReferenceDefinition() : $entity['definition'];
        }
    }

    public function convertCriteria(EntityDefinition $entity, Criteria $criteria, int $apiVersion, SearchRequestException $searchException): void
    {
        foreach ($criteria->getSearchQueryFields() as $field) {
            $this->validateQueryField($entity, $field, $apiVersion, $searchException);
        }
    }

    private function validateFields(EntityDefinition $entity, array $payload, int $apiVersion, ApiConversionException $conversionException, string $pointer): array
    {
        foreach ($payload as $field => $value) {
            if ($this->isFromFuture($entity->getEntityName(), $field, $apiVersion)) {
                $conversionException->add(
                    new WriteFutureFieldException($field, $entity->getEntityName(), $apiVersion),
                    $pointer . '/' . $field
                );

                continue;
            }

            if ($this->isDeprecated($entity->getEntityName(), $field, $apiVersion)) {
                $conversionException->add(
                    new WriteRemovedFieldException($field, $entity->getEntityName(), $apiVersion),
                    $pointer . '/' . $field
                );

                continue;
            }
        }

        $futureConverter = $this->converterRegistry->getFutureConverter($apiVersion);

        return $futureConverter->convert($entity->getEntityName(), $payload);
    }

    private function validateQueryField(EntityDefinition $entity, string $concatenatedFields, int $apiVersion, SearchRequestException $searchException, string $pointer = ''): void
    {
        $parts = explode('.', $concatenatedFields);
        $fieldName = array_shift($parts);
        if ($fieldName === $entity->getEntityName()) {
            $fieldName = array_shift($parts);
        }

        if ($this->isFromFuture($entity->getEntityName(), $fieldName, $apiVersion)) {
            $searchException->add(
                new QueryFutureFieldException($fieldName, $entity->getEntityName(), $apiVersion),
                $pointer . '/' . $fieldName
            );
        }

        if ($this->isDeprecated($entity->getEntityName(), $fieldName, $apiVersion)) {
            $searchException->add(
                new QueryRemovedFieldException($fieldName, $entity->getEntityName(), $apiVersion),
                $pointer . '/' . $fieldName
            );
        }

        if (count($parts) === 0) {
            return;
        }

        $field = $entity->getField($fieldName);

        if (!$field || !$field instanceof AssociationField) {
            return;
        }

        $entity = $field instanceof ManyToManyAssociationField ? $field->getToManyReferenceDefinition() : $field->getReferenceDefinition();

        $this->validateQueryField($entity, implode('.', $parts), $apiVersion, $searchException, $pointer . '/' . $fieldName);
    }

    private function stripNotAllowedFields(EntityDefinition $definition, $payload, int $apiVersion): array
    {
        foreach ($payload as $field => $value) {
            if ($this->isFromFuture($definition->getEntityName(), $field, $apiVersion)) {
                unset($payload[$field]);

                continue;
            }

            if ($this->isDeprecated($definition->getEntityName(), $field, $apiVersion)) {
                unset($payload[$field]);

                continue;
            }
        }

        return $this->stripAssociations($definition, $payload, $apiVersion);
    }

    private function stripAssociations(EntityDefinition $definition, array $payload, int $apiVersion): array
    {
        $toOneFields = $definition->getFields()->filter(function (Field $field) {
            return $field instanceof OneToOneAssociationField || $field instanceof ManyToOneAssociationField;
        });

        /** @var OneToOneAssociationField|OneToManyAssociationField $field */
        foreach ($toOneFields as $field) {
            if (array_key_exists($field->getPropertyName(), $payload) && is_array($payload[$field->getPropertyName()])) {
                $payload[$field->getPropertyName()] = $this->stripNotAllowedFields(
                    $field->getReferenceDefinition(),
                    $payload[$field->getPropertyName()],
                    $apiVersion
                );
            }
        }

        $toManyFields = $definition->getFields()->filter(function (Field $field) {
            return $field instanceof OneToManyAssociationField || $field instanceof ManyToManyAssociationField;
        });

        /** @var OneToManyAssociationField|ManyToManyAssociationField $field */
        foreach ($toManyFields as $field) {
            if (array_key_exists($field->getPropertyName(), $payload) && is_array($payload[$field->getPropertyName()])) {
                foreach ($payload[$field->getPropertyName()] as $key => $entityPayload) {
                    $payload[$field->getPropertyName()] = $this->stripNotAllowedFields(
                        $field instanceof ManyToManyAssociationField ? $field->getToManyReferenceDefinition() : $field->getReferenceDefinition(),
                        $entityPayload,
                        $apiVersion
                    );
                }
            }
        }

        return $payload;
    }

    private function isDeprecated(string $entityName, ?string $fieldName, int $apiVersion): bool
    {
        if (!isset($this->deprecations[$apiVersion][$entityName][$fieldName])) {
            $deprecatedConverter = $this->converterRegistry->getDeprecationConverter($apiVersion);
            $this->deprecations[$apiVersion][$entityName][$fieldName] = $deprecatedConverter->isDeprecated($entityName, $fieldName);
        }

        return $this->deprecations[$apiVersion][$entityName][$fieldName];
    }

    private function isFromFuture(string $entityName, ?string $fieldName, int $apiVersion): bool
    {
        if (!isset($this->fromFuture[$apiVersion][$entityName][$fieldName])) {
            $futureConverter = $this->converterRegistry->getFutureConverter($apiVersion);
            $this->fromFuture[$apiVersion][$entityName][$fieldName] = $futureConverter->isFromFuture($entityName, $fieldName);
        }

        return $this->fromFuture[$apiVersion][$entityName][$fieldName];
    }
}
