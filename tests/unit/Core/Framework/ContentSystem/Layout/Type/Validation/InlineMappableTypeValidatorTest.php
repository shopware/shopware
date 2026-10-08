<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\InlineMappableType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\InlineMappableTypeValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;

/**
 * Two rules, and the second is the one that matters: `mappable` and `inlineMappable` on one property would offer an
 * author two mapping mechanisms for the same value, so the declaration is refused at container build rather than
 * resolved by precedence at render time.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(InlineMappableTypeValidator::class)]
class InlineMappableTypeValidatorTest extends TestCase
{
    #[DataProvider('acceptsValidSpecificationProvider')]
    #[TestDox('accepts valid property specification without violations')]
    public function testAcceptsValidPropertySpecification(PropertySpecificationDto $dto): void
    {
        static::assertCount(0, $this->validate($dto));
    }

    /**
     * @return iterable<string, array{PropertySpecificationDto}>
     */
    public static function acceptsValidSpecificationProvider(): iterable
    {
        yield 'inlineMappable on string type' => [
            self::dto('text', 'string', inlineMappable: true),
        ];

        yield 'mappable alone on string type' => [
            self::dto('text', 'string', mappable: true),
        ];

        yield 'mappable alone on an FQCN type' => [
            self::dto('media', 'Shopware\Core\Content\Media\MediaEntity', mappable: true),
        ];

        yield 'neither flag set' => [
            self::dto('count', 'integer'),
        ];
    }

    #[DataProvider('rejectsInvalidSpecificationProvider')]
    #[TestDox('rejects invalid property specification with $expectedMessage')]
    public function testRejectsInvalidPropertySpecification(PropertySpecificationDto $dto, string $expectedMessage): void
    {
        $violations = $this->validate($dto);

        static::assertCount(1, $violations);
        static::assertSame('inlineMappable', $violations->get(0)->getPropertyPath());
        static::assertSame($expectedMessage, $violations->get(0)->getMessage());
    }

    /**
     * @return iterable<string, array{PropertySpecificationDto, string}>
     */
    public static function rejectsInvalidSpecificationProvider(): iterable
    {
        $typeMessage = (new InlineMappableType())->message;
        $exclusiveMessage = (new InlineMappableType())->exclusiveMessage;

        yield 'inlineMappable on integer type' => [
            self::dto('count', 'integer', inlineMappable: true),
            $typeMessage,
        ];

        yield 'inlineMappable on an FQCN type' => [
            self::dto('media', 'Shopware\Core\Content\Media\MediaEntity', inlineMappable: true),
            $typeMessage,
        ];

        // A union including string is still refused: interpolation writes text into a string, and a property that
        // may hold an integer instead has no place to put it.
        yield 'inlineMappable on a union type' => [
            self::dto('label', ['string', 'integer'], inlineMappable: true),
            $typeMessage,
        ];

        yield 'both flags on one string property' => [
            self::dto('text', 'string', mappable: true, inlineMappable: true),
            $exclusiveMessage,
        ];
    }

    #[TestDox('throws UnexpectedTypeException when constraint type is wrong')]
    public function testThrowsOnWrongConstraintType(): void
    {
        $validator = new InlineMappableTypeValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException(new NotBlank(), InlineMappableType::class));
        $validator->validate(self::dto('x', 'string'), new NotBlank());
    }

    #[TestDox('throws UnexpectedTypeException when value type is wrong')]
    public function testThrowsOnWrongValueType(): void
    {
        $validator = new InlineMappableTypeValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException('not-a-dto', PropertySpecificationDto::class));
        $validator->validate('not-a-dto', new InlineMappableType());
    }

    /**
     * @param string|list<string> $type
     */
    private static function dto(
        string $name,
        string|array $type,
        bool $mappable = false,
        bool $inlineMappable = false,
    ): PropertySpecificationDto {
        return new PropertySpecificationDto(
            $name,
            $type,
            false,
            false,
            'A title',
            'A description.',
            null,
            null,
            null,
            null,
            $mappable,
            $inlineMappable,
        );
    }

    private function validate(PropertySpecificationDto $dto): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return $validator->validate($dto);
    }
}
