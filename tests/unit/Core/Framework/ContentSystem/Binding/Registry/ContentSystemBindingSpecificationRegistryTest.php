<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\AbstractContentSystemBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[CoversClass(ContentSystemBindingSpecificationRegistry::class)]
class ContentSystemBindingSpecificationRegistryTest extends TestCase
{
    #[TestDox('aggregates specifications from every loader keyed by source-qualified id')]
    public function testAggregatesSpecificationsFromAllLoadersKeyedByQualifiedId(): void
    {
        $registry = new ContentSystemBindingSpecificationRegistry([
            $this->loader($this->specification('from-media-library', 'media-gallery', 'core')),
            $this->loader($this->specification('from-product-list', 'product-grid', 'plugin:Acme')),
        ]);

        static::assertSame(
            ['core:from-media-library', 'plugin:Acme:from-product-list'],
            array_keys($registry->all())
        );
    }

    #[TestDox('byType returns only specifications matching the given type, as a list')]
    public function testByTypeFiltersByType(): void
    {
        $registry = new ContentSystemBindingSpecificationRegistry([
            $this->loader(
                $this->specification('from-media-library', 'media-gallery', 'core'),
                $this->specification('from-product-list', 'product-grid', 'core'),
                $this->specification('from-media-library-alt', 'media-gallery', 'plugin:Acme'),
            ),
        ]);

        $byType = $registry->byType('media-gallery');

        static::assertSame([0, 1], array_keys($byType));
        static::assertSame(['from-media-library', 'from-media-library-alt'], array_map(static fn (BindingSpecification $s) => $s->id(), $byType));
    }

    #[TestDox('byType returns an empty list when no specification matches the type')]
    public function testByTypeReturnsEmptyListForUnmatchedType(): void
    {
        $registry = new ContentSystemBindingSpecificationRegistry([
            $this->loader($this->specification('from-media-library', 'media-gallery', 'core')),
        ]);

        static::assertSame([], $registry->byType('unknown-type'));
    }

    #[TestDox('get resolves a specification by its source-qualified id')]
    public function testGetResolvesBySourceQualifiedId(): void
    {
        $registry = new ContentSystemBindingSpecificationRegistry([
            $this->loader($this->specification('from-media-library', 'media-gallery', 'core')),
        ]);

        $specification = $registry->get('core:from-media-library');

        static::assertNotNull($specification);
        static::assertSame('from-media-library', $specification->id());
    }

    #[TestDox('get returns null for an id that does not exist')]
    public function testGetReturnsNullForMissingId(): void
    {
        $registry = new ContentSystemBindingSpecificationRegistry([
            $this->loader($this->specification('from-media-library', 'media-gallery', 'core')),
        ]);

        static::assertNull($registry->get('missing:x'));
    }

    #[TestDox('throws when unwrapped as a decorator')]
    public function testGetDecoratedThrows(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ContentSystemBindingSpecificationRegistry::class));

        (new ContentSystemBindingSpecificationRegistry([]))->getDecorated();
    }

    #[TestDox('throws bindingSpecificationDuplicate when two loaders emit the same source-qualified id')]
    public function testThrowsOnCrossLoaderQualifiedIdCollision(): void
    {
        $registry = new ContentSystemBindingSpecificationRegistry([
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
