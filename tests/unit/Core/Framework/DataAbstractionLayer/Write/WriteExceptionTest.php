<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteException::class)]
class WriteExceptionTest extends TestCase
{
    #[TestDox('tryToThrow is a no-op while no error was added')]
    public function testTryToThrowWithoutErrors(): void
    {
        $exception = new WriteException();

        $exception->tryToThrow();

        static::assertSame([], $exception->getExceptions());
        static::assertSame('FRAMEWORK__WRITE_ERROR', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    #[TestDox('added errors update the aggregated message and make tryToThrow throw')]
    public function testAddUpdatesMessageAndThrows(): void
    {
        $exception = new WriteException();
        $inner = new WriteConstraintViolationException(new ConstraintViolationList([
            self::violation('This value should not be blank.', '/type'),
        ]), '/0');

        $exception->add($inner);

        static::assertSame([$inner], $exception->getExceptions());
        static::assertStringContainsString('There are 1 error(s) while writing data.', $exception->getMessage());
        static::assertStringContainsString('1. [/0/type] This value should not be blank.', $exception->getMessage());

        $this->expectExceptionObject($exception);
        $exception->tryToThrow();
    }

    #[TestDox('getErrors flattens inner shopware exceptions and generic throwables')]
    public function testGetErrorsFlattensInnerExceptions(): void
    {
        $exception = new WriteException();
        $exception->add(new WriteConstraintViolationException(new ConstraintViolationList([
            self::violation('This value should not be blank.', '/type'),
        ]), '/0'));
        $exception->add(new \RuntimeException('plain failure'));

        $errors = iterator_to_array($exception->getErrors(), false);

        static::assertCount(2, $errors);
        static::assertSame('This value should not be blank.', $errors[0]['detail']);
        static::assertSame('/0/type', $errors[0]['source']['pointer']);
        static::assertSame('plain failure', $errors[1]['detail']);
    }

    private static function violation(string $message, string $path): ConstraintViolation
    {
        return new ConstraintViolation($message, null, [], null, $path, null);
    }
}
