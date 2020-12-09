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
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;

class ApiVersionConverter
{
    /**
     * @var ConverterRegistry
     */
    private $converterRegistry;

    /**
     * @var array[int]array[string]string
     */
    private $deprecations;

    /**
     * @var array[int]array[string]string
     */
    private $fromFuture;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(ConverterRegistry $converterRegistry, RequestStack $requestStack)
    {
        $this->converterRegistry = $converterRegistry;
        $this->requestStack = $requestStack;
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

    public function convertEntity(EntityDefinition $definition, Entity $entity, int $apiVersion): array
    {
        $payload = $entity->jsonSerialize();

        $payload = $this->stripNotAllowedFields($definition, $payload, $apiVersion);

        return $payload;
    }

    public function convertPayload(EntityDefinition $definition, array $payload, int $apiVersion, ApiConversionException $conversionException, string $pointer = ''): array
    {
        $toOneFields = $definition->getFields()->filter(function (Field $field) {
            return $field instanceof OneToOneAssociationField || $field instanceof ManyToOneAssociationField;
        });

        /** @var OneToOneAssociationField|OneToManyAssociationField $field */
        foreach ($toOneFields as $field) {
            if (!\array_key_exists($field->getPropertyName(), $payload) || !\is_array($payload[$field->getPropertyName()])) {
                continue;
            }

            $payload[$field->getPropertyName()] = $this->convertPayload(
                $field->getReferenceDefinition(),
                $payload[$field->getPropertyName()],
                $apiVersion,
                $conversionException,
                $pointer . '/' . $field->getPropertyName()
            );
        }

        $toManyFields = $definition->getFields()->filter(function (Field $field) {
            return $field instanceof OneToManyAssociationField || $field instanceof ManyToManyAssociationField;
        });

        /** @var OneToManyAssociationField|ManyToManyAssociationField $field */
        foreach ($toManyFields as $field) {
            if (!\array_key_exists($field->getPropertyName(), $payload) || !\is_array($payload[$field->getPropertyName()])) {
                continue;
            }

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

        $payload = $this->validateFields($definition, $payload, $apiVersion, $conversionException, $pointer);

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

    public function convertCriteria(EntityDefinition $definition, Criteria $criteria, int $apiVersion, SearchRequestException $searchException): void
    {
        foreach ($criteria->getAllFields() as $field) {
            $this->validateQueryField($definition, $field, $apiVersion, $searchException);
        }
    }

    protected function ignoreDeprecations(): bool
    {
        // We don't have a request
        if ($this->requestStack->getMasterRequest() === null) {
            return false;
        }

        return $this->requestStack->getMasterRequest()->headers->get(PlatformRequest::HEADER_IGNORE_DEPRECATIONS) === 'true';
    }

    private function validateFields(EntityDefinition $definition, array $payload, int $apiVersion, ApiConversionException $conversionException, string $pointer): array
    {
        foreach ($payload as $field => $_value) {
            if ($this->isFromFuture($definition->getEntityName(), $field, $apiVersion)) {
                $conversionException->add(
                    new WriteFutureFieldException($field, $definition->getEntityName(), $apiVersion),
                    $pointer . '/' . $field
                );

                continue;
            }

            if ($this->isDeprecated($definition->getEntityName(), $field, $apiVersion)) {
                $conversionException->add(
                    new WriteRemovedFieldException($field, $definition->getEntityName(), $apiVersion),
                    $pointer . '/' . $field
                );
            }
        }

        return $this->converterRegistry->convert($apiVersion, $definition->getEntityName(), $payload);
    }

    private function validateQueryField(EntityDefinition $definition, string $concatenatedFields, int $apiVersion, SearchRequestException $searchException, string $pointer = ''): void
    {
        $parts = explode('.', $concatenatedFields);
        $fieldName = array_shift($parts);
        if ($fieldName === $definition->getEntityName()) {
            $fieldName = array_shift($parts);
        }

        if ($this->isFromFuture($definition->getEntityName(), $fieldName, $apiVersion)) {
            $searchException->add(
                new QueryFutureFieldException($fieldName, $definition->getEntityName(), $apiVersion),
                $pointer . '/' . $fieldName
            );
        }

        if ($this->isDeprecated($definition->getEntityName(), $fieldName, $apiVersion)) {
            $searchException->add(
                new QueryRemovedFieldException($fieldName, $definition->getEntityName(), $apiVersion),
                $pointer . '/' . $fieldName
            );
        }

        if (\count($parts) === 0) {
            return;
        }

        $field = $definition->getField($fieldName);

        if (!$field || !$field instanceof AssociationField) {
            return;
        }

        $definition = $field instanceof ManyToManyAssociationField ? $field->getToManyReferenceDefinition() : $field->getReferenceDefinition();

        $this->validateQueryField($definition, implode('.', $parts), $apiVersion, $searchException, $pointer . '/' . $fieldName);
    }

    private function stripNotAllowedFields(EntityDefinition $definition, array $payload, int $apiVersion): array
    {
        foreach ($payload as $field => $_value) {
            if ($this->isFromFuture($definition->getEntityName(), $field, $apiVersion)) {
                unset($payload[$field]);

                continue;
            }

            if ($this->isDeprecated($definition->getEntityName(), $field, $apiVersion)) {
                unset($payload[$field]);
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
            if (\array_key_exists($field->getPropertyName(), $payload) && \is_array($payload[$field->getPropertyName()])) {
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
            if (\array_key_exists($field->getPropertyName(), $payload) && \is_array($payload[$field->getPropertyName()])) {
                foreach ($payload[$field->getPropertyName()] as $entityPayload) {
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
            $isDeprecated = !$this->ignoreDeprecations()
                && $this->converterRegistry->isDeprecated($apiVersion, $entityName, $fieldName);
            $this->deprecations[$apiVersion][$entityName][$fieldName] = $isDeprecated;
        }

        return $this->deprecations[$apiVersion][$entityName][$fieldName];
    }

    private function isFromFuture(string $entityName, ?string $fieldName, int $apiVersion): bool
    {
        if (!isset($this->fromFuture[$apiVersion][$entityName][$fieldName])) {
            $this->fromFuture[$apiVersion][$entityName][$fieldName] = $this->converterRegistry->isFromFuture($apiVersion, $entityName, $fieldName);
        }

        return $this->fromFuture[$apiVersion][$entityName][$fieldName];
    }
}
