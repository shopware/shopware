<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Validation\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConstraintViolationException::class)]
class ConstraintViolationExceptionTest extends TestCase
{
    #[TestDox('aggregates the violations into message, input data and filtered lists')]
    public function testViolationAccess(): void
    {
        $exception = self::createException();

        static::assertSame('Caught 2 violation errors.', $exception->getMessage());
        static::assertSame('FRAMEWORK__CONSTRAINT_VIOLATION', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(['name' => ''], $exception->getInputData());
        static::assertCount(2, $exception->getViolations());
        static::assertCount(1, $exception->getViolations('/name'));
        static::assertCount(0, $exception->getViolations('/other'));
    }

    #[TestDox('violation codes of known constraints are mapped to VIOLATION::<error name>')]
    public function testMapErrorCodes(): void
    {
        // mapErrorCodes() removes and re-adds mapped violations, so they move to the END of the list
        $violations = array_values(iterator_to_array(self::createException()->getViolations()));

        // no constraint attached: the code stays untouched (and the violation keeps its position)
        static::assertSame('custom-code', $violations[0]->getCode());
        static::assertSame('VIOLATION::IS_BLANK_ERROR', $violations[1]->getCode());
    }

    #[TestDox('getErrors yields one error per violation with the property path as pointer')]
    public function testGetErrors(): void
    {
        $errors = iterator_to_array(self::createException()->getErrors(), false);

        static::assertCount(2, $errors);
        // remapped violations are re-added at the end of the list (see testMapErrorCodes)
        static::assertSame('custom-code', $errors[0]['code']);
        static::assertSame('VIOLATION::IS_BLANK_ERROR', $errors[1]['code']);
        static::assertSame('This value should not be blank.', $errors[1]['detail']);
        static::assertSame('/name', $errors[1]['source']['pointer']);
    }

    /**
     * @deprecated tag:v6.8.0 - reason: getRootViolations is removed with the next major - to be removed
     */
    #[TestDox('getRootViolations filters violations without a property path')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetRootViolations(): void
    {
        $exception = self::createException();

        $root = $exception->getRootViolations();

        static::assertCount(1, $root);
        static::assertSame('', $root->get(0)->getPropertyPath());
    }

    private static function createException(): ConstraintViolationException
    {
        $notBlank = new NotBlank();
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'This value should not be blank.',
                null,
                [],
                null,
                '/name',
                '',
                null,
                NotBlank::IS_BLANK_ERROR,
                $notBlank
            ),
            new ConstraintViolation('Root level violation', null, [], null, '', null, null, 'custom-code'),
        ]);

        return new ConstraintViolationException($violations, ['name' => '']);
    }
}
