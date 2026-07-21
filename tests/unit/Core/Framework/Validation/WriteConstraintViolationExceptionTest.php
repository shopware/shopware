<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteConstraintViolationException::class)]
class WriteConstraintViolationExceptionTest extends TestCase
{
    #[TestDox('carries the violations, path and aggregated message')]
    public function testAccessors(): void
    {
        $exception = self::createException();

        static::assertSame('Caught 1 constraint violation errors.', $exception->getMessage());
        static::assertSame('FRAMEWORK__WRITE_CONSTRAINT_VIOLATION', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertCount(1, $exception->getViolations());
        static::assertSame('/0', $exception->getPath());

        $exception->setPath('/1');
        static::assertSame('/1', $exception->getPath());
    }

    #[TestDox('toArray exposes message, template, parameters and property path per violation')]
    public function testToArray(): void
    {
        static::assertSame([
            [
                'message' => 'This value should not be blank.',
                'messageTemplate' => 'blank-template',
                'parameters' => ['{{ value }}' => ''],
                'propertyPath' => '/type',
            ],
        ], self::createException()->toArray());
    }

    #[TestDox('getErrors prefixes the pointer with the write path')]
    public function testGetErrors(): void
    {
        $errors = iterator_to_array(self::createException()->getErrors(), false);

        static::assertCount(1, $errors);
        static::assertSame('/0/type', $errors[0]['source']['pointer']);
        static::assertSame('This value should not be blank.', $errors[0]['detail']);
    }

    private static function createException(): WriteConstraintViolationException
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'This value should not be blank.',
                'blank-template',
                ['{{ value }}' => ''],
                null,
                '/type',
                ''
            ),
        ]);

        return new WriteConstraintViolationException($violations, '/0');
    }
}
