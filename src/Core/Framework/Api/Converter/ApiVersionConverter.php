<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

use Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

class ApiVersionConverter
{
    /**
     * @var ConverterRegistry
     */
    private $converterRegistry;

    public function __construct(ConverterRegistry $converterRegistry)
    {
        $this->converterRegistry = $converterRegistry;
    }

    public function convertEntity(EntityDefinition $definition, Entity $entity): array
    {
        return $entity->jsonSerialize();
    }

    public function convertPayload(EntityDefinition $definition, array $payload, ApiConversionException $conversionException, string $pointer = ''): array
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
                    $conversionException,
                    $pointer . '/' . $key . '/' . $field->getPropertyName()
                );
            }
        }

        $payload = $this->validateFields($definition, $payload);

        return $payload;
    }

    private function validateFields(EntityDefinition $definition, array $payload): array
    {
        return $this->converterRegistry->convert($definition->getEntityName(), $payload);
    }
}
