<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

use Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException;
use Shopware\Core\Framework\Api\Converter\Exceptions\QueryDeprecatedFieldException;
use Shopware\Core\Framework\Api\Converter\Exceptions\QueryFutureFieldException;
use Shopware\Core\Framework\Api\Converter\Exceptions\WriteDeprecatedFieldException;
use Shopware\Core\Framework\Api\Converter\Exceptions\WriteFutureFieldException;
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

    public function __construct(ConverterRegistry $converterRegistry)
    {

        $this->converterRegistry = $converterRegistry;
    }

    public function isFieldInResponseAllowed(string $entityName, string $fieldName, int $apiVersion): bool
    {
        foreach ($this->converterRegistry->getLegacyConverters($entityName, $apiVersion) as $converter) {
            if ($converter->isFieldFromFuture($fieldName)) {
                return false;
            }
        }

        foreach ($this->converterRegistry->getCurrentConverters($entityName, $apiVersion) as $converter) {
            if ($converter->isFieldDeprecated($fieldName)) {
                return false;
            }
        }

        return true;
    }

    public function convertPayload(EntityDefinition $entity, array $payload, int $apiVersion, ApiConversionException $conversionException, string $pointer = ''): array
    {
        if (\count($this->converterRegistry->getLegacyConverters($entity->getEntityName(), $apiVersion)) > 0 || \count($this->converterRegistry->getCurrentConverters($entity->getEntityName(), $apiVersion)) > 0) {
            $this->validateFields($entity, $payload, $apiVersion, $conversionException, $pointer);

            foreach ($this->converterRegistry->getLegacyConverters($entity->getEntityName(), $apiVersion) as $converter) {
                $payload = $converter->convertEntityPayloadToCurrentVersion($payload);
            }
        }

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
        $rootEntity = array_shift($entities);

        /** @var EntityDefinition $rootDefinition */
        $rootDefinition = $rootEntity['definition'];
        // ToDo handle deprecation of whole entities and endpoints

        foreach ($entities as $entity) {
            foreach ($this->converterRegistry->getLegacyConverters($rootDefinition->getEntityName(), $apiVersion) as $converter) {
                if ($converter->isFieldFromFuture($entity['entity'])) {
                    throw new QueryFutureFieldException($entity['entity'], $rootDefinition->getEntityName(), $apiVersion);
                }
            }

            foreach ($this->converterRegistry->getCurrentConverters($rootDefinition->getEntityName(), $apiVersion) as $converter) {
                if ($converter->isFieldDeprecated($entity['entity'])) {
                    throw new QueryDeprecatedFieldException($entity['entity'], $rootDefinition->getEntityName(), $apiVersion);
                }
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

    private function validateFields(EntityDefinition $entity, array $payload, int $apiVersion, ApiConversionException $conversionException, string $pointer): void
    {
        foreach ($payload as $field => $value) {
            foreach ($this->converterRegistry->getLegacyConverters($entity->getEntityName(), $apiVersion) as $converter) {
                if ($converter->isFieldFromFuture($field)) {
                    $conversionException->add(
                        new WriteFutureFieldException($field, $entity->getEntityName(), $apiVersion),
                        $pointer . '/' . $field
                    );

                    break;
                }
            }

            foreach ($this->converterRegistry->getCurrentConverters($entity->getEntityName(), $apiVersion) as $converter) {
                if ($converter->isFieldDeprecated($field)) {
                    $conversionException->add(
                        new WriteDeprecatedFieldException($field, $entity->getEntityName(), $apiVersion),
                        $pointer . '/' . $field
                    );

                    break;
                }
            }
        }
    }

    private function validateQueryField(EntityDefinition $entity, string $concatenatedFields, int $apiVersion, SearchRequestException $searchException, string $pointer = '')
    {
        $parts = explode('.', $concatenatedFields);
        $fieldName = array_shift($parts);
        if ($fieldName === $entity->getEntityName()) {
            $fieldName = array_shift($parts);
        }


        foreach ($this->converterRegistry->getLegacyConverters($entity->getEntityName(), $apiVersion) as $converter) {
            if ($converter->isFieldFromFuture($fieldName)) {
                $searchException->add(
                    new QueryFutureFieldException($fieldName, $entity->getEntityName(), $apiVersion),
                    $pointer . '/' . $fieldName
                );

                break;
            }
        }

        foreach ($this->converterRegistry->getCurrentConverters($entity->getEntityName(), $apiVersion) as $converter) {
            if ($converter->isFieldDeprecated($fieldName)) {
                $searchException->add(
                    new QueryDeprecatedFieldException($fieldName, $entity->getEntityName(), $apiVersion),
                    $pointer . '/' . $fieldName
                );

                break;
            }
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
}
