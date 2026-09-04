<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Definitions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\StyleOptionSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;

/**
 * Guards the shipped built-in option definitions: they must parse, pass validation, and keep their
 * declared value vocabulary. A malformed or renamed YAML file fails here rather than at boot.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(YamlStyleOptionLoader::class)]
class BuiltInStyleOptionDefinitionsTest extends TestCase
{
    /**
     * @var array<string, StyleOptionSpecification>
     */
    private array $builtIns;

    protected function setUp(): void
    {
        $this->builtIns = $this->loadBuiltIns();
    }

    #[TestDox('loads and validates every shipped definition into a named specification')]
    public function testBuiltInDefinitionsLoad(): void
    {
        static::assertSame(
            ['align-self', 'col-span', 'display', 'justify-self', 'row-span'],
            $this->sortedNames($this->builtIns),
        );
    }

    #[DataProvider('definesSpanOptionAsIntegerProvider')]
    #[TestDox('defines $name as an integer bounded to the 1-12 grid range')]
    public function testSpanOptionIsBoundedInteger(string $name): void
    {
        $option = $this->builtIns[$name];

        static::assertSame(StyleOptionValueType::TYPE_INTEGER, $option->valueType()->type());
        static::assertSame(['min' => 1, 'max' => 12], $option->valueType()->range());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function definesSpanOptionAsIntegerProvider(): iterable
    {
        yield 'col-span' => ['col-span'];
        yield 'row-span' => ['row-span'];
    }

    #[TestDox('defines display as a boolean defaulting to visible')]
    public function testDisplayIsBoolean(): void
    {
        $display = $this->builtIns['display'];

        static::assertSame(StyleOptionValueType::TYPE_BOOLEAN, $display->valueType()->type());
        static::assertTrue($display->valueType()->default());
    }

    /**
     * @param list<string> $expectedEnum
     */
    #[DataProvider('definesAlignmentOptionAsStringEnumProvider')]
    #[TestDox('defines $name as a string enum defaulting to auto with its full declared vocabulary')]
    public function testAlignmentOptionIsStringEnum(string $name, array $expectedEnum): void
    {
        $option = $this->builtIns[$name];

        static::assertSame(StyleOptionValueType::TYPE_STRING, $option->valueType()->type());
        static::assertSame('auto', $option->valueType()->default());
        static::assertSame($expectedEnum, $option->valueType()->enum());
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function definesAlignmentOptionAsStringEnumProvider(): iterable
    {
        yield 'align-self' => ['align-self', ['auto', 'start', 'center', 'end', 'stretch', 'baseline']];
        yield 'justify-self' => ['justify-self', ['auto', 'start', 'center', 'end', 'stretch']];
    }

    #[TestDox('keeps its canonical six-key breakpoint set')]
    public function testBreakpointSetIsCanonical(): void
    {
        static::assertSame(['xs', 'sm', 'md', 'lg', 'xl', 'xxl'], Breakpoint::values());
    }

    #[DataProvider('allShippedOptionsProvider')]
    #[TestDox('marks $name as breakpoint-aware')]
    public function testAllShippedOptionsAreBreakpointAware(string $name): void
    {
        static::assertTrue($this->builtIns[$name]->breakpointAware());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allShippedOptionsProvider(): iterable
    {
        yield 'col-span' => ['col-span'];
        yield 'row-span' => ['row-span'];
        yield 'display' => ['display'];
        yield 'align-self' => ['align-self'];
        yield 'justify-self' => ['justify-self'];
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
