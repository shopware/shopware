<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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

    #[TestDox('every registered binding specification carries only canonical loader bindings that decode through their loader serializer')]
    public function testEveryRegisteredSpecificationIsCanonicalAndDecodable(): void
    {
        $map = $this->dataLoaderMap();
        $serializers = $this->configSerializers();

        $all = $this->registry()->all();

        static::assertNotEmpty($all, 'The integration environment must register at least one binding specification.');

        // One aggregate assertion over a runtime-dynamic set (the registry cannot be a static data provider): each
        // non-canonical or non-decodable loader binding contributes a diagnostic line, and the empty-list assertion
        // fails with every offender named at once. A no-throw decode is the canonicality assertion.
        $problems = [];
        $checked = 0;

        foreach ($all as $qualifiedId => $specification) {
            foreach ($specification->resolves() as $referenceKey => $binding) {
                ++$checked;

                if (!isset($map->sourceToConfigSpecifications[$binding->loader])) {
                    $problems[] = \sprintf('%s.resolves[%s]: loader "%s" is not a registered data-loader source.', $qualifiedId, $referenceKey, $binding->loader);

                    continue;
                }

                try {
                    $serializers->decode($binding->loader, $binding->config);
                } catch (ContentSystemException $exception) {
                    $problems[] = \sprintf('%s.resolves[%s]: config does not decode through loader "%s": %s', $qualifiedId, $referenceKey, $binding->loader, $exception->getMessage());
                }
            }
        }

        static::assertGreaterThan(0, $checked, 'At least one canonical loader binding must be checked.');
        static::assertSame([], $problems);
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

    private function registry(): ContentSystemBindingSpecificationRegistry
    {
        return new ContentSystemBindingSpecificationRegistry(
            [$this->yamlLoader(), $this->databaseLoader()],
        );
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
