<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubArrayLoaderConfig;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(AttributionReconciler::class)]
class AttributionReconcilerTest extends TestCase
{
    private ConfigCanonicalizer $canonicalizer;

    protected function setUp(): void
    {
        // A real ConfigCanonicalizer: several of these tests exercise canonicalization (key/list order) directly,
        // so it must run for real rather than be stubbed out.
        $this->canonicalizer = new ConfigCanonicalizer();
    }

    #[TestDox('drops a key whose attribution no longer matches the specification wiring, leaving the wiring itself untouched')]
    public function testDropsAttributionOnWiringMismatch(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['entity' => 'media', 'limit' => 5]),
        ]);

        $config = new StubArrayLoaderConfig(['entity' => 'media', 'limit' => 10]);
        $element = ContentElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(ContentElement::class, $reconciled);
        static::assertSame([], $reconciled->getAttributedSpecifications());
        static::assertSame($element->getDataRequirements(), $reconciled->getDataRequirements());
    }

    #[TestDox('keeps attribution when wiring matches after canonicalization, unaffected by key and list order differences')]
    public function testKeepsAttributionWhenWiringMatchesModuloCanonicalOrder(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', [
                'associations' => ['media', 'manufacturer'],
                'filters' => ['status' => 'active', 'limit' => 10],
            ]),
        ]);

        // Same config as the specification's, but with top-level keys and the "associations" list reordered.
        $config = new StubArrayLoaderConfig([
            'filters' => ['limit' => 10, 'status' => 'active'],
            'associations' => ['manufacturer', 'media'],
        ]);
        $element = ContentElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(ContentElement::class, $reconciled);
        static::assertSame(['product' => 'spec-1'], $reconciled->getAttributedSpecifications());
    }

    #[TestDox('drops attribution when the specification no longer resolves from the registry')]
    public function testDropsAttributionWhenSpecificationNotFound(): void
    {
        $config = new StubArrayLoaderConfig(['entity' => 'media']);
        $element = ContentElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'gone-spec')
            ->build();

        $result = $this->reconciler([], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(ContentElement::class, $reconciled);
        static::assertSame([], $reconciled->getAttributedSpecifications());
    }

    #[TestDox('drops attribution when the specification no longer resolves the attributed key')]
    public function testDropsAttributionWhenSpecificationNoLongerResolvesKey(): void
    {
        $specification = $this->specification('spec-1', []);

        $config = new StubArrayLoaderConfig(['entity' => 'media']);
        $element = ContentElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(ContentElement::class, $reconciled);
        static::assertSame([], $reconciled->getAttributedSpecifications());
    }

    #[TestDox('drops attribution when the stored config no longer decodes, without throwing out of reconcile()')]
    public function testDropsAttributionWhenStoredConfigUndecodableWithoutThrowing(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
        ]);

        // The registered "entity" serializer decodes normally, except for a config that carries the "corrupt"
        // marker — standing in for a persisted config whose shape no longer matches what decode() expects. It
        // throws the real client-defect factory a production serializer would use for that.
        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $serializer->method('decode')->willReturnCallback(
            static function (array $data): AbstractContentDataLoaderConfig {
                if (($data['corrupt'] ?? false) === true) {
                    throw ContentSystemException::invalidFieldValueType('product.config', 'array', 'string');
                }

                return new StubArrayLoaderConfig($data);
            }
        );
        $serializer->method('encode')->willReturnCallback(
            static fn (AbstractContentDataLoaderConfig $config): array => $config->jsonSerialize()
        );
        $provider = new DataLoaderConfigSerializerProvider(new ServiceLocator(['entity' => static fn () => $serializer]));

        $node = [
            'component' => 'card',
            'attributedSpecifications' => ['product' => 'spec-1'],
            'dataRequirements' => ['product' => ['source' => 'entity', 'config' => ['corrupt' => true]]],
        ];

        $result = $this->reconciler(['spec-1' => $specification], $provider)->reconcile([$node]);

        $reconciled = $result[0];
        static::assertIsArray($reconciled);
        static::assertArrayNotHasKey('attributedSpecifications', $reconciled);
        static::assertSame($node['dataRequirements'], $reconciled['dataRequirements']);
    }

    #[TestDox('drops attribution when the element\'s stored domain-loader config fails the real domain serializer\'s decode(), reclassified via the shared config-serializer decode chokepoint, without throwing out of reconcile()')]
    public function testDropsAttributionWhenRealDomainLoaderConfigFailsToDecodeWithoutThrowing(): void
    {
        $specification = $this->specification('spec-1', [
            'navigation' => new LoaderBinding('navigation', ['rootId' => 'main-navigation', 'depth' => 3]),
        ]);

        // The real NavigationLoaderConfigSerializer, not a stub: "depth" must be a positive int, so this
        // element's stored config fails its decode() with a CategoryException -- a domain exception, not a
        // ContentSystemException. DataLoaderConfigSerializerProvider reclassifies it to
        // ContentSystemException::invalidLoaderConfig() at the shared decode chokepoint; the reconciler must
        // catch that reclassified exception and drop the attribution, not throw.
        $serializer = new NavigationLoaderConfigSerializer();
        $provider = new DataLoaderConfigSerializerProvider(new ServiceLocator(['navigation' => static fn () => $serializer]));

        $node = [
            'component' => 'card',
            'attributedSpecifications' => ['navigation' => 'spec-1'],
            'dataRequirements' => ['navigation' => ['source' => 'navigation', 'config' => ['depth' => 'not-an-int']]],
        ];

        $result = $this->reconciler(['spec-1' => $specification], $provider)->reconcile([$node]);

        $reconciled = $result[0];
        static::assertIsArray($reconciled);
        static::assertArrayNotHasKey('attributedSpecifications', $reconciled);
        static::assertSame($node['dataRequirements'], $reconciled['dataRequirements']);
    }

    #[TestDox('drops attribution when the element wiring source is no longer registered, without throwing out of reconcile()')]
    public function testDropsAttributionWhenElementSourceUnregisteredWithoutThrowing(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
        ]);

        $config = new StubArrayLoaderConfig(['limit' => 5]);
        $element = ContentElementBuilder::create('card', 'elem-1')
            // Only "entity" (the specification's own binding source) is registered on the provider below; this
            // element wiring points at a source that is not — an uninstalled loader whose config serializer was
            // de-registered. The real, unstubbed DataLoaderConfigSerializerProvider::encode() throws
            // configSerializerNotRegistered for it.
            ->withDataRequirement('product', 'removed_plugin_source', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(ContentElement::class, $reconciled);
        static::assertSame([], $reconciled->getAttributedSpecifications());
    }

    #[TestDox('rethrows a non-client-defect ContentSystemException instead of dropping the key')]
    public function testRethrowsNonClientDefectException(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
        ]);

        $internalFault = ContentSystemException::invalidFieldType(AbstractContentDataLoaderConfig::class, StubArrayLoaderConfig::class);
        static::assertFalse(ContentSystemException::isClientDefect($internalFault));

        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $serializer->method('decode')->willThrowException($internalFault);
        $provider = new DataLoaderConfigSerializerProvider(new ServiceLocator(['entity' => static fn () => $serializer]));

        $config = new StubArrayLoaderConfig(['limit' => 5]);
        $element = ContentElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $reconciler = $this->reconciler(['spec-1' => $specification], $provider);

        $this->expectExceptionObject($internalFault);

        $reconciler->reconcile([$element]);
    }

    #[TestDox('keeps attribution and passes properties through unchanged, since stored property values are never compared')]
    public function testKeepsAttributionRegardlessOfPropertyValueChanges(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
        ]);

        $config = new StubArrayLoaderConfig(['limit' => 5]);
        $element = ContentElementBuilder::create('card', 'elem-1')
            ->withProperty('mediaId', 'edited-after-binding')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(ContentElement::class, $reconciled);
        static::assertSame(['product' => 'spec-1'], $reconciled->getAttributedSpecifications());
        static::assertSame('edited-after-binding', $reconciled->getProperty('mediaId'));
    }

    #[TestDox('drops only the key whose wiring diverged, keeping every other key\'s attribution independently')]
    public function testDropsOnlyDivergedKeyAmongMultipleAttributedKeys(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
            'media' => new LoaderBinding('entity', ['limit' => 1]),
        ]);

        $matchingConfig = new StubArrayLoaderConfig(['limit' => 5]);
        $divergedConfig = new StubArrayLoaderConfig(['limit' => 2]); // was 1, edited away from the specification

        $element = ContentElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $matchingConfig)
            ->withDataRequirement('media', 'entity', $divergedConfig)
            ->withAttributedSpecification('product', 'spec-1')
            ->withAttributedSpecification('media', 'spec-1')
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(ContentElement::class, $reconciled);
        static::assertSame(['product' => 'spec-1'], $reconciled->getAttributedSpecifications());
    }

    #[TestDox('omits attributedSpecifications on a raw node when every key drops, instead of writing an empty array')]
    public function testRawPayloadOmitsAttributedSpecificationsKeyWhenAllDropped(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
        ]);

        $node = [
            'component' => 'card',
            'attributedSpecifications' => ['product' => 'spec-1'],
            'dataRequirements' => ['product' => ['source' => 'entity', 'config' => ['limit' => 999]]],
        ];

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$node]);

        $reconciled = $result[0];
        static::assertIsArray($reconciled);
        static::assertArrayNotHasKey('attributedSpecifications', $reconciled);
        static::assertSame($node['dataRequirements'], $reconciled['dataRequirements']);
    }

    #[TestDox('writes only the surviving keys back onto a raw node\'s attributedSpecifications map')]
    public function testRawPayloadKeepsOnlySurvivingKeysInAttributionMap(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
            'media' => new LoaderBinding('entity', ['limit' => 1]),
        ]);

        $node = [
            'component' => 'card',
            'attributedSpecifications' => ['product' => 'spec-1', 'media' => 'spec-1'],
            'dataRequirements' => [
                'product' => ['source' => 'entity', 'config' => ['limit' => 5]],
                'media' => ['source' => 'entity', 'config' => ['limit' => 999]],
            ],
        ];

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$node]);

        $reconciled = $result[0];
        static::assertIsArray($reconciled);
        static::assertSame(['product' => 'spec-1'], $reconciled['attributedSpecifications']);
    }

    #[TestDox('drops a raw attribution entry whose key is non-string, keeping every other key\'s attribution independently')]
    public function testRawPayloadDropsNonStringKeyAttribution(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
        ]);

        $node = [
            'component' => 'card',
            // A "0" array literal key casts to the integer 0 in PHP — the same shape json_decode(..., true)
            // produces for a raw payload's numeric-looking attributedSpecifications key.
            'attributedSpecifications' => ['0' => 'spec-1', 'product' => 'spec-1'],
            'dataRequirements' => [
                0 => ['source' => 'entity', 'config' => ['limit' => 5]],
                'product' => ['source' => 'entity', 'config' => ['limit' => 5]],
            ],
        ];

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$node]);

        $reconciled = $result[0];
        static::assertIsArray($reconciled);
        static::assertSame(['product' => 'spec-1'], $reconciled['attributedSpecifications']);
        static::assertSame($node['dataRequirements'], $reconciled['dataRequirements']);
    }

    #[TestDox('drops a raw attribution entry whose specification id is non-string, keeping every other key\'s attribution independently')]
    public function testRawPayloadDropsNonStringSpecificationIdAttribution(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
            'media' => new LoaderBinding('entity', ['limit' => 1]),
        ]);

        $node = [
            'component' => 'card',
            'attributedSpecifications' => ['product' => 123, 'media' => 'spec-1'],
            'dataRequirements' => [
                'product' => ['source' => 'entity', 'config' => ['limit' => 5]],
                'media' => ['source' => 'entity', 'config' => ['limit' => 1]],
            ],
        ];

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$node]);

        $reconciled = $result[0];
        static::assertIsArray($reconciled);
        static::assertSame(['media' => 'spec-1'], $reconciled['attributedSpecifications']);
    }

    #[TestDox('drops a raw attribution entry whose requirement "source" is non-string, keeping every other key\'s attribution independently')]
    public function testRawPayloadDropsNonStringRequirementSourceAttribution(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
            'media' => new LoaderBinding('entity', ['limit' => 1]),
        ]);

        $node = [
            'component' => 'card',
            'attributedSpecifications' => ['product' => 'spec-1', 'media' => 'spec-1'],
            'dataRequirements' => [
                'product' => ['source' => 123, 'config' => ['limit' => 5]],
                'media' => ['source' => 'entity', 'config' => ['limit' => 1]],
            ],
        ];

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$node]);

        $reconciled = $result[0];
        static::assertIsArray($reconciled);
        static::assertSame(['media' => 'spec-1'], $reconciled['attributedSpecifications']);
    }

    #[TestDox('drops a raw attribution entry whose requirement entry is absent, keeping every other key\'s attribution independently')]
    public function testRawPayloadDropsAttributionWithAbsentRequirementEntry(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
            'media' => new LoaderBinding('entity', ['limit' => 1]),
        ]);

        $node = [
            'component' => 'card',
            'attributedSpecifications' => ['product' => 'spec-1', 'media' => 'spec-1'],
            // "product" has no matching entry in dataRequirements at all.
            'dataRequirements' => [
                'media' => ['source' => 'entity', 'config' => ['limit' => 1]],
            ],
        ];

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$node]);

        $reconciled = $result[0];
        static::assertIsArray($reconciled);
        static::assertSame(['media' => 'spec-1'], $reconciled['attributedSpecifications']);
    }

    #[TestDox('recurses into slot children, dropping a nested element\'s stale attribution too')]
    public function testRecursesIntoSlotsAndDropsStaleAttributionOnChild(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
        ]);

        $staleConfig = new StubArrayLoaderConfig(['limit' => 999]);
        $child = ContentElementBuilder::create('card', 'child-1')
            ->withDataRequirement('product', 'entity', $staleConfig)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $parent = ContentElementBuilder::create('container', 'parent-1')
            ->withSlot('content', [$child])
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$parent]);

        $reconciledParent = $result[0];
        static::assertInstanceOf(ContentElement::class, $reconciledParent);

        $reconciledChild = $reconciledParent->getSlots()['content']->first();
        static::assertInstanceOf(ContentElement::class, $reconciledChild);
        static::assertSame([], $reconciledChild->getAttributedSpecifications());
    }

    #[TestDox('recurses into slot children of a raw-array node, dropping a nested raw child\'s stale attribution')]
    public function testRecursesIntoSlotsOfRawArrayNode(): void
    {
        // The raw-array payload is the Admin/Sync JSON shape this reconciler guards; its nested-slot walk is a
        // distinct code path from the ContentElement slot walk, so a nested raw child must reconcile too.
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
        ]);

        $node = [
            'component' => 'container',
            'slots' => [
                'content' => [
                    [
                        'component' => 'card',
                        'attributedSpecifications' => ['product' => 'spec-1'],
                        'dataRequirements' => ['product' => ['source' => 'entity', 'config' => ['limit' => 999]]],
                    ],
                ],
            ],
        ];

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$node]);

        $reconciledParent = $result[0];
        static::assertIsArray($reconciledParent);
        $reconciledChild = $reconciledParent['slots']['content'][0];
        static::assertIsArray($reconciledChild);
        static::assertArrayNotHasKey('attributedSpecifications', $reconciledChild);
    }

    /**
     * @param array<string, BindingSpecification> $specifications
     */
    private function reconciler(array $specifications, DataLoaderConfigSerializerProvider $provider): AttributionReconciler
    {
        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn($specifications);

        return new AttributionReconciler($registry, $provider, $this->canonicalizer);
    }

    /**
     * @param array<string, LoaderBinding> $resolves
     */
    private function specification(string $id, array $resolves): BindingSpecification
    {
        return new BindingSpecification($id, 'card', 'label', $resolves, [], 'core');
    }

    /**
     * A provider wired to a real, registered stub serializer that round-trips an array through decode()/encode()
     * for the "entity" source, the way a production storage-shape serializer does for a simple config.
     */
    private function provider(): DataLoaderConfigSerializerProvider
    {
        $serializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $serializer->method('decode')->willReturnCallback(
            static fn (array $data): AbstractContentDataLoaderConfig => new StubArrayLoaderConfig($data)
        );
        $serializer->method('encode')->willReturnCallback(
            static fn (AbstractContentDataLoaderConfig $config): array => $config->jsonSerialize()
        );

        return new DataLoaderConfigSerializerProvider(new ServiceLocator(['entity' => static fn () => $serializer]));
    }
}
