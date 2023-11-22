<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CustomEntityException::class)]
class CustomEntityExceptionTest extends TestCase
{
    public function testNoLabelProperty(): void
    {
        $exception = CustomEntityException::noLabelProperty();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(CustomEntityException::CUSTOM_FIELDS_AWARE_NO_LABEL_PROPERTY, $exception->getErrorCode());
        static::assertSame('Entity must have a label property when it is custom field aware', $exception->getMessage());
    }

    public function testLabelPropertyNotDefined(): void
    {
        $labelProperty = 'some_label';
        $exception = CustomEntityException::labelPropertyNotDefined($labelProperty);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(CustomEntityException::CUSTOM_FIELDS_AWARE_LABEL_PROPERTY_NOT_DEFINED, $exception->getErrorCode());
        static::assertSame('Entity label_property "some_label" is not defined in fields', $exception->getMessage());
    }

    public function testLabelPropertyWrongType(): void
    {
        $labelProperty = 'some_label';
        $exception = CustomEntityException::labelPropertyWrongType($labelProperty);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(CustomEntityException::CUSTOM_FIELDS_AWARE_LABEL_PROPERTY_WRONG_TYPE, $exception->getErrorCode());
        static::assertSame('Entity label_property "some_label" must be a string field', $exception->getMessage());
    }
}
