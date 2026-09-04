<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\AbstractContentSystemBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemBindingSpecificationRegistry::class)]
class ContentSystemBindingSpecificationRegistryTest extends TestCase
{
    #[TestDox('aggregates specifications from every loader keyed by source-qualified id')]
    public function testAggregatesSpecificationsFromAllLoadersKeyedByQualifiedId(): void
    {
        $registry = $this->registry([
            $this->loader($this->specification('media-picker', 'media-gallery', 'core')),
            $this->loader($this->specification('from-product-list', 'product-grid', 'plugin:Acme')),
        ]);

        static::assertSame(
            ['core:media-picker', 'plugin:Acme:from-product-list'],
            array_keys($registry->all())
        );
    }

    #[TestDox('returns only specifications matching the given type, as a list')]
    public function testByTypeFiltersByType(): void
    {
        $registry = $this->registry([
            $this->loader(
                $this->specification('media-picker', 'media-gallery', 'core'),
                $this->specification('from-product-list', 'product-grid', 'core'),
                $this->specification('media-picker-alt', 'media-gallery', 'plugin:Acme'),
            ),
        ]);

        $byType = $registry->byType('media-gallery');

        static::assertSame([0, 1], array_keys($byType));
        static::assertSame(['media-picker', 'media-picker-alt'], array_map(static fn (BindingSpecification $s) => $s->id(), $byType));
    }

    #[TestDox('resolves a specification by its source-qualified id')]
    public function testGetResolvesBySourceQualifiedId(): void
    {
        $registry = $this->registry([
            $this->loader($this->specification('media-picker', 'media-gallery', 'core')),
        ]);

        $specification = $registry->get('core:media-picker');

        static::assertNotNull($specification);
        static::assertSame('media-picker', $specification->id());
    }

    #[TestDox('returns an empty list when no specification matches the type')]
    public function testByTypeReturnsEmptyListForUnmatchedType(): void
    {
        $registry = $this->registry([
            $this->loader($this->specification('media-picker', 'media-gallery', 'core')),
        ]);

        static::assertSame([], $registry->byType('unknown-type'));
    }

    #[TestDox('returns null for an id that does not exist')]
    public function testGetReturnsNullForMissingId(): void
    {
        $registry = $this->registry([
            $this->loader($this->specification('media-picker', 'media-gallery', 'core')),
        ]);

        static::assertNull($registry->get('missing:x'));
    }

    #[TestDox('throws when unwrapped as a decorator')]
    public function testGetDecoratedThrows(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ContentSystemBindingSpecificationRegistry::class));

        $this->registry([])->getDecorated();
    }

    #[TestDox('throws when calling invalidate on the leaf registry, per the decoration-pattern contract')]
    public function testInvalidateOnLeafRegistryThrows(): void
    {
        // invalidate() is defined on the abstract base (self::class), inherited unchanged by the leaf;
        // only the cached decorator overrides it. So the exception names the abstract base class.
        $this->expectExceptionObject(new DecorationPatternException(AbstractContentSystemBindingSpecificationRegistry::class));

        $this->registry([])->invalidate();
    }

    #[TestDox('throws bindingSpecificationDuplicate when two loaders emit the same source-qualified id')]
    public function testThrowsOnCrossLoaderQualifiedIdCollision(): void
    {
        $registry = $this->registry([
            $this->loader($this->specification('dup', 'Sw:Product', 'app:Acme')),
            $this->loader($this->specification('dup', 'Sw:Product', 'app:Acme')),
        ]);

        try {
            $registry->all();
            static::fail('Expected ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::BINDING_SPECIFICATION_DUPLICATE, $e->getErrorCode());
        }
    }

    private function specification(string $id, string $type, string $source): BindingSpecification
    {
        return new BindingSpecification($id, $type, 'label', [], [], $source);
    }

    /**
     * @param list<AbstractContentSystemBindingSpecificationLoader> $loaders
     */
    private function registry(array $loaders): ContentSystemBindingSpecificationRegistry
    {
        return new ContentSystemBindingSpecificationRegistry($loaders);
    }

    private function loader(BindingSpecification ...$specifications): AbstractContentSystemBindingSpecificationLoader
    {
        return new class(array_values($specifications)) extends AbstractContentSystemBindingSpecificationLoader {
            /**
             * @param list<BindingSpecification> $specifications
             */
            public function __construct(private readonly array $specifications)
            {
            }

            public function load(): array
            {
                return $this->specifications;
            }
        };
    }
}
