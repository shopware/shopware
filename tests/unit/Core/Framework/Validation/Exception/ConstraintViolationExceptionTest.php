<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Validation\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(ConstraintViolationException::class)]
class ConstraintViolationExceptionTest extends TestCase
{
    public function testCustomException(): void
    {
        $list = new ConstraintViolationList([
            new ConstraintViolation('foo', 'asd', [], '', '', '', null, 'test-code', new Callback($this->noop(...))),
        ]);

        $exception = new ConstraintViolationException($list, []);

        $errors = iterator_to_array($exception->getErrors(), false);
        static::assertCount(1, $errors);
        static::assertSame('VIOLATION::test-code', $errors[0]['code']);
    }

    public function noop(): void
    {
    }
}
