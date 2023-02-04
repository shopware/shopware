<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class InvalidSerializerFieldException extends ShopwareHttpException
{
    private readonly string $expectedClass;

    private readonly Field $field;

    public function __construct(
        string $expectedClass,
        Field $field
    ) {
        parent::__construct(
            'Expected field of type "{{ expectedField }}" got "{{ field }}".',
            ['expectedField' => $expectedClass, 'field' => $field::class]
        );

        $this->expectedClass = $expectedClass;
        $this->field = $field;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getField(): Field
    {
        return $this->field;
    }

    public function getExpectedClass(): string
    {
        return $this->expectedClass;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_FIELD_SERIALIZER';
    }
}
