<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Event\ContentTreePreparationEvent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentTreePreparationEvent::class)]
class ContentTreePreparationEventTest extends TestCase
{
    #[TestDox('Exposes the constructed forest through tree(), then replaces it via replaceTree()')]
    public function testReplaceTreeReplacesWhatTreeReturns(): void
    {
        $tree = [new StoredElement('root-id', 'section')];
        $event = $this->createEvent($tree);

        static::assertSame($tree, $event->tree());

        $replacement = [new StoredElement('injected-id', 'text')];
        $event->replaceTree($replacement);

        static::assertSame($replacement, $event->tree());
    }

    /**
     * @param array<array-key, mixed> $replacement
     */
    #[DataProvider('foreignForestProvider')]
    #[TestDox('refuses a replacement that is not a stored forest: $_dataName')]
    public function testReplaceTreeRefusesAForeignForest(array $replacement, ContentSystemException $expected): void
    {
        $event = $this->createEvent([new StoredElement('root-id', 'section')]);

        $this->expectExceptionObject($expected);

        $event->replaceTree($replacement); // @phpstan-ignore argument.type (the guard answers for callers static analysis does not see)
    }

    /**
     * @param array<array-key, mixed> $replacement
     */
    #[DataProvider('foreignForestProvider')]
    #[TestDox('refuses a construction that is not a stored forest: $_dataName')]
    public function testConstructorRefusesAForeignForest(array $replacement, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        $this->createEvent($replacement); // @phpstan-ignore argument.type (the guard answers for callers static analysis does not see)
    }

    #[TestDox('keeps the forest it holds when a replacement is refused')]
    public function testRefusedReplacementLeavesTheForestInPlace(): void
    {
        $tree = [new StoredElement('root-id', 'section')];
        $event = $this->createEvent($tree);

        try {
            $event->replaceTree([new RenderedElement('rendered-id', 'text')]); // @phpstan-ignore argument.type (the guard answers for callers static analysis does not see)
        } catch (ContentSystemException) {
        }

        static::assertSame($tree, $event->tree());
    }

    /**
     * The rendered model is the case the guard exists for: it is what a listener holds when it confuses the two
     * sides of the storage/render split, and it carries an `id`, so the pipeline's own post-event check waves it
     * through. The remaining cases are the shapes the `list<StoredElement>` docblock also promises and the
     * runtime `array` type does not.
     *
     * @return iterable<string, array{array<array-key, mixed>, ContentSystemException}>
     */
    public static function foreignForestProvider(): iterable
    {
        yield 'a rendered element' => [
            [new RenderedElement('rendered-id', 'text')],
            ContentSystemException::invalidMapValue('Stored content tree', '0', StoredElement::class, RenderedElement::class),
        ];

        yield 'a stored element behind a valid one' => [
            [new StoredElement('root-id', 'section'), new RenderedElement('rendered-id', 'text')],
            ContentSystemException::invalidMapValue('Stored content tree', '1', StoredElement::class, RenderedElement::class),
        ];

        yield 'a decoded element still in array form' => [
            [['id' => 'root-id', 'component' => 'section']],
            ContentSystemException::invalidMapValue('Stored content tree', '0', StoredElement::class, 'array'),
        ];

        yield 'a forest keyed by element id' => [
            ['root-id' => new StoredElement('root-id', 'section')],
            ContentSystemException::invalidMapValue('Stored content tree', 'tree', 'list<StoredElement>', 'array with non-list keys'),
        ];

        yield 'a forest with a gap left by an unset root' => [
            [0 => new StoredElement('first-id', 'section'), 2 => new StoredElement('third-id', 'section')],
            ContentSystemException::invalidMapValue('Stored content tree', 'tree', 'list<StoredElement>', 'array with non-list keys'),
        ];
    }

    /**
     * @param list<StoredElement> $tree
     */
    private function createEvent(array $tree): ContentTreePreparationEvent
    {
        return new ContentTreePreparationEvent(
            $tree,
            LayoutReference::create('layout-1', 'Test', null),
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            Generator::generateSalesChannelContext(),
            new RenderingCacheContext(),
        );
    }
}
