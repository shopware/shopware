<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\LanguageLoader\LanguageLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Content\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(VirtualRootWrapper::class)]
class VirtualRootWrapperTest extends TestCase
{
    private VirtualRootWrapper $wrapper;

    protected function setUp(): void
    {
        $this->wrapper = new VirtualRootWrapper();
    }

    #[TestDox('returns true when specification has data requirements and elements exist')]
    public function testRequiresWrapping(): void
    {
        $requirement = new DataRequirement('language', 'language', new LanguageLoaderConfig());
        $specification = new RenderingSpecification(
            'layout-1',
            [$requirement],
            PlaceholderValues::from([]),
            new Request(),
        );

        $element = ContentElementBuilder::create('Sw:Text')->build();

        static::assertTrue($this->wrapper->requiresWrapping($specification, [$element]));
    }

    #[TestDox('returns false when specification has no data requirements')]
    public function testRequiresWrappingNoRequirements(): void
    {
        $specification = new RenderingSpecification(
            'layout-1',
            [],
            PlaceholderValues::from([]),
            new Request(),
        );

        $element = ContentElementBuilder::create('Sw:Text')->build();

        static::assertFalse($this->wrapper->requiresWrapping($specification, [$element]));
    }

    #[TestDox('returns false when elements array is empty')]
    public function testRequiresWrappingNoElements(): void
    {
        $requirement = new DataRequirement('language', 'language', new LanguageLoaderConfig());
        $specification = new RenderingSpecification(
            'layout-1',
            [$requirement],
            PlaceholderValues::from([]),
            new Request(),
        );

        static::assertFalse($this->wrapper->requiresWrapping($specification, []));
    }

    #[TestDox('creates virtual root with correct identity, broadcast providers, and slot contents')]
    public function testWrapCreatesVirtualRootWithBroadcastProvidersAndSlotContents(): void
    {
        $requirement = new DataRequirement('language', 'language', new LanguageLoaderConfig());
        $specification = new RenderingSpecification(
            'layout-1',
            [$requirement],
            PlaceholderValues::from([]),
            new Request(),
        );

        $root1 = ContentElementBuilder::create('Sw:Text')->build();
        $root2 = ContentElementBuilder::create('Sw:Image')->build();

        $virtualRoot = $this->wrapper->wrap([$root1, $root2], $specification);

        static::assertSame('__page_context_root__', $virtualRoot->getId());
        static::assertSame('Sw:Internal:PageContext', $virtualRoot->getComponent());
        static::assertArrayHasKey('language', $virtualRoot->getProvidesContext());

        $slots = $virtualRoot->getSlots();
        static::assertArrayHasKey('__page_roots__', $slots);
        static::assertCount(2, iterator_to_array($slots['__page_roots__']->getIterator()));
    }

    #[TestDox('extracts original roots from a valid virtual root wrapper')]
    public function testUnwrapExtractsOriginalRoots(): void
    {
        $requirement = new DataRequirement('language', 'language', new LanguageLoaderConfig());
        $specification = new RenderingSpecification(
            'layout-1',
            [$requirement],
            PlaceholderValues::from([]),
            new Request(),
        );

        $root1 = ContentElementBuilder::create('Sw:Text')->build();
        $root2 = ContentElementBuilder::create('Sw:Image')->build();

        $virtualRoot = $this->wrapper->wrap([$root1, $root2], $specification);
        $extractedRoots = $this->wrapper->unwrap($virtualRoot);

        static::assertCount(2, $extractedRoots);
        static::assertSame($root1->getId(), $extractedRoots[0]->getId());
        static::assertSame($root2->getId(), $extractedRoots[1]->getId());
    }

    #[TestDox('throws when element is not the virtual root')]
    public function testUnwrapThrowsWhenElementIsNotVirtualRoot(): void
    {
        static::expectExceptionObject(ContentSystemException::pathIntegrityViolation(
            'Expected virtual page context root with ID "__page_context_root__", got element with ID "some-id" and component "Sw:Text"'
        ));

        $regularElement = ContentElementBuilder::create('Sw:Text', 'some-id')->build();
        $this->wrapper->unwrap($regularElement);
    }

    /**
     * @return \Generator<string, array{ContentElement, ContentSystemException}>
     */
    public static function elementsWithMissingOrEmptySlotProvider(): \Generator
    {
        $elementWithoutSlot = new ContentElement(
            '__page_context_root__',
            'Sw:Internal:PageContext',
        );

        yield 'missing slot' => [
            $elementWithoutSlot,
            ContentSystemException::pathIntegrityViolation('Virtual page context root is missing required slot "__page_roots__"'),
        ];

        $emptySlot = new SlotContent();
        $elementWithEmptySlot = new ContentElement(
            '__page_context_root__',
            'Sw:Internal:PageContext',
            [],
            [],
            ['__page_roots__' => $emptySlot],
        );

        yield 'empty slot' => [
            $elementWithEmptySlot,
            ContentSystemException::pathIntegrityViolation('Virtual page context root slot is empty - roots were lost during hydration'),
        ];
    }

    #[DataProvider('elementsWithMissingOrEmptySlotProvider')]
    #[TestDox('throws when the page roots slot is missing or empty')]
    public function testUnwrapThrowsWhenSlotIsMissingOrEmpty(
        ContentElement $element,
        ContentSystemException $expectedException
    ): void {
        static::expectExceptionObject($expectedException);

        $this->wrapper->unwrap($element);
    }

    #[TestDox('round-trips: unwrap(wrap(roots)) returns the original roots')]
    public function testWrapUnwrapRoundtrip(): void
    {
        $requirement = new DataRequirement('language', 'language', new LanguageLoaderConfig());
        $specification = new RenderingSpecification(
            'layout-1',
            [$requirement],
            PlaceholderValues::from([]),
            new Request(),
        );

        $root1 = ContentElementBuilder::create('Sw:Section', 'root-a')->build();
        $root2 = ContentElementBuilder::create('Sw:Container', 'root-b')->build();
        $originalRoots = [$root1, $root2];

        $virtualRoot = $this->wrapper->wrap($originalRoots, $specification);
        $restoredRoots = $this->wrapper->unwrap($virtualRoot);

        static::assertCount(2, $restoredRoots);
        static::assertSame('root-a', $restoredRoots[0]->getId());
        static::assertSame('root-b', $restoredRoots[1]->getId());
    }
}
