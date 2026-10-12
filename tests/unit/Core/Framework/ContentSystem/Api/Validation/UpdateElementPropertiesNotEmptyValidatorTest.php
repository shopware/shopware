<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutUpdateElementPropertiesRequest;
use Shopware\Core\Framework\ContentSystem\Api\UpdateElementPropertiesRequest;
use Shopware\Core\Framework\ContentSystem\Api\Validation\UpdateElementPropertiesNotEmpty;
use Shopware\Core\Framework\ContentSystem\Api\Validation\UpdateElementPropertiesNotEmptyValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdateElementPropertiesNotEmptyValidator::class)]
class UpdateElementPropertiesNotEmptyValidatorTest extends TestCase
{
    #[DataProvider('requestCarryingAnEditProvider')]
    #[TestDox('accepts $_dataName')]
    public function testAcceptsARequestCarryingAnEdit(object $request): void
    {
        static::assertCount(0, $this->validate($request));
    }

    /**
     * One row per side of the values-or-removeKeys disjunction, split across the two host DTO types; the
     * full accept/reject matrix per route lives in the two DTO tests, which drive the attribute wiring.
     *
     * @return iterable<string, array{object}>
     */
    public static function requestCarryingAnEditProvider(): iterable
    {
        yield 'a draft request carrying only values' => [
            new UpdateElementPropertiesRequest(elementId: 'block-a', values: ['headline' => 'Hi']),
        ];

        yield 'a persisted request carrying only removeKeys' => [
            new ContentLayoutUpdateElementPropertiesRequest(elementId: 'block-a', expectedVersion: null, removeKeys: ['tag']),
        ];
    }

    #[TestDox('rejects a request with both lists empty, on the values property, with the shared wording and code')]
    public function testRejectsARequestWithBothListsEmpty(): void
    {
        $violations = $this->validate(new UpdateElementPropertiesRequest(elementId: 'block-a'));

        static::assertCount(1, $violations);
        static::assertSame('values', $violations->get(0)->getPropertyPath());
        static::assertSame(
            'An update-element-properties request must carry at least one entry in "values" or "removeKeys" (updateElementPropertiesEmpty).',
            (string) $violations->get(0)->getMessage()
        );
        static::assertSame(UpdateElementPropertiesNotEmpty::EMPTY_REQUEST_ERROR, $violations->get(0)->getCode());
        static::assertSame([], $violations->get(0)->getInvalidValue());
    }

    #[TestDox('throws UnexpectedTypeException on a foreign constraint')]
    public function testThrowsOnAForeignConstraint(): void
    {
        $validator = new UpdateElementPropertiesNotEmptyValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));
        $constraint = new NotBlank();

        $this->expectExceptionObject(new UnexpectedTypeException($constraint, UpdateElementPropertiesNotEmpty::class));
        $validator->validate(new UpdateElementPropertiesRequest(elementId: 'block-a'), $constraint);
    }

    // The fail-loud arm: a third DTO wiring the constraint without being added to the validator's
    // check throws instead of validating nothing.
    #[TestDox('throws UnexpectedTypeException on a host object that is neither update-element-properties request')]
    public function testThrowsOnAForeignHostObject(): void
    {
        $validator = new UpdateElementPropertiesNotEmptyValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));
        $host = new \stdClass();

        $this->expectExceptionObject(new UnexpectedTypeException(
            $host,
            UpdateElementPropertiesRequest::class . '|' . ContentLayoutUpdateElementPropertiesRequest::class
        ));
        $validator->validate($host, new UpdateElementPropertiesNotEmpty());
    }

    /**
     * Validates against the one constraint under test in isolation; the attribute wiring per DTO is pinned
     * by the two DTO tests.
     */
    private function validate(object $request): ConstraintViolationListInterface
    {
        return Validation::createValidatorBuilder()
            ->getValidator()
            ->validate($request, new UpdateElementPropertiesNotEmpty());
    }
}
