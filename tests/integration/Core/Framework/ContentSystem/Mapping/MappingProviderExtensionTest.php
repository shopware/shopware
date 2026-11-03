<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductMappingCandidateProvider;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\AbstractContentSystemElementTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingExpander;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingMetadata;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\ContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentity;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueOrigin;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedTreeFactory;
use Shopware\Core\Framework\ContentSystem\Rendering\ResolvedLoaderValue;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestKernel;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class MappingProviderExtensionTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public static function tearDownAfterClass(): void
    {
        KernelLifecycleManager::ensureKernelShutdown();
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        KernelLifecycleManager::bootKernel();
    }

    #[DataProvider('bundleNameProvider')]
    public function testBundleOwnedSourcesStayDistinctAcrossCatalogueWritesAndRendering(string $bundleName): void
    {
        $this->installRegistry([
            new BundleMappingProvider('AcmePlugin'),
            new BundleMappingProvider('OtherPlugin'),
        ]);
        $catalogue = $this->catalogue();
        $path = $bundleName . ':recommendations.title';
        static::assertSame('{{map:' . $path . '}}', $catalogue[$path]['canonicalToken']);
        $elements = [$this->element('mapped', new MappingSourceReference($bundleName, 'recommendations', path: 'title'))];
        $this->save($elements);
        static::assertSame($bundleName, $this->render($elements)[0]['text']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bundleNameProvider(): iterable
    {
        yield 'AcmePlugin' => ['AcmePlugin'];
        yield 'OtherPlugin' => ['OtherPlugin'];
    }

    public function testReorderedPluginConfigurationSurvivesWritingReloadingAndRendering(): void
    {
        $catalogued = new MappingSourceReference('AcmePlugin', 'recommendations', [
            'country' => 'DE', 'options' => ['currency' => 'EUR', 'limit' => 5], 'priority' => ['featured', 'new'],
        ], 'title');
        $stored = new MappingSourceReference('AcmePlugin', 'recommendations', [
            'priority' => ['featured', 'new'], 'options' => ['limit' => 5, 'currency' => 'EUR'], 'country' => 'DE',
        ], 'title');
        $provider = $this->createMock(AbstractMappingCandidateProvider::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([
            new MappingCandidate($catalogued->displayName(), 'Recommendations', '', 'plugin', 'string', source: $catalogued),
        ]);
        $provider->method('resolveSource')->willReturn(MappingSourceResolution::resolved('Plugin title'));
        $this->installRegistry([$provider]);
        $elements = [$this->element('mapped', $stored)];

        static::assertSame('Plugin title', $this->render($elements)[0]['text']);
        $id = $this->save($elements);
        $repository = static::getContainer()->get('content_layout.repository');
        $layout = $repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);
        $codec = static::getContainer()->get(StoredElementCodec::class);
        $reloaded = array_map($codec->encode(...), $layout->getLayout());

        static::assertSame('Plugin title', $this->render($reloaded)[0]['text']);
        $repository->update([['id' => $id, 'layout' => $reloaded]], Context::createDefaultContext());
    }

    #[DataProvider('rejectedMappingOutcomes')]
    public function testRejectedMappingCannotEscapeThroughKeyedOutputOrChildDistribution(bool $offered): void
    {
        $source = new MappingSourceReference('AcmePlugin', 'message', path: 'text');
        $provider = $this->createMock(AbstractMappingCandidateProvider::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn($offered ? [
            new MappingCandidate($source->displayName(), 'Message', '', 'plugin', 'string', source: $source),
        ] : []);
        $provider->expects($offered ? $this->once() : $this->never())->method('resolveSource')->willReturn(MappingSourceResolution::resolved(false));
        $this->installRegistry([$provider]);
        $grandchild = StoredElementBuilder::create(TestElementTypeLoader::MAPPABLE_PRIMITIVE, 'grandchild')
            ->withConsumer('text', ContextType::Single)
            ->build();
        $child = StoredElementBuilder::create(TestElementTypeLoader::MAPPABLE_PRIMITIVE, 'child')
            ->withProperty('text', 'Authored fallback')
            ->withConsumer('text', ContextType::Single, scope: ConsumerScope::Root, source: $source)
            ->withConsumer('items', ContextType::Single)
            ->withProvider('text', BroadcastDistributionConfig::simple())
            ->withSlot('content', [$grandchild])
            ->build();
        $parent = StoredElementBuilder::create('Sw:Grid:Container', 'parent')
            ->withProperty('items', ['Authored fallback' => 'Selected item'])
            ->withProvider('items', KeyedDistributionConfig::fromArray(['keyProperty' => 'text']))
            ->withSlot('content', [$child])
            ->build();
        $cache = new RenderingCacheContext();

        $deliveries = static::getContainer()->get(ContextDeliveryResolver::class)->resolve([$parent], [], [], $cache, 'product');
        $result = static::getContainer()->get(RenderedTreeFactory::class)->create([$parent], $deliveries, [], RenderingMode::FULL);

        $renderedChild = $result->tree[0]->slots['content'][0];
        static::assertSame(['items' => 'Selected item'], $renderedChild->properties);
        static::assertSame([], $renderedChild->slots['content'][0]->properties);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function rejectedMappingOutcomes(): iterable
    {
        yield 'unavailable candidate' => [false];
        yield 'incompatible provider value' => [true];
    }

    #[DataProvider('mappingFallbackOutcomes')]
    public function testParentAndChildUseAuthoredFallbackWhenTheMappingHasNoValueAndTheLoaderReturnsNull(MappingSourceResolution $resolution): void
    {
        $source = new MappingSourceReference('AcmePlugin', 'message', path: 'text');
        $provider = $this->createMock(AbstractMappingCandidateProvider::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([
            new MappingCandidate($source->displayName(), 'Message', '', 'plugin', 'string', source: $source),
        ]);
        $provider->expects($this->once())->method('resolveSource')->willReturn($resolution);
        $this->installRegistry([$provider]);
        $child = StoredElementBuilder::create(TestElementTypeLoader::MAPPABLE_PRIMITIVE, 'child')
            ->withConsumer('text', ContextType::Single)
            ->build();
        $parent = StoredElementBuilder::create(TestElementTypeLoader::MAPPABLE_PRIMITIVE, 'parent')
            ->withProperty('text', 'Authored fallback')
            ->withDataRequirement('text', 'entity', new EntityLoaderConfig(entity: 'product', property: 'productId', associations: []))
            ->withConsumer('text', ContextType::Single, scope: ConsumerScope::Root, source: $source)
            ->withProvider('text', BroadcastDistributionConfig::simple())
            ->withSlot('content', [$child])
            ->build();
        $loaded = new ResolvedLoaderValue(null, new LoaderValueIdentity(
            'entity',
            'config-hash',
            'inputs-hash',
            (new ValueFingerprinter())->fingerprint(null),
        ));

        $deliveries = static::getContainer()->get(ContextDeliveryResolver::class)->resolve(
            [$parent],
            ['parent' => ['text' => null]],
            [],
            new RenderingCacheContext(),
            'product',
        );
        $result = static::getContainer()->get(RenderedTreeFactory::class)->create(
            [$parent],
            $deliveries,
            ['parent' => ['text' => $loaded]],
            RenderingMode::FULL,
        );

        static::assertSame(['text' => 'Authored fallback'], $result->tree[0]->properties);
        static::assertSame($result->tree[0]->properties, $result->tree[0]->slots['content'][0]->properties);
        static::assertSame(ValueOrigin::DeclaredAuthored, $result->provenance['parent']['text']->origin);
        static::assertSame(ValueOrigin::DeliveredContext, $result->provenance['child']['text']->origin);
    }

    /**
     * @return iterable<string, array{MappingSourceResolution}>
     */
    public static function mappingFallbackOutcomes(): iterable
    {
        yield 'missing source' => [MappingSourceResolution::noValue()];
        yield 'empty source' => [MappingSourceResolution::resolved('')];
    }

    #[DataProvider('mappedMediaProviderSources')]
    public function testMappedMediaBacksARequiredChildDuringDiagnosisAndSaving(MappingSourceReference $source): void
    {
        $previousKernelClass = KernelFactory::$kernelClass;
        KernelFactory::$kernelClass = MappingProviderBackingTestKernel::class;
        try {
            KernelLifecycleManager::bootKernel(cacheId: 'mapping-provider-backing');
        } finally {
            KernelFactory::$kernelClass = $previousKernelClass;
        }
        $pluginSource = new MappingSourceReference('AcmePlugin', 'image', path: 'media');
        $provider = $this->createMock(AbstractMappingCandidateProvider::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([
            new MappingCandidate($pluginSource->displayName(), 'Image', '', 'media', MediaEntity::class, source: $pluginSource),
        ]);
        $provider->expects($this->never())->method('resolveSource');
        $this->installRegistry([$provider]);
        $elements = [[
            'id' => 'mapped-parent', 'component' => 'Sw:Test:MappedMediaContainer',
            'acceptsContext' => ['picture' => ['type' => 'single', 'required' => false, 'scope' => 'root', 'source' => $source->jsonSerialize()]],
            'providesContext' => ['picture' => ['type' => 'single', 'distribution' => 'broadcast', 'consumerAlias' => 'image']],
            'slots' => ['content' => [[
                'id' => 'required-child', 'component' => 'Sw:Test:RequiredMedia',
                'acceptsContext' => ['image' => ['type' => 'single', 'required' => true]],
            ]]],
        ]];

        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/layout/diagnose', ['layout' => $elements, 'rootSource' => 'product']);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($body['diagnostics']['resolvable'], json_encode($body['diagnostics']['violations'], \JSON_THROW_ON_ERROR));
        $childResolution = $body['resolutions']['required-child'][0];
        static::assertSame('parent', $childResolution['resolved']['origin']);
        static::assertSame('mapped-parent', $childResolution['resolved']['providerElementId']);
        $parentResolution = $body['resolutions']['mapped-parent'][0];
        static::assertSame('root', $parentResolution['resolved']['origin']);
        static::assertSame($source->displayName(), $parentResolution['resolved']['contextKey']);
        static::assertNull($parentResolution['resolved']['providerElementId']);
        $this->save($elements);
    }

    #[DataProvider('mappedReferencePickerCases')]
    public function testRequiredMappedReferenceReportsMappingRatherThanAnEmptyPicker(MappingSourceReference $source, bool $withPicker): void
    {
        $previousKernelClass = KernelFactory::$kernelClass;
        KernelFactory::$kernelClass = MappingProviderBackingTestKernel::class;
        try {
            KernelLifecycleManager::bootKernel(cacheId: 'mapping-provider-backing');
        } finally {
            KernelFactory::$kernelClass = $previousKernelClass;
        }
        $pluginSource = new MappingSourceReference('AcmePlugin', 'image', path: 'media');
        $provider = $this->createMock(AbstractMappingCandidateProvider::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('provide')->willReturn([
            new MappingCandidate($pluginSource->displayName(), 'Image', '', 'media', MediaEntity::class, source: $pluginSource),
        ]);
        $provider->expects($this->never())->method('resolveSource');
        $this->installRegistry([$provider]);
        $element = [
            'id' => 'mapped', 'component' => 'Sw:Test:RequiredMedia',
            'acceptsContext' => ['image' => ['type' => 'single', 'required' => false, 'scope' => 'root', 'source' => $source->jsonSerialize()]],
        ];
        if ($withPicker) {
            $element['dataRequirements'] = ['image' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']]];
        }

        $this->getBrowser()->jsonRequest('POST', '/api/_action/content-system/layout/diagnose', ['layout' => [$element], 'rootSource' => 'product']);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($body['diagnostics']['resolvable'], json_encode($body['diagnostics']['violations'], \JSON_THROW_ON_ERROR));
        $resolution = $body['resolutions']['mapped'][0];
        static::assertSame('root', $resolution['resolved']['origin']);
        static::assertSame($source->displayName(), $resolution['resolved']['contextKey']);
        static::assertNull($resolution['resolved']['providerElementId']);
        $this->save([$element]);
    }

    /**
     * @return iterable<string, array{MappingSourceReference, bool}>
     */
    public static function mappedReferencePickerCases(): iterable
    {
        $root = MappingSourceReference::root('product', 'cover');
        $plugin = new MappingSourceReference('AcmePlugin', 'image', path: 'media');
        yield 'core mapping without picker' => [$root, false];
        yield 'core mapping with empty picker' => [$root, true];
        yield 'plugin mapping without picker' => [$plugin, false];
        yield 'plugin mapping with empty picker' => [$plugin, true];
    }

    /**
     * @return iterable<string, array{MappingSourceReference}>
     */
    public static function mappedMediaProviderSources(): iterable
    {
        yield 'reshaped core media with a different destination key' => [MappingSourceReference::root('product', 'cover')];
        yield 'typed non-root plugin media' => [new MappingSourceReference('AcmePlugin', 'image', path: 'media')];
    }

    public function testRegistryCanBeDecoratedThroughItsPublicAliasWithoutASecondProvider(): void
    {
        $previousKernelClass = KernelFactory::$kernelClass;
        KernelFactory::$kernelClass = MappingRegistryDecorationTestKernel::class;
        try {
            KernelLifecycleManager::bootKernel(cacheId: 'mapping-registry-decoration');
        } finally {
            KernelFactory::$kernelClass = $previousKernelClass;
        }

        $registry = static::getContainer()->get(AbstractContentSystemMappingCandidateRegistry::class);
        static::assertInstanceOf(ProductNameOverrideRegistry::class, $registry);
        static::assertInstanceOf(ContentSystemMappingCandidateRegistry::class, $registry->getDecorated());
        static::assertSame('Custom product name', $this->catalogue()['product.name']['label']);

        $source = MappingSourceReference::root('product', 'name');
        $candidate = static::getContainer()->get(StoredMappingInspector::class)->admitProperty(TestElementTypeLoader::MAPPABLE_PRIMITIVE, 'text', $source, 'product');
        static::assertInstanceOf(MappingCandidate::class, $candidate);
        static::assertSame('Custom product name', $candidate->label);

        $elements = [
            $this->element('mapped', $source),
            ['id' => 'inline', 'component' => 'Sw:Content:Text', 'properties' => [
                'text' => '{{map:product.name}} / {{map:product.description}}',
            ]],
        ];
        $this->save($elements);
        $cache = new RenderingCacheContext();
        $rendered = $this->render($elements, $cache);
        static::assertSame('Custom Shirt', $rendered[0]['text']);
        static::assertSame('Custom Shirt / Description', $rendered[1]['text']);
        static::assertContains('acme-product-name', $cache->getTags());

        $beforeReset = $registry->forRootSource('product')['product.description'];
        static::assertSame($beforeReset, $registry->forRootSource('product')['product.description']);
        $registry->reset();
        static::assertNotSame($beforeReset, $registry->forRootSource('product')['product.description']);
        $candidateAfterReset = $registry->forRootSource('product')['product.name'];
        static::assertInstanceOf(MappingCandidate::class, $candidateAfterReset);
        static::assertSame('Custom product name', $candidateAfterReset->label);
    }

    public function testProviderClassesCanUseContainerParameters(): void
    {
        $previousKernelClass = KernelFactory::$kernelClass;
        KernelFactory::$kernelClass = ParameterizedMappingProviderTestKernel::class;
        try {
            KernelLifecycleManager::bootKernel(cacheId: 'parameterized-mapping-provider');
        } finally {
            KernelFactory::$kernelClass = $previousKernelClass;
        }

        $source = new MappingSourceReference('ParameterizedPlugin', 'recommendations', path: 'title');
        static::assertSame($source->jsonSerialize(), $this->catalogue()[$source->displayName()]['source']);
        $elements = [
            $this->element('mapped', $source),
            ['id' => 'inline', 'component' => 'Sw:Content:Text', 'properties' => [
                'text' => '{{map:ParameterizedPlugin:recommendations.title}}',
            ]],
        ];
        $this->save($elements);
        $rendered = $this->render($elements);

        static::assertSame('ParameterizedPlugin', $rendered[0]['text']);
        static::assertSame('ParameterizedPlugin', $rendered[1]['text']);
    }

    public function testImmediateOverridesCannotSkipSourceAdmission(): void
    {
        $inner = new ContentSystemMappingCandidateRegistry([static::getContainer()->get(ProductMappingCandidateProvider::class)], new NullLogger());
        $registry = new ImmediateProductNameOverrideRegistry($inner);
        static::getContainer()->set(ContentSystemMappingCandidateRegistry::class, $registry);
        $elements = [
            $this->element('whole-field', MappingSourceReference::root('product', 'name')),
            ['id' => 'inline', 'component' => 'Sw:Content:Text', 'properties' => ['text' => '{{map:product.name}}']],
        ];
        $this->save($elements);

        $rendered = $this->render($elements);

        static::assertSame('Immediate override', $rendered[0]['text']);
        static::assertSame('Immediate override', $rendered[1]['text']);
        static::assertSame(2, $registry->resolutionCalls);

        $context = new MappingSourceResolutionContext(new StoredElement('element', 'Sw:Content:Text'), [], [], new RenderingCacheContext());
        static::assertTrue($registry->resolveSource('product', MappingSourceReference::root('product', 'internalComment'), $context)->isNoValue());
        static::assertTrue($registry->resolveSource('product', new MappingSourceReference('root', 'product', ['unlisted' => true], 'name'), $context)->isNoValue());

        $unavailable = $this->render([
            $this->element('whole-field', MappingSourceReference::root('product', 'internalComment')),
            ['id' => 'inline', 'component' => 'Sw:Content:Text', 'properties' => ['text' => 'Before {{map:product.internalComment}} after']],
        ]);

        static::assertArrayNotHasKey('text', $unavailable[0]);
        static::assertSame('Before  after', $unavailable[1]['text']);
        static::assertSame(2, $registry->resolutionCalls);
    }

    public function testExplicitOverridePreservesTheCoreSourceIdentityAndUnrelatedMappings(): void
    {
        $registry = $this->installRegistry([], override: true);
        $source = MappingSourceReference::root('product', 'name');
        static::assertSame($source->jsonSerialize(), $this->catalogue()['product.name']['source']);
        $elements = [
            $this->element('whole-field', $source),
            ['id' => 'inline', 'component' => 'Sw:Content:Text', 'properties' => [
                'text' => '{{map:product.name}} / {{map:product.description}}',
            ]],
        ];
        $this->save($elements);

        $cache = new RenderingCacheContext();
        $rendered = $this->render($elements, $cache);
        static::assertSame('Custom Shirt', $rendered[0]['text']);
        static::assertSame('Custom Shirt / Description', $rendered[1]['text']);
        static::assertContains('acme-product-name', $cache->getTags());
        $product = new SalesChannelProductEntity();
        $product->setTranslated(['name' => 'Shirt']);
        static::assertTrue($registry->resolveSource(
            'product',
            new MappingSourceReference('root', 'product', ['unlisted' => true], 'name'),
            new MappingSourceResolutionContext(new StoredElement('element', 'Sw:Content:Text'), ['product' => $product], [], new RenderingCacheContext()),
        )->isNoValue());
        $beforeReset = $registry->forRootSource('product')['product.description'];
        static::assertSame($beforeReset, $registry->forRootSource('product')['product.description']);
        $registry->reset();
        static::assertNotSame($beforeReset, $registry->forRootSource('product')['product.description']);
        static::assertArrayHasKey('product.name', $registry->forRootSource('product'));
    }

    public function testAccidentalCollisionIsUnavailableAcrossCatalogueWritesAndRendering(): void
    {
        $collision = $this->createMock(AbstractMappingCandidateProvider::class);
        $collision->method('supports')->willReturn(true);
        $collision->method('provide')->willReturn([new MappingCandidate('product.name', 'Duplicate', '', 'basic', 'string')]);
        $collision->expects($this->never())->method('resolveRootSource');
        $this->installRegistry([$collision]);
        $catalogue = $this->catalogue();
        static::assertArrayNotHasKey('product.name', $catalogue);
        static::assertArrayHasKey('product.description', $catalogue);
        $elements = [
            $this->element('whole-field', MappingSourceReference::root('product', 'name')),
            ['id' => 'inline', 'component' => 'Sw:Content:Text', 'properties' => [
                'text' => 'Hi {{map:product.name}} / {{map:product.description}}',
            ]],
        ];

        // A layout stored before a plugin introduced the conflict still follows the unavailable-mapping rules.
        $rendered = $this->render($elements);
        static::assertArrayNotHasKey('text', $rendered[0]);
        static::assertSame('Hi  / Description', $rendered[1]['text']);

        try {
            $this->save([$elements[0]]);
            static::fail('The write gate must reject an ambiguous mapping.');
        } catch (WriteException $exception) {
            $codes = [];
            foreach ($exception->getExceptions() as $inner) {
                if ($inner instanceof WriteConstraintViolationException) {
                    foreach ($inner->getViolations() as $violation) {
                        $codes[] = $violation->getCode();
                    }
                }
            }
            static::assertContains(ContentSystemException::UNKNOWN_MAPPING_PATH, $codes);
        }
    }

    /**
     * @param list<AbstractMappingCandidateProvider> $additionalProviders
     */
    private function installRegistry(array $additionalProviders, bool $override = false): AbstractContentSystemMappingCandidateRegistry
    {
        $registry = new ContentSystemMappingCandidateRegistry([
            static::getContainer()->get(ProductMappingCandidateProvider::class),
            ...$additionalProviders,
        ], new NullLogger());
        $result = $override ? new ProductNameOverrideRegistry($registry) : $registry;
        static::getContainer()->set(ContentSystemMappingCandidateRegistry::class, $result);

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalogue(): array
    {
        $this->getBrowser()->request('GET', '/api/_info/content-system-mapping-candidates.json');
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return array_column(json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['mappingCandidates']['product'], null, 'path');
    }

    /**
     * @return array<string, mixed>
     */
    private function element(string $id, MappingSourceReference $source): array
    {
        return ['id' => $id, 'component' => TestElementTypeLoader::MAPPABLE_PRIMITIVE, 'properties' => ['text' => 'Authored'], 'acceptsContext' => [
            'text' => ['type' => 'single', 'required' => false, 'scope' => 'root', 'source' => $source->jsonSerialize()],
        ]];
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    private function save(array $elements): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('content_layout.repository')->create([[
            'id' => $id, 'name' => 'Mapping extension ' . $id, 'version' => '1.0.0',
            'rootSource' => 'product', 'layout' => $elements,
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @param list<array<string, mixed>> $elements
     *
     * @return list<array<string, mixed>>
     */
    private function render(array $elements, ?RenderingCacheContext $cache = null): array
    {
        $forest = array_map(static fn (array $element): StoredElement => static::getContainer()->get(StoredElementCodec::class)->decode($element), $elements);
        $product = new SalesChannelProductEntity();
        $product->setTranslated(['name' => 'Shirt', 'description' => 'Description']);
        $ambient = ['product' => $product];
        $cache ??= new RenderingCacheContext();
        $deliveries = static::getContainer()->get(ContextDeliveryResolver::class)->resolve($forest, [], $ambient, $cache, 'product');
        $tokens = [];
        foreach ($forest as $element) {
            $tokens[$element->id]['text'] = (new InlineMappingTokenParser())->parse($element->property('text')?->asString() ?? '');
        }
        $expanded = static::getContainer()->get(InlineMappingExpander::class)->expand($forest, $ambient, 'product', $cache, inlineMappings: new InlineMappingMetadata($tokens), loaderValues: []);
        $result = static::getContainer()->get(RenderedTreeFactory::class)->create($expanded, $deliveries, [], RenderingMode::FULL);

        return array_map(static fn ($element): array => $element->properties, $result->tree);
    }
}

/**
 * A plugin's supported override decorates the public registry and keeps exact admission and cache handling.
 *
 * @internal
 */
class ProductNameOverrideRegistry extends AbstractContentSystemMappingCandidateRegistry
{
    public function __construct(private readonly AbstractContentSystemMappingCandidateRegistry $inner)
    {
    }

    public function getDecorated(): AbstractContentSystemMappingCandidateRegistry
    {
        return $this->inner;
    }

    /**
     * @return array<string, MappingCandidate>
     */
    public function forRootSource(string $rootSource): array
    {
        $candidates = $this->inner->forRootSource($rootSource);
        if ($rootSource === 'product' && isset($candidates['product.name'])) {
            $candidates['product.name'] = new MappingCandidate('product.name', 'Custom product name', '', 'basic', 'string');
        }

        return $candidates;
    }

    protected function resolveAdmittedSource(string $rootSource, MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        $resolved = $this->inner->resolveSource($rootSource, $source, $context);
        if ($rootSource === 'product' && $source->isSameAs(MappingSourceReference::root('product', 'name')) && $resolved->hasValue && \is_string($resolved->value)) {
            $context->cacheContext->addTags(['acme-product-name']);

            return MappingSourceResolution::resolved('Custom ' . $resolved->value);
        }

        return $resolved;
    }
}

/**
 * @internal
 */
class ImmediateProductNameOverrideRegistry extends ProductNameOverrideRegistry
{
    public int $resolutionCalls = 0;

    protected function resolveAdmittedSource(string $rootSource, MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        ++$this->resolutionCalls;

        return MappingSourceResolution::resolved('Immediate override');
    }
}

/**
 * @internal
 */
class BundleMappingProvider extends AbstractMappingCandidateProvider
{
    public function __construct(private readonly string $bundleName)
    {
    }

    public static function registeredCandidateKeys(): array
    {
        return [];
    }

    public function supports(string $rootSource): bool
    {
        return $rootSource === 'product';
    }

    public function provide(string $rootSource): array
    {
        $source = new MappingSourceReference($this->bundleName, 'recommendations', path: 'title');

        return [new MappingCandidate($source->displayName(), 'Recommendations', '', 'plugin', 'string', source: $source)];
    }

    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        return MappingSourceResolution::resolved($this->bundleName);
    }
}

/**
 * Boots normal Shopware definitions with only the documented plugin decorator added.
 *
 * @internal
 */
class MappingRegistryDecorationTestKernel extends TestKernel
{
    public function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);
        $container->register(ProductNameOverrideRegistry::class)
            ->setDecoratedService(AbstractContentSystemMappingCandidateRegistry::class)
            ->setArguments([new Reference(ProductNameOverrideRegistry::class . '.inner')]);
    }
}

/**
 * @internal
 */
class ParameterizedMappingProviderTestKernel extends TestKernel
{
    public function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);
        $container->setParameter('test.mapping_provider.class', BundleMappingProvider::class);
        $container->register('test.parameterized_mapping_provider', '%test.mapping_provider.class%')
            ->setArguments(['ParameterizedPlugin'])
            ->addTag('content_system.mapping_candidate_provider');
    }
}

/**
 * @internal
 */
class MappingProviderBackingTestKernel extends TestKernel
{
    public function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);
        $container->register(MappingProviderBackingTypeLoader::class)->addTag('content_system.type_loader');
    }
}

/**
 * @internal
 */
class MappingProviderBackingTypeLoader extends AbstractContentSystemElementTypeLoader
{
    public function load(): array
    {
        $parentProperties = ContentSystemElementTypeSpecificationBuilder::create('Sw:Test:MappedMediaContainer')
            ->reference('picture', MediaEntity::class, mappable: true)
            ->build()->properties();

        return [
            new ContentSystemElementTypeSpecification(
                'Sw:Test:MappedMediaContainer',
                'Mapped media container',
                '',
                null,
                null,
                new CopilotSpecification('', []),
                $parentProperties,
                [new SlotSpecification('content', null, [], '')],
                'test',
            ),
            ContentSystemElementTypeSpecificationBuilder::create('Sw:Test:RequiredMedia')
                ->reference('image', MediaEntity::class, required: true, mappable: true)
                ->build(),
        ];
    }
}
