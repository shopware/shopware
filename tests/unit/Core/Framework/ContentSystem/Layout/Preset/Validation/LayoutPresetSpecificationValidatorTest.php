<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation\LayoutPresetSpecificationValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutPresetSpecificationValidator::class)]
class LayoutPresetSpecificationValidatorTest extends TestCase
{
    #[DataProvider('acceptsValidSpecificationProvider')]
    #[TestDox('accepts a valid preset specification without violations')]
    public function testAcceptsValidSpecification(LayoutPresetSpecificationDto $dto): void
    {
        static::assertCount(0, $this->validate($dto));
    }

    /**
     * @return iterable<string, array{LayoutPresetSpecificationDto}>
     */
    public static function acceptsValidSpecificationProvider(): iterable
    {
        yield 'with layout' => [new LayoutPresetSpecificationDto('Text block', 'A text block.', 'regular-align-left', [['component' => 'Sw:Content:Text']])];
        yield 'empty layout list' => [new LayoutPresetSpecificationDto('Empty', 'Nothing here.', 'regular-circle', [])];
    }

    #[DataProvider('rejectsInvalidSpecificationProvider')]
    #[TestDox('rejects an invalid preset specification with a violation at $expectedPath')]
    public function testRejectsInvalidSpecification(LayoutPresetSpecificationDto $dto, string $expectedPath): void
    {
        $violations = $this->validate($dto);

        static::assertCount(1, $violations);
        static::assertSame($expectedPath, $violations->get(0)->getPropertyPath());
    }

    /**
     * @return iterable<string, array{LayoutPresetSpecificationDto, string}>
     */
    public static function rejectsInvalidSpecificationProvider(): iterable
    {
        yield 'blank name' => [new LayoutPresetSpecificationDto('', 'A text block.', 'regular-align-left', []), 'name'];
        yield 'blank description' => [new LayoutPresetSpecificationDto('N', '', 'regular-align-left', []), 'description'];
        yield 'blank icon' => [new LayoutPresetSpecificationDto('N', 'A text block.', '', []), 'icon'];
        yield 'non-list layout' => [new LayoutPresetSpecificationDto('N', 'A text block.', 'regular-align-left', ['not' => 'a list']), 'layout'];
    }

    private function validate(LayoutPresetSpecificationDto $dto): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return $validator->validate($dto);
    }
}
