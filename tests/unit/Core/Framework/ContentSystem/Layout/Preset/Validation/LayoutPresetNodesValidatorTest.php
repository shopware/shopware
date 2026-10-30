<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation\LayoutPresetNodes;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation\LayoutPresetNodesValidator;
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
#[CoversClass(LayoutPresetNodesValidator::class)]
class LayoutPresetNodesValidatorTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $layout
     */
    #[DataProvider('validLayoutProvider')]
    #[TestDox('accepts $_dataName')]
    public function testAcceptsValidLayout(array $layout): void
    {
        static::assertCount(0, $this->validate($layout));
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>}>
     */
    public static function validLayoutProvider(): iterable
    {
        yield 'a component node' => [[['component' => 'Sw:Content:Text']]];

        yield 'a preset reference at root level' => [[['preset' => 'Sw:MediaAndText']]];

        yield 'a preset reference inside a slot' => [[[
            'component' => 'Sw:Grid:Container',
            'slots' => ['content' => [['component' => 'Sw:Media:Image'], ['preset' => 'Sw:MediaAndText']]],
        ]]];

        yield 'an empty layout' => [[]];
    }

    /**
     * @param list<mixed> $layout
     */
    #[DataProvider('invalidLayoutProvider')]
    #[TestDox('rejects $_dataName')]
    public function testRejectsInvalidLayout(array $layout, string $expectedPath, string $expectedMessage): void
    {
        $violations = $this->validate($layout);

        static::assertCount(1, $violations);
        static::assertSame($expectedPath, $violations->get(0)->getPropertyPath());
        static::assertSame($expectedMessage, $violations->get(0)->getMessage());
    }

    /**
     * @return iterable<string, array{list<mixed>, string, string}>
     */
    public static function invalidLayoutProvider(): iterable
    {
        yield 'a node declaring neither component nor preset' => [
            [['properties' => ['text' => 'Hello']]],
            'layout[0]',
            'A layout node must declare either "component" or "preset".',
        ];

        yield 'a node declaring both component and preset' => [
            [['component' => 'Sw:Content:Text', 'preset' => 'Sw:MediaAndText']],
            'layout[0]',
            'A layout node must declare either "component" or "preset", not both.',
        ];

        yield 'a non-string preset reference' => [
            [['preset' => ['name' => 'Sw:MediaAndText']]],
            'layout[0].preset',
            'A "preset" reference must be a non-empty string.',
        ];

        yield 'an empty preset reference' => [
            [['preset' => '']],
            'layout[0].preset',
            'A "preset" reference must be a non-empty string.',
        ];

        yield 'a preset reference with further properties' => [
            [['preset' => 'Sw:MediaAndText', 'properties' => ['text' => 'Hello'], 'slots' => []]],
            'layout[0]',
            'A "preset" reference takes no other properties, got: properties, slots.',
        ];

        yield 'a non-mapping node' => [
            ['Sw:Content:Text'],
            'layout[0]',
            'A layout node must be a mapping.',
        ];

        yield 'an invalid node nested in a slot' => [
            [['component' => 'Sw:Grid:Container', 'slots' => ['content' => [['component' => 'Sw:Media:Image'], []]]]],
            'layout[0].slots[content][1]',
            'A layout node must declare either "component" or "preset".',
        ];
    }

    #[TestDox('throws UnexpectedTypeException when constraint type is wrong')]
    public function testThrowsOnWrongConstraintType(): void
    {
        $validator = new LayoutPresetNodesValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException(new NotBlank(), LayoutPresetNodes::class));
        $validator->validate([], new NotBlank());
    }

    /**
     * @param list<mixed> $layout
     */
    private function validate(array $layout): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return $validator->validate(new LayoutPresetSpecificationDto('Preset', 'A preset.', 'regular-box', $layout));
    }
}
