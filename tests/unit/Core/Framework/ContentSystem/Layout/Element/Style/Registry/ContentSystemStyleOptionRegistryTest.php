<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\AbstractContentSystemStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\ContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemStyleOptionRegistry::class)]
class ContentSystemStyleOptionRegistryTest extends TestCase
{
    #[TestDox('aggregates options from every loader keyed by name')]
    public function testAggregatesOptionsFromAllLoaders(): void
    {
        $registry = new ContentSystemStyleOptionRegistry([
            $this->loader($this->option('col-span', 'core')),
            $this->loader($this->option('brand-accent', 'plugin:Acme')),
        ]);

        $all = $registry->all();

        static::assertSame(['col-span', 'brand-accent'], array_keys($all));
        static::assertSame('plugin:Acme', $all['brand-accent']->source());
    }

    #[TestDox('aggregates non-colliding options from every loader')]
    public function testAllResolvedAggregatesNonCollidingOptions(): void
    {
        $registry = new ContentSystemStyleOptionRegistry([
            $this->loader($this->option('col-span', 'core')),
            $this->loader($this->option('brand-accent', 'plugin:Acme')),
        ]);

        $resolved = $registry->allResolved();

        static::assertSame(['col-span', 'brand-accent'], array_keys($resolved));
        static::assertSame('plugin:Acme', $resolved['brand-accent']->source());
    }

    #[TestDox('resolves a cross-loader duplicate to the higher-precedence source, not the first registered')]
    public function testAllResolvedPrefersHigherPrecedenceSource(): void
    {
        // The app source is registered first, yet core wins: precedence beats registration order.
        $registry = new ContentSystemStyleOptionRegistry([
            $this->loader($this->option('col-span', 'app:Acme')),
            $this->loader($this->option('col-span', 'core')),
        ]);

        static::assertSame('core', $registry->allResolved()['col-span']->source());
    }

    #[TestDox('keeps the first-registered option when two sources share the same precedence tier')]
    public function testAllResolvedKeepsFirstRegisteredWithinSameTier(): void
    {
        $registry = new ContentSystemStyleOptionRegistry([
            $this->loader($this->option('brand-accent', 'plugin:First')),
            $this->loader($this->option('brand-accent', 'plugin:Second')),
        ]);

        static::assertSame('plugin:First', $registry->allResolved()['brand-accent']->source());
    }

    #[TestDox('fails hard when two loaders register the same option name, naming both sources')]
    public function testFailsOnCrossLoaderDuplicate(): void
    {
        $registry = new ContentSystemStyleOptionRegistry([
            $this->loader($this->option('col-span', 'core')),
            $this->loader($this->option('col-span', 'app:Acme')),
        ]);

        $this->expectExceptionObject(ContentSystemException::styleOptionDuplicate('col-span', 'core', 'app:Acme'));

        $registry->all();
    }

    #[TestDox('throws when unwrapped as a decorator')]
    public function testGetDecoratedThrows(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ContentSystemStyleOptionRegistry::class));

        (new ContentSystemStyleOptionRegistry([]))->getDecorated();
    }

    private function option(string $name, string $source): StyleOptionSpecification
    {
        return new StyleOptionSpecification($name, new StyleOptionValueType('integer', null, null, null, null), true, null, $source);
    }

    private function loader(StyleOptionSpecification ...$options): AbstractContentSystemStyleOptionLoader
    {
        return new class(array_values($options)) extends AbstractContentSystemStyleOptionLoader {
            /**
             * @param list<StyleOptionSpecification> $options
             */
            public function __construct(private readonly array $options)
            {
            }

            public function load(): array
            {
                return $this->options;
            }
        };
    }
}
