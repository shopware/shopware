<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use DataAbstractionLayerException::invalidSerializerField instead
 */
#[Package('core')]
class InvalidSerializerFieldException extends DataAbstractionLayerException
{
    private readonly string $expectedClass;

    private readonly Field $field;

    public function __construct(
        string $expectedClass,
        Field $field
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use DataAbstractionLayerException::invalidSerializerField instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_FIELD_SERIALIZER_CODE,
            'Expected field of type "{{ expectedField }}" got "{{ field }}".',
            ['expectedField' => $expectedClass, 'field' => $field::class]
        );

        $this->expectedClass = $expectedClass;
        $this->field = $field;
    }

    public function getField(): Field
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use DataAbstractionLayerException::invalidSerializerField instead')
        );

        return $this->field;
    }

    public function getExpectedClass(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use DataAbstractionLayerException::invalidSerializerField instead')
        );

        return $this->expectedClass;
    }
}
