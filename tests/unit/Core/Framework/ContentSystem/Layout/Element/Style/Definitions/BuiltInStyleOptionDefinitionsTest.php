<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Definitions;

use PHPUnit\Framework\Attributes\CoversNothing;
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

    #[TestDox('col-span and row-span ship as integers bounded to the 1-12 grid range')]
    public function testSpanOptionsAreBoundedIntegers(): void
    {
        $options = $this->loadBuiltIns();

        foreach (['col-span', 'row-span'] as $name) {
            static::assertSame(StyleOptionValueType::TYPE_INTEGER, $options[$name]->valueType()->type());
            static::assertSame(['min' => 1, 'max' => 12], $options[$name]->valueType()->range());
        }
    }

    #[TestDox('margin and padding ship as bounded strings')]
    public function testSpacingOptionsAreBoundedStrings(): void
    {
        $options = $this->loadBuiltIns();

        foreach (['margin', 'padding'] as $name) {
            static::assertSame(StyleOptionValueType::TYPE_STRING, $options[$name]->valueType()->type());
            static::assertSame(64, $options[$name]->valueType()->maxLength());
        }
    }

    #[TestDox('display ships as a boolean defaulting to visible')]
    public function testDisplayIsBoolean(): void
    {
        $display = $this->loadBuiltIns()['display'];

        static::assertSame(StyleOptionValueType::TYPE_BOOLEAN, $display->valueType()->type());
        static::assertTrue($display->valueType()->default());
    }

    #[TestDox('align-self and justify-self ship as string enums defaulting to auto')]
    public function testAlignmentOptionsAreStringEnums(): void
    {
        $options = $this->loadBuiltIns();

        foreach (['align-self', 'justify-self'] as $name) {
            static::assertSame(StyleOptionValueType::TYPE_STRING, $options[$name]->valueType()->type());
            static::assertSame('auto', $options[$name]->valueType()->default());
            static::assertContains('auto', $options[$name]->valueType()->enum() ?? []);
        }
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
