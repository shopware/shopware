<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductMappingCandidateProvider;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
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
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedTreeFactory;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\MappingCandidateProviderCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
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

    public function testBundleOwnedSourcesStayDistinctAcrossCatalogueWritesAndRendering(): void
    {
        $this->installRegistry([
            new BundleMappingProvider('AcmePlugin'),
            new BundleMappingProvider('OtherPlugin'),
        ]);
        $catalogue = $this->catalogue();
        foreach (['AcmePlugin', 'OtherPlugin'] as $bundleName) {
            $path = $bundleName . ':recommendations.title';
            static::assertSame('{{map:' . $path . '}}', $catalogue[$path]['canonicalToken']);
            $elements = [$this->element('mapped', new MappingSourceReference($bundleName, 'recommendations', path: 'title'))];
            $this->save($elements);
            static::assertSame($bundleName, $this->render($elements)[0]['text']);
        }
    }

    public function testRegistryCanBeDecoratedThroughItsPublicAliasWithoutASecondProvider(): void
    {
        $container = new ContainerBuilder();
        $container->register('core.product', ProductMappingCandidateProvider::class)
            ->setSynthetic(true)->setPublic(true)->addTag('content_system.mapping_candidate_provider');
        $container->register(ContentSystemMappingCandidateRegistry::class)
            ->setArguments([new TaggedIteratorArgument('content_system.mapping_candidate_provider')]);
        $container->setAlias(AbstractContentSystemMappingCandidateRegistry::class, ContentSystemMappingCandidateRegistry::class)->setPublic(true);
        $container->register('acme.mapping', ProductNameOverrideRegistry::class)
            ->setDecoratedService(AbstractContentSystemMappingCandidateRegistry::class)
            ->setArguments([new Reference('acme.mapping.inner')]);
        $container->addCompilerPass(new MappingCandidateProviderCompilerPass());
        $container->compile();
        $container->set('core.product', static::getContainer()->get(ProductMappingCandidateProvider::class));
        $registry = $container->get(AbstractContentSystemMappingCandidateRegistry::class);
        static::assertInstanceOf(ProductNameOverrideRegistry::class, $registry);
        $product = new SalesChannelProductEntity();
        $product->setTranslated(['name' => 'Shirt']);

        static::assertSame('Custom Shirt', $registry->resolveSource(
            'product',
            MappingSourceReference::root('product', 'name'),
            new MappingSourceResolutionContext(new StoredElement('element', 'Sw:Content:Text'), ['product' => $product], [], null),
        )->value);
        $registry->reset();
        static::assertSame(ProductMappingCandidateProvider::registeredCandidateKeys()['product'], array_keys($registry->forRootSource('product')));
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
        static::assertTrue($registry->resolveSource(
            'product',
            new MappingSourceReference('root', 'product', ['unlisted' => true], 'name'),
            new MappingSourceResolutionContext(new StoredElement('element', 'Sw:Content:Text'), [], [], null),
        )->isNoValue());
        $registry->reset();
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
        ]);
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
    private function save(array $elements): void
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('content_layout.repository')->create([[
            'id' => $id, 'name' => 'Mapping extension ' . $id, 'version' => '1.0.0',
            'rootSource' => 'product', 'layout' => $elements,
        ]], Context::createDefaultContext());
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
        $expanded = static::getContainer()->get(InlineMappingExpander::class)->expand($forest, $ambient, 'product', $cache, inlineMappings: new InlineMappingMetadata($tokens));
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

    public function forRootSource(string $rootSource): array
    {
        return $this->inner->forRootSource($rootSource);
    }

    public function resolveSource(string $rootSource, MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        $resolved = $this->inner->resolveSource($rootSource, $source, $context);
        if ($rootSource === 'product' && $source->isSameAs(MappingSourceReference::root('product', 'name')) && $resolved->hasValue && \is_string($resolved->value)) {
            $context->cacheContext?->addTags(['acme-product-name']);

            return MappingSourceResolution::resolved('Custom ' . $resolved->value);
        }

        return $resolved;
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

    public function supportsSource(MappingSourceReference $source): bool
    {
        return $source->type === $this->bundleName && $source->id === 'recommendations';
    }

    public function resolveSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        return MappingSourceResolution::resolved($this->bundleName);
    }
}
