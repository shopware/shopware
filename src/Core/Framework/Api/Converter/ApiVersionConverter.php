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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Will be removed as it is not used anymore
 */
#[Package('core')]
class ApiVersionConverter
{
    /**
     * @internal
     */
    public function __construct(private readonly ConverterRegistry $converterRegistry)
    {
    }

    /**
     * @return array<mixed>
     */
    public function convertEntity(EntityDefinition $definition, Entity $entity): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return $entity->jsonSerialize();
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function convertPayload(EntityDefinition $definition, array $payload, ApiConversionException $conversionException, string $pointer = ''): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        $toOneFields = $definition->getFields()->filter(fn (Field $field) => $field instanceof OneToOneAssociationField || $field instanceof ManyToOneAssociationField);

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

        $toManyFields = $definition->getFields()->filter(fn (Field $field) => $field instanceof OneToManyAssociationField || $field instanceof ManyToManyAssociationField);

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

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function validateFields(EntityDefinition $definition, array $payload): array
    {
        return $this->converterRegistry->convert($definition->getEntityName(), $payload);
    }
}
