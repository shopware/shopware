<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Definitions;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\StyleOptionSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Symfony\Component\Validator\Validation;

/**
 * Guards the shipped built-in option definitions: they must parse, pass validation, and keep their
 * declared value vocabulary. A malformed or renamed YAML file fails here rather than at boot.
 *
 * @internal
 */
#[CoversNothing]
class BuiltInStyleOptionDefinitionsTest extends TestCase
{
    #[TestDox('every shipped definition loads and validates into a named specification')]
    public function testBuiltInDefinitionsLoad(): void
    {
        $options = $this->loadBuiltIns();

        static::assertSame(
            ['align-self', 'col-span', 'display', 'justify-self', 'margin', 'padding', 'row-span'],
            $this->sortedNames($options),
        );
    }

    #[DataProvider('spanOptionProvider')]
    #[TestDox('$name ships as an integer bounded to the 1-12 grid range')]
    public function testSpanOptionIsBoundedInteger(string $name): void
    {
        $option = $this->loadBuiltIns()[$name];

        static::assertSame(StyleOptionValueType::TYPE_INTEGER, $option->valueType()->type());
        static::assertSame(['min' => 1, 'max' => 12], $option->valueType()->range());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function spanOptionProvider(): iterable
    {
        yield 'col-span' => ['col-span'];
        yield 'row-span' => ['row-span'];
    }

    #[DataProvider('spacingOptionProvider')]
    #[TestDox('$name ships as a string bounded to 64 characters')]
    public function testSpacingOptionIsBoundedString(string $name): void
    {
        $option = $this->loadBuiltIns()[$name];

        static::assertSame(StyleOptionValueType::TYPE_STRING, $option->valueType()->type());
        static::assertSame(64, $option->valueType()->maxLength());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function spacingOptionProvider(): iterable
    {
        yield 'margin' => ['margin'];
        yield 'padding' => ['padding'];
    }

    #[TestDox('display ships as a boolean defaulting to visible')]
    public function testDisplayIsBoolean(): void
    {
        $display = $this->loadBuiltIns()['display'];

        static::assertSame(StyleOptionValueType::TYPE_BOOLEAN, $display->valueType()->type());
        static::assertTrue($display->valueType()->default());
    }

    #[DataProvider('alignmentOptionProvider')]
    #[TestDox('$name ships as a string enum defaulting to auto')]
    public function testAlignmentOptionIsStringEnum(string $name): void
    {
        $option = $this->loadBuiltIns()[$name];

        static::assertSame(StyleOptionValueType::TYPE_STRING, $option->valueType()->type());
        static::assertSame('auto', $option->valueType()->default());
        static::assertContains('auto', $option->valueType()->enum() ?? []);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function alignmentOptionProvider(): iterable
    {
        yield 'align-self' => ['align-self'];
        yield 'justify-self' => ['justify-self'];
    }

    #[TestDox('the breakpoint primitive keeps its canonical six-key set')]
    public function testBreakpointSetIsCanonical(): void
    {
        static::assertSame(['xs', 'sm', 'md', 'lg', 'xl', 'xxl'], Breakpoint::values());
    }

    /**
     * @return array<string, StyleOptionSpecification>
     */
    private function loadBuiltIns(): array
    {
        $loader = new YamlStyleOptionLoader(
            new StyleOptionSpecificationSerializer(),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            [new StyleOptionSourceDirectory('core', $this->definitionsDirectory())],
        );

        $options = [];
        foreach ($loader->load() as $option) {
            $options[$option->name()] = $option;
        }

        return $options;
    }

    private function definitionsDirectory(): string
    {
        // Breakpoint lives directly under Style/, so its directory + /Definitions is the shipped folder
        $styleDir = \dirname((string) (new \ReflectionClass(Breakpoint::class))->getFileName());

        return $styleDir . '/Definitions';
    }

    /**
     * @param array<string, StyleOptionSpecification> $options
     *
     * @return list<string>
     */
    private function sortedNames(array $options): array
    {
        $names = array_keys($options);
        sort($names);

        return $names;
    }
}
