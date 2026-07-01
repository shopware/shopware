<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\WellFormedBindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\WellFormedBindingSpecificationValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(WellFormedBindingSpecificationValidator::class)]
class WellFormedBindingSpecificationValidatorTest extends TestCase
{
    #[TestDox('accepts a well-formed binding specification declaration without violations')]
    public function testAcceptsWellFormedDeclaration(): void
    {
        $dto = new BindingSpecificationDto(
            'media-gallery',
            'From media library',
            ['image' => ['loader' => 'entity', 'config' => ['entity' => 'media']]],
            ['alt' => ['default' => 'fallback alt']],
        );

        static::assertCount(0, $this->validate($dto));
    }

    #[TestDox('accepts an empty resolves/inputs declaration without violations')]
    public function testAcceptsEmptyResolvesAndInputs(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'From media library', [], []);

        static::assertCount(0, $this->validate($dto));
    }

    #[TestDox('accepts absent (null) resolves/inputs, as when the YAML body omits both keys')]
    public function testAcceptsNullResolvesAndInputs(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'From media library', null, null);

        static::assertCount(0, $this->validate($dto));
    }

    #[DataProvider('rejectsMalformedDeclarationProvider')]
    #[TestDox('rejects $_dataName with a violation at $expectedPath containing $expectedMessage')]
    public function testRejectsMalformedDeclaration(BindingSpecificationDto $dto, string $expectedPath, string $expectedMessage): void
    {
        $violations = $this->validate($dto);

        static::assertGreaterThanOrEqual(1, $violations->count());

        $paths = [];
        foreach ($violations as $violation) {
            $paths[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        static::assertArrayHasKey($expectedPath, $paths);
        static::assertStringContainsString($expectedMessage, $paths[$expectedPath]);
    }

    /**
     * @return iterable<string, array{BindingSpecificationDto, string, string}>
     */
    public static function rejectsMalformedDeclarationProvider(): iterable
    {
        yield 'type is not a string' => [
            new BindingSpecificationDto(42, 'label', [], []),
            'type',
            'type must not be blank',
        ];

        yield 'type is blank' => [
            new BindingSpecificationDto('', 'label', [], []),
            'type',
            'type must not be blank',
        ];

        yield 'label is not a string' => [
            new BindingSpecificationDto('media-gallery', false, [], []),
            'label',
            'label must not be blank',
        ];

        yield 'resolves is not an array' => [
            new BindingSpecificationDto('media-gallery', 'label', 'not-an-array', []),
            'resolves',
            'resolves must be an array',
        ];

        yield 'resolves entry is not an array' => [
            new BindingSpecificationDto('media-gallery', 'label', ['image' => 'not-an-array'], []),
            'resolves[image]',
            'resolves entry "image" must be an array',
        ];

        yield 'resolves entry is missing loader' => [
            new BindingSpecificationDto('media-gallery', 'label', ['image' => ['config' => []]], []),
            'resolves[image].loader',
            'must declare a non-blank "loader"',
        ];

        yield 'resolves entry has a blank loader' => [
            new BindingSpecificationDto('media-gallery', 'label', ['image' => ['loader' => '']], []),
            'resolves[image].loader',
            'must declare a non-blank "loader"',
        ];

        yield 'resolves entry config is not an array' => [
            new BindingSpecificationDto('media-gallery', 'label', ['image' => ['loader' => 'entity', 'config' => 'not-an-array']], []),
            'resolves[image].config',
            'config" must be an array',
        ];

        yield 'inputs is not an array' => [
            new BindingSpecificationDto('media-gallery', 'label', [], 'not-an-array'),
            'inputs',
            'inputs must be an array',
        ];

        yield 'inputs entry is not an array' => [
            new BindingSpecificationDto('media-gallery', 'label', [], ['alt' => 'not-an-array']),
            'inputs[alt]',
            'inputs entry "alt" must be an array',
        ];

        yield 'inputs entry default is non-scalar' => [
            new BindingSpecificationDto('media-gallery', 'label', [], ['alt' => ['default' => ['not', 'a', 'scalar']]]),
            'inputs[alt].default',
            'default" must be a scalar or null',
        ];
    }

    #[TestDox('accepts an inputs entry whose default is explicitly null')]
    public function testAcceptsInputsEntryWithNullDefault(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], ['alt' => ['default' => null]]);

        static::assertCount(0, $this->validate($dto));
    }

    #[TestDox('throws UnexpectedTypeException when the constraint type is wrong')]
    public function testThrowsOnWrongConstraintType(): void
    {
        $validator = new WellFormedBindingSpecificationValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException(new NotBlank(), WellFormedBindingSpecification::class));
        $validator->validate(new BindingSpecificationDto('media-gallery', 'label', [], []), new NotBlank());
    }

    #[TestDox('throws UnexpectedTypeException when the value type is wrong')]
    public function testThrowsOnWrongValueType(): void
    {
        $validator = new WellFormedBindingSpecificationValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException('not-a-dto', BindingSpecificationDto::class));
        $validator->validate('not-a-dto', new WellFormedBindingSpecification());
    }

    private function validate(BindingSpecificationDto $dto): ConstraintViolationListInterface
    {
        // Validate against the explicit structural constraint only, NOT via attribute mapping: the DTO also
        // carries the dep-injected TypeConsistentBindingSpecification, whose validator the default (no-arg)
        // constraint-validator factory here cannot construct. This isolates the structural rule under test;
        // the semantic §6 constraint is covered by its own container-based integration test.
        return Validation::createValidatorBuilder()
            ->getValidator()
            ->validate($dto, new WellFormedBindingSpecification());
    }
}
