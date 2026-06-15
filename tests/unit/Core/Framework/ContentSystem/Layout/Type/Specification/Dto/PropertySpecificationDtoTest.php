<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(PropertySpecificationDto::class)]
class PropertySpecificationDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    #[TestDox('accepts valid property with all required fields')]
    public function testAcceptsValidProperty(): void
    {
        $dto = new PropertySpecificationDto('variant', 'string', false, false, 'Variant', 'Product variant selector.', null, null, null);

        $violations = $this->validator->validate($dto);

        static::assertCount(0, $violations);
    }

    #[DataProvider('blankFieldProvider')]
    #[TestDox('rejects blank $field')]
    public function testRejectsBlankField(string $field, PropertySpecificationDto $dto, int $expectedViolations): void
    {
        $violations = $this->validator->validate($dto);

        static::assertGreaterThanOrEqual($expectedViolations, $violations->count());

        $violatedPaths = [];
        foreach ($violations as $violation) {
            $violatedPaths[] = $violation->getPropertyPath();
        }

        static::assertContains($field, $violatedPaths);
    }

    /**
     * @return iterable<string, array{string, PropertySpecificationDto, int}>
     */
    public static function blankFieldProvider(): iterable
    {
        yield 'blank name' => [
            'name',
            new PropertySpecificationDto('', 'string', false, false, 'Title', 'Description.', null, null, null),
            1,
        ];

        yield 'blank type' => [
            'type',
            new PropertySpecificationDto('variant', '', false, false, 'Title', 'Description.', null, null, null),
            1,
        ];

        yield 'blank title' => [
            'title',
            new PropertySpecificationDto('variant', 'string', false, false, '', 'Description.', null, null, null),
            1,
        ];

        yield 'blank description' => [
            'description',
            new PropertySpecificationDto('variant', 'string', false, false, 'Title', '', null, null, null),
            1,
        ];
    }
}
