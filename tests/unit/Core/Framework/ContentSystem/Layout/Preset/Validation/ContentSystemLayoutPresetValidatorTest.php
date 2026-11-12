<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation\ContentSystemLayoutPresetValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemLayoutPresetValidator::class)]
class ContentSystemLayoutPresetValidatorTest extends TestCase
{
    #[TestDox('reports no violations for resolvable references, including the same preset used twice')]
    public function testResolvableReferencesHaveNoViolations(): void
    {
        $violations = (new ContentSystemLayoutPresetValidator())->validate('Sw:Page', [
            'Sw:Page' => $this->preset([['preset' => 'Sw:Leaf'], ['preset' => 'Sw:Leaf']]),
            'Sw:Leaf' => $this->preset([['component' => 'Sw:Content:Text']]),
        ]);

        static::assertCount(0, $violations);
    }

    #[TestDox('reports an unknown reference inside a slot for the preset holding it')]
    public function testUnknownReferenceInSlot(): void
    {
        $violations = (new ContentSystemLayoutPresetValidator())->validate('Sw:Page', [
            'Sw:Page' => $this->preset([['component' => 'Sw:Grid:Container', 'slots' => ['content' => [['preset' => 'Sw:Missing']]]]]),
        ]);

        static::assertCount(1, $violations);
        static::assertSame('Sw:Page', $violations->get(0)->getPropertyPath());
        static::assertSame(ContentSystemException::LAYOUT_PRESET_UNKNOWN_REFERENCE, $violations->get(0)->getCode());
        static::assertSame(
            'Layout preset "Sw:Page" references unknown preset "Sw:Missing" via Sw:Page -> Sw:Missing',
            $violations->get(0)->getMessage(),
        );
    }

    #[TestDox('collects every unresolvable reference, in slots and in referenced presets, instead of stopping at the first')]
    public function testCollectsAllViolations(): void
    {
        $violations = (new ContentSystemLayoutPresetValidator())->validate('Sw:Page', [
            'Sw:Page' => $this->preset([
                ['preset' => 'Sw:Missing'],
                ['component' => 'Sw:Grid:Container', 'slots' => ['content' => [['preset' => 'Sw:Page']]]],
                ['preset' => 'Sw:Nested'],
            ]),
            'Sw:Nested' => $this->preset([['preset' => 'Sw:AlsoMissing']]),
        ]);

        $messages = [];
        foreach ($violations as $violation) {
            static::assertSame('Sw:Page', $violation->getPropertyPath());
            $messages[] = $violation->getMessage();
        }

        static::assertSame([
            'Layout preset "Sw:Page" references unknown preset "Sw:Missing" via Sw:Page -> Sw:Missing',
            'Layout preset "Sw:Page" has a circular reference: Sw:Page -> Sw:Page',
            'Layout preset "Sw:Page" references unknown preset "Sw:AlsoMissing" via Sw:Page -> Sw:Nested -> Sw:AlsoMissing',
        ], $messages);
    }

    #[TestDox('stops at the limit and reports a preset whose references expand exponentially')]
    public function testExponentialExpansionIsReportedAsTooManyReferences(): void
    {
        $presets = ['Sw:Level40' => $this->preset([['component' => 'Sw:Content:Text']])];
        for ($level = 39; $level >= 0; --$level) {
            $next = 'Sw:Level' . ($level + 1);
            $presets['Sw:Level' . $level] = $this->preset([['preset' => $next], ['preset' => $next]]);
        }

        $violations = (new ContentSystemLayoutPresetValidator())->validate('Sw:Level0', $presets);

        static::assertCount(1, $violations);
        static::assertSame('Sw:Level0', $violations->get(0)->getPropertyPath());
        static::assertSame(ContentSystemException::LAYOUT_PRESET_TOO_MANY_REFERENCES, $violations->get(0)->getCode());
        static::assertSame('Layout preset "Sw:Level0" reaches the limit of 1000 nested presets', $violations->get(0)->getMessage());
    }

    #[TestDox('reports an unknown reference when the validated preset itself is missing')]
    public function testMissingValidatedPreset(): void
    {
        $violations = (new ContentSystemLayoutPresetValidator())->validate('Sw:Missing', []);

        static::assertCount(1, $violations);
        static::assertSame('Sw:Missing', $violations->get(0)->getPropertyPath());
        static::assertSame(ContentSystemException::LAYOUT_PRESET_UNKNOWN_REFERENCE, $violations->get(0)->getCode());
        static::assertSame(
            'Layout preset "Sw:Missing" references unknown preset "Sw:Missing" via Sw:Missing',
            $violations->get(0)->getMessage(),
        );
    }

    #[TestDox('reports a circular reference for a preset on the cycle and for a preset reaching it, but not for an unrelated one')]
    public function testCircularReference(): void
    {
        $presets = [
            'App:A' => $this->preset([['preset' => 'App:B']]),
            'App:B' => $this->preset([['preset' => 'App:A']]),
            'App:Page' => $this->preset([['preset' => 'App:A']]),
            'Sw:Valid' => $this->preset([['component' => 'Sw:Content:Text']]),
        ];
        $validator = new ContentSystemLayoutPresetValidator();

        $onCycle = $validator->validate('App:A', $presets);
        static::assertCount(1, $onCycle);
        static::assertSame(ContentSystemException::LAYOUT_PRESET_CIRCULAR_REFERENCE, $onCycle->get(0)->getCode());
        static::assertSame('Layout preset "App:A" has a circular reference: App:A -> App:B -> App:A', $onCycle->get(0)->getMessage());

        $reachingCycle = $validator->validate('App:Page', $presets);
        static::assertCount(1, $reachingCycle);
        static::assertSame('Layout preset "App:Page" has a circular reference: App:Page -> App:A -> App:B -> App:A', $reachingCycle->get(0)->getMessage());

        static::assertCount(0, $validator->validate('Sw:Valid', $presets));
    }

    /**
     * @param list<array<string, mixed>> $layout
     */
    private function preset(array $layout): LayoutPresetSpecificationDto
    {
        return new LayoutPresetSpecificationDto('Preset', 'A preset.', 'regular-box', $layout);
    }
}
