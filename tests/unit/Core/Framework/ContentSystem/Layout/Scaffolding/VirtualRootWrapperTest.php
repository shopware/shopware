<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Scaffolding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
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
        $element = ContentElementBuilder::create('Sw:Text')->build();

        static::assertTrue($this->wrapper->requiresWrapping($this->specificationWithLanguageRequirement(), [$element]));
    }

    #[TestDox('returns false when specification has no data requirements')]
    public function testRequiresWrappingNoRequirements(): void
    {
        $specification = new RenderingSpecification(
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
        static::assertFalse($this->wrapper->requiresWrapping($this->specificationWithLanguageRequirement(), []));
    }

    #[TestDox('creates virtual root with correct identity, broadcast providers, and slot contents')]
    public function testWrapCreatesVirtualRootWithBroadcastProvidersAndSlotContents(): void
    {
        $root1 = ContentElementBuilder::create('Sw:Text')->build();
        $root2 = ContentElementBuilder::create('Sw:Image')->build();

        $virtualRoot = $this->wrapper->wrap([$root1, $root2], $this->specificationWithLanguageRequirement());

        static::assertSame('__page_context_root__', $virtualRoot->getId());
        static::assertSame('Sw:Internal:PageContext', $virtualRoot->getComponent());
        static::assertArrayHasKey('language', $virtualRoot->getProvidesContext());

        $slots = $virtualRoot->getSlots();
        static::assertArrayHasKey('__page_roots__', $slots);
        static::assertCount(2, iterator_to_array($slots['__page_roots__']->getIterator()));
    }

    #[TestDox('returns true when element is the virtual page context root')]
    public function testIsVirtualRootReturnsTrueForVirtualRoot(): void
    {
        $virtualRoot = $this->wrapper->wrap(
            [ContentElementBuilder::create('Sw:Text')->build()],
            $this->specificationWithLanguageRequirement(),
        );

        static::assertTrue($this->wrapper->isVirtualRoot($virtualRoot));
    }

    #[TestDox('returns false when element is a regular non-virtual element')]
    public function testIsVirtualRootReturnsFalseForRegularElement(): void
    {
        $element = ContentElementBuilder::create('Sw:Text', 'some-id')->build();

        static::assertFalse($this->wrapper->isVirtualRoot($element));
    }

    #[TestDox('extracts original roots from a valid virtual root wrapper')]
    public function testUnwrapExtractsOriginalRoots(): void
    {
        $root1 = ContentElementBuilder::create('Sw:Section', 'root-a')->build();
        $root2 = ContentElementBuilder::create('Sw:Container', 'root-b')->build();

        $virtualRoot = $this->wrapper->wrap([$root1, $root2], $this->specificationWithLanguageRequirement());
        $extractedRoots = $this->wrapper->unwrap($virtualRoot);

        static::assertCount(2, $extractedRoots);
        static::assertSame('root-a', $extractedRoots[0]->getId());
        static::assertSame('root-b', $extractedRoots[1]->getId());
    }

    #[DataProvider('rootlessWrapperProvider')]
    #[TestDox('rejects a wrapper whose roots slot holds no roots')]
    public function testUnwrapRejectsAWrapperWithoutRoots(
        ContentElement $malformedWrapper,
        ContentSystemException $expectedException
    ): void {
        // Fixture guard: the element really passes the identity check the caller performs, so the
        // rejection below is about the roots slot and nothing else.
        static::assertTrue($this->wrapper->isVirtualRoot($malformedWrapper));

        $this->expectExceptionObject($expectedException);

        $this->wrapper->unwrap($malformedWrapper);
    }

    /**
     * @return \Generator<string, array{ContentElement, ContentSystemException}>
     */
    public static function rootlessWrapperProvider(): \Generator
    {
        yield 'missing slot' => [
            new ContentElement(VirtualRootWrapper::VIRTUAL_ROOT_ID, 'Sw:Internal:PageContext'),
            ContentSystemException::invalidMapValue(
                'Virtual page context root slot map',
                '__page_roots__',
                'a slot holding at least one root',
                'no such slot'
            ),
        ];

        yield 'empty slot' => [
            new ContentElement(
                VirtualRootWrapper::VIRTUAL_ROOT_ID,
                'Sw:Internal:PageContext',
                [],
                [],
                ['__page_roots__' => new SlotContent()],
            ),
            ContentSystemException::invalidMapValue(
                'Virtual page context root slot map',
                '__page_roots__',
                'a slot holding at least one root',
                'an empty slot'
            ),
        ];
    }

    private function specificationWithLanguageRequirement(): RenderingSpecification
    {
        $requirement = new DataRequirement('language', 'language', new LanguageLoaderConfig());

        return new RenderingSpecification(
            [$requirement],
            PlaceholderValues::from([]),
            new Request(),
        );
    }
}
