<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\RootSourceConfigMap;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * Proves at the registry level: every specification the aggregated registry serves in the integration
 * environment carries only canonical {@see LoaderBinding}s,
 * each `resolves` entry's loader is a registered data-loader source and its config decodes through that loader's
 * config serializer without error. This is the runtime counterpart to the load-time canonicalization the sugar
 * ladder performs: no sugar shape survives into a registered specification. The registry is built from the real
 * container loaders so `all()` reflects production aggregation without the cross-request cache.
 *
 * @internal
 */
#[Package('framework')]
class RegistryBindingSpecificationCanonicityTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('serves only loader bindings whose loader is a registered data-loader source')]
    public function testEveryRegisteredLoaderBindingNamesARegisteredDataLoaderSource(): void
    {
        $map = $this->dataLoaderMap();
        $bindings = $this->registeredLoaderBindings();

        $unregisteredLoaders = array_map(
            static fn (LoaderBinding $binding): string => $binding->loader,
            array_filter($bindings, static fn (LoaderBinding $binding): bool => !isset($map->sourceToConfigSpecifications[$binding->loader])),
        );

        static::assertNotSame([], $bindings, 'The integration environment must register at least one loader binding.');
        static::assertSame([], $unregisteredLoaders);
    }

    #[TestDox('serves only loader bindings whose scoped config values name one root-source set')]
    public function testEveryRegisteredLoaderBindingNamesOneRootSourceSet(): void
    {
        $bindings = $this->registeredLoaderBindings();

        $conflicting = array_keys(array_filter(
            $bindings,
            static fn (LoaderBinding $binding): bool => RootSourceConfigMap::branches($binding->config) === null,
        ));

        static::assertNotSame([], $bindings, 'The integration environment must register at least one loader binding.');
        static::assertSame([], $conflicting);
    }

    #[TestDox('serves only loader bindings whose config decodes through their loader serializer')]
    public function testEveryRegisteredLoaderBindingConfigDecodesThroughItsSerializer(): void
    {
        $serializers = $this->configSerializers();
        $bindings = $this->registeredLoaderBindings();

        $decoded = [];

        foreach ($bindings as $binding) {
            foreach (RootSourceConfigMap::branches($binding->config) ?? [] as $branchConfig) {
                $decoded[] = $serializers->decode($binding->loader, $branchConfig);
            }
        }

        static::assertNotSame([], $decoded, 'At least one loader binding config branch must decode.');
    }

    #[TestDox('the synthesized core:Sw:Media:Image default is served with its canonical entity wiring, no inputs, and is the type default')]
    public function testSwMediaImageDefaultIsServedInCanonicalForm(): void
    {
        $specification = $this->registry()->get('core:Sw:Media:Image');

        static::assertInstanceOf(BindingSpecification::class, $specification, 'The synthesized core:Sw:Media:Image default must be registered.');
        static::assertSame('Sw:Media:Image', $specification->type());
        static::assertTrue($specification->isDefault(), '"Sw:Media:Image" === "Sw:Media:Image", so this specification is the type\'s default.');

        $mediaBinding = $specification->resolves()['media'] ?? null;
        static::assertInstanceOf(LoaderBinding::class, $mediaBinding, 'core:Sw:Media:Image must wire the media reference.');
        static::assertSame('entity', $mediaBinding->loader);
        static::assertSame('media', $mediaBinding->config['entity'] ?? null, 'The tier-A shorthand must canonicalize to an explicit media entity name.');
        static::assertSame('mediaId', $mediaBinding->config['property'] ?? null, 'The configured property must remain the authored mediaId storage key.');

        static::assertSame([], $specification->inputs(), 'The synthesized default carries no inputs; mediaId is an undeclared storage key.');
    }

    #[TestDox('the core product slider default explicitly targets products through the entity collection loader')]
    public function testSwProductSliderDefaultExplicitlyTargetsProducts(): void
    {
        $specification = $this->registry()->get('core:Sw:Product:Slider');

        static::assertInstanceOf(BindingSpecification::class, $specification);

        $productsBinding = $specification->resolves()['products'] ?? null;
        static::assertInstanceOf(LoaderBinding::class, $productsBinding);
        static::assertSame('entity_collection', $productsBinding->loader);
        static::assertSame(
            ['entity' => 'product', 'property' => 'productIds'],
            $productsBinding->config,
        );
    }

    private function registry(): ContentSystemBindingSpecificationRegistry
    {
        return new ContentSystemBindingSpecificationRegistry(
            [$this->yamlLoader(), $this->databaseLoader()],
        );
    }

    /**
     * @return array<string, LoaderBinding> every loader binding of every registered specification, keyed `qualifiedId.resolves[referenceKey]`
     */
    private function registeredLoaderBindings(): array
    {
        $bindings = [];

        foreach ($this->registry()->all() as $qualifiedId => $specification) {
            foreach ($specification->resolves() as $referenceKey => $binding) {
                $bindings[\sprintf('%s.resolves[%s]', $qualifiedId, $referenceKey)] = $binding;
            }
        }

        return $bindings;
    }

    private function yamlLoader(): YamlBindingSpecificationLoader
    {
        $loader = static::getContainer()->get(YamlBindingSpecificationLoader::class);
        static::assertInstanceOf(YamlBindingSpecificationLoader::class, $loader);

        return $loader;
    }

    private function databaseLoader(): DatabaseBindingSpecificationLoader
    {
        $loader = static::getContainer()->get(DatabaseBindingSpecificationLoader::class);
        static::assertInstanceOf(DatabaseBindingSpecificationLoader::class, $loader);

        return $loader;
    }

    private function dataLoaderMap(): ContentSystemDataLoaderMap
    {
        $resolver = static::getContainer()->get(ContentSystemDataLoaderMapResolver::class);
        static::assertInstanceOf(ContentSystemDataLoaderMapResolver::class, $resolver);

        return $resolver->resolve();
    }

    private function configSerializers(): DataLoaderConfigSerializerProvider
    {
        $serializers = static::getContainer()->get(DataLoaderConfigSerializerProvider::class);
        static::assertInstanceOf(DataLoaderConfigSerializerProvider::class, $serializers);

        return $serializers;
    }
}
