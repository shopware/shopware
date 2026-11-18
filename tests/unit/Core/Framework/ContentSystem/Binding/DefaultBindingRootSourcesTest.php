<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Adapter\NoneSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\DefaultBindingRootSources;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\RootSourceConfigMap;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DefaultBindingRootSources::class)]
class DefaultBindingRootSourcesTest extends TestCase
{
    private const TYPE = 'Test:Type';

    private const UNRESTRICTED = 'Test:Unrestricted';

    private const RESTRICTED = 'Test:Restricted';

    private const PRODUCT_ONLY = 'Test:ProductOnly';

    private const CATEGORY_ONLY = 'Test:CategoryOnly';

    private const TWO_ENTRIES = 'Test:TwoEntries';

    private const AMBIGUOUS = 'Test:Ambiguous';

    /**
     * @param list<BindingSpecification> $specifications
     * @param list<string>|null $expected
     */
    #[DataProvider('forTypeProvider')]
    #[TestDox('resolves the root sources of the default specification of a type: $_dataName')]
    public function testForType(array $specifications, ?array $expected): void
    {
        static::assertSame($expected, $this->defaultBindingRootSources($specifications)->forType(self::TYPE));
    }

    /**
     * @return iterable<string, array{list<BindingSpecification>, list<string>|null}>
     */
    public static function forTypeProvider(): iterable
    {
        yield 'no default specification' => [
            [self::specification('picker', self::TYPE, ['media' => ['id' => self::scoped(['product'])]])],
            null,
        ];

        yield 'a default without scoped config' => [
            [self::defaultOf(self::TYPE, ['media' => ['id' => 'literal']])],
            null,
        ];

        yield 'a default scoped over product and category lists them in known root-source order' => [
            [self::defaultOf(self::TYPE, ['breadcrumb' => [
                'property' => self::scoped(['product', 'category']),
                'type' => self::scoped(['product', 'category']),
            ]])],
            ['category', 'product'],
        ];

        yield 'two entries scoped over product and category, and over product' => [
            [self::defaultOf(self::TYPE, [
                'first' => ['id' => self::scoped(['product', 'category'])],
                'second' => ['id' => self::scoped(['product'])],
            ])],
            ['product'],
        ];

        yield 'two entries scoped over disjoint root sources' => [
            [self::defaultOf(self::TYPE, [
                'first' => ['id' => self::scoped(['product'])],
                'second' => ['id' => self::scoped(['category'])],
            ])],
            [],
        ];

        yield 'two config values of one entry scoped over product and category, and over product' => [
            [self::defaultOf(self::TYPE, ['media' => [
                'id' => self::scoped(['product', 'category']),
                'type' => self::scoped(['product']),
            ]])],
            ['product'],
        ];

        yield 'a scoped key unknown to the registry is left out, section and none keys are kept' => [
            [self::defaultOf(self::TYPE, ['media' => ['id' => self::scoped(['none', 'unknown_entity', 'header', 'product'])]])],
            ['product', 'header', 'none'],
        ];

        yield 'an empty scoped map' => [
            [self::defaultOf(self::TYPE, ['media' => ['id' => self::scoped([])]])],
            [],
        ];

        yield 'a config value carrying the marker beside a second key is not scoped' => [
            [self::defaultOf(self::TYPE, ['media' => ['id' => [RootSourceConfigMap::MARKER => ['product' => 'a'], 'other' => 'b']]])],
            null,
        ];

        yield 'two default specifications' => [
            [
                self::defaultOf(self::TYPE, ['media' => ['id' => 'literal']]),
                self::defaultOf(self::TYPE, ['media' => ['id' => 'literal']], 'app'),
            ],
            [],
        ];

        yield 'a scoped non-default specification beside an unscoped default' => [
            [
                self::defaultOf(self::TYPE, ['media' => ['id' => 'literal']]),
                self::specification('picker', self::TYPE, ['media' => ['id' => self::scoped(['product'])]]),
            ],
            null,
        ];
    }

    /**
     * @param list<StoredElement> $elements
     * @param list<string>|null $expected
     */
    #[DataProvider('forElementsProvider')]
    #[TestDox('resolves the root sources of a list of elements from their restrictions: $_dataName')]
    public function testForElements(array $elements, ?array $expected): void
    {
        $specifications = [
            self::defaultOf(self::RESTRICTED, ['breadcrumb' => [
                'property' => self::scoped(['product', 'category']),
                'type' => self::scoped(['product', 'category']),
            ]]),
            self::defaultOf(self::PRODUCT_ONLY, ['media' => ['id' => self::scoped(['product'])]]),
            self::defaultOf(self::CATEGORY_ONLY, ['media' => ['id' => self::scoped(['category'])]]),
            self::defaultOf(self::TWO_ENTRIES, [
                'first' => ['id' => self::scoped(['product'])],
                'second' => ['id' => self::scoped(['product', 'category'])],
            ]),
            self::defaultOf(self::AMBIGUOUS, ['media' => ['id' => 'literal']]),
            self::defaultOf(self::AMBIGUOUS, ['media' => ['id' => 'literal']], 'app'),
        ];

        static::assertSame($expected, $this->defaultBindingRootSources($specifications)->forElements($elements));
    }

    /**
     * @return iterable<string, array{list<StoredElement>, list<string>|null}>
     */
    public static function forElementsProvider(): iterable
    {
        yield 'an empty list' => [[], null];

        yield 'a restricted element in a slot below an unrestricted root' => [
            [self::element(self::UNRESTRICTED, slots: ['content' => [self::element(self::UNRESTRICTED), self::element(self::RESTRICTED)]])],
            ['category', 'product'],
        ];

        yield 'a restricted root with a more restricted element in its slot' => [
            [self::element(self::RESTRICTED, slots: ['content' => [self::element(self::PRODUCT_ONLY)]])],
            ['product'],
        ];

        yield 'a restricted element two slot levels below the root' => [
            [self::element(self::UNRESTRICTED, slots: ['content' => [
                self::element(self::UNRESTRICTED, slots: ['inner' => [self::element(self::RESTRICTED)]]),
            ]])],
            ['category', 'product'],
        ];

        yield 'a restricted element that already wires every scoped key' => [
            [self::element(self::RESTRICTED, wiredKeys: ['breadcrumb'])],
            null,
        ];

        yield 'a restricted element that wires one of two scoped entries' => [
            [self::element(self::TWO_ENTRIES, wiredKeys: ['first'])],
            ['category', 'product'],
        ];

        yield 'two elements restricted to product and category, and to product' => [
            [self::element(self::RESTRICTED), self::element(self::PRODUCT_ONLY)],
            ['product'],
        ];

        yield 'two elements with disjoint restrictions' => [
            [self::element(self::PRODUCT_ONLY), self::element(self::CATEGORY_ONLY)],
            [],
        ];

        yield 'an element whose type has two defaults beside an unrestricted element' => [
            [self::element(self::UNRESTRICTED), self::element(self::AMBIGUOUS)],
            [],
        ];

        yield 'an element whose type has two defaults fails even when it wires every key' => [
            [self::element(self::AMBIGUOUS, wiredKeys: ['media'])],
            [],
        ];
    }

    /**
     * @param list<BindingSpecification> $specifications
     */
    private function defaultBindingRootSources(array $specifications): DefaultBindingRootSources
    {
        $keyed = [];
        foreach ($specifications as $specification) {
            $keyed[$specification->qualifiedId()] = $specification;
        }

        $bindingRegistry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $bindingRegistry->method('all')->willReturn($keyed);

        $sectionFactories = [];
        foreach (['header', 'footer'] as $section) {
            $sectionFactories[$section] = fn (): AbstractSpecificationSource => static::createStub(AbstractSpecificationSource::class);
        }

        return new DefaultBindingRootSources($bindingRegistry, new RootSourceRegistry(
            entityTypes: ['category', 'landing_page', 'product'],
            sectionSources: new ServiceLocator($sectionFactories),
            noneSource: new NoneSpecificationSource(),
            entitySources: [],
        ));
    }

    /**
     * @param array<string, array<string, mixed>> $configs config per resolves key
     */
    private static function defaultOf(string $type, array $configs, string $source = 'core'): BindingSpecification
    {
        return self::specification($type, $type, $configs, $source);
    }

    /**
     * @param array<string, array<string, mixed>> $configs config per resolves key
     */
    private static function specification(string $id, string $type, array $configs, string $source = 'core'): BindingSpecification
    {
        return new BindingSpecification(
            $id,
            $type,
            $id,
            array_map(static fn (array $config): LoaderBinding => new LoaderBinding('entity', $config), $configs),
            [],
            $source,
        );
    }

    /**
     * @param list<string> $rootSources
     *
     * @return array{'$scoped': array<string, string>}
     */
    private static function scoped(array $rootSources): array
    {
        return [RootSourceConfigMap::MARKER => array_fill_keys($rootSources, 'value')];
    }

    /**
     * @param list<string> $wiredKeys
     * @param array<string, list<StoredElement>> $slots
     */
    private static function element(string $component, array $wiredKeys = [], array $slots = []): StoredElement
    {
        $dataRequirements = [];
        foreach ($wiredKeys as $key) {
            $dataRequirements[$key] = new DataRequirement($key, 'entity', new StubLoaderConfig());
        }

        return new StoredElement($component . '-' . \count($slots), $component, $dataRequirements, [], $slots);
    }
}
