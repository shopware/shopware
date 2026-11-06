<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubArrayLoaderConfig;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
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
        $element = StoredElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(StoredElement::class, $reconciled);
        static::assertSame(['product' => 'spec-1'], $reconciled->attributedSpecifications);
    }

    #[TestDox('keeps attribution and passes properties through unchanged, since stored property values are never compared')]
    public function testKeepsAttributionRegardlessOfPropertyValueChanges(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
        ]);

        $config = new StubArrayLoaderConfig(['limit' => 5]);
        $element = StoredElementBuilder::create('card', 'elem-1')
            ->withProperty('mediaId', 'edited-after-binding')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(StoredElement::class, $reconciled);
        static::assertSame(['product' => 'spec-1'], $reconciled->attributedSpecifications);
        static::assertSame('edited-after-binding', $reconciled->property('mediaId')?->jsonSerialize());
    }

    #[TestDox('recurses into slot children, dropping a nested element\'s stale attribution too')]
    public function testRecursesIntoSlotsAndDropsStaleAttributionOnChild(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
        ]);

        $staleConfig = new StubArrayLoaderConfig(['limit' => 999]);
        $child = StoredElementBuilder::create('card', 'child-1')
            ->withDataRequirement('product', 'entity', $staleConfig)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $parent = StoredElementBuilder::create('container', 'parent-1')
            ->withSlot('content', [$child])
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$parent]);

        $reconciledParent = $result[0];
        static::assertInstanceOf(StoredElement::class, $reconciledParent);

        $reconciledChild = $reconciledParent->slots['content'][0];
        static::assertSame([], $reconciledChild->attributedSpecifications);
    }

    #[TestDox('drops a key whose attribution no longer matches the specification wiring, leaving the wiring itself untouched')]
    public function testDropsAttributionOnWiringMismatch(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['entity' => 'media', 'limit' => 5]),
        ]);

        $config = new StubArrayLoaderConfig(['entity' => 'media', 'limit' => 10]);
        $element = StoredElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(StoredElement::class, $reconciled);
        static::assertSame([], $reconciled->attributedSpecifications);
        static::assertSame($element->dataRequirements, $reconciled->dataRequirements);
    }

    #[TestDox('drops attribution when the specification no longer resolves from the registry')]
    public function testDropsAttributionWhenSpecificationNotFound(): void
    {
        $config = new StubArrayLoaderConfig(['entity' => 'media']);
        $element = StoredElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'gone-spec')
            ->build();

        $result = $this->reconciler([], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(StoredElement::class, $reconciled);
        static::assertSame([], $reconciled->attributedSpecifications);
        static::assertSame($element->dataRequirements, $reconciled->dataRequirements);
    }

    #[TestDox('drops attribution when the specification no longer resolves the attributed key')]
    public function testDropsAttributionWhenSpecificationNoLongerResolvesKey(): void
    {
        $specification = $this->specification('spec-1', []);

        $config = new StubArrayLoaderConfig(['entity' => 'media']);
        $element = StoredElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(StoredElement::class, $reconciled);
        static::assertSame([], $reconciled->attributedSpecifications);
        static::assertSame($element->dataRequirements, $reconciled->dataRequirements);
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

        $element = StoredElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $matchingConfig)
            ->withDataRequirement('media', 'entity', $divergedConfig)
            ->withAttributedSpecification('product', 'spec-1')
            ->withAttributedSpecification('media', 'spec-1')
            ->build();

        $result = $this->reconciler(['spec-1' => $specification], $this->provider())
            ->reconcile([$element]);

        $reconciled = $result[0];
        static::assertInstanceOf(StoredElement::class, $reconciled);
        static::assertSame(['product' => 'spec-1'], $reconciled->attributedSpecifications);
    }

    #[TestDox('rejects the write when the element wiring source has no registered config serializer, instead of dropping the attribution')]
    public function testThrowsWhenElementSourceUnregistered(): void
    {
        $specification = $this->specification('spec-1', [
            'product' => new LoaderBinding('entity', ['limit' => 5]),
        ]);

        $config = new StubArrayLoaderConfig(['limit' => 5]);
        $element = StoredElementBuilder::create('card', 'elem-1')
            // Only "entity" (the specification's own binding source) is registered on the provider below; this
            // element wiring points at a source that is not — an uninstalled loader whose config serializer was
            // de-registered. The real, unstubbed DataLoaderConfigSerializerProvider::encode() throws
            // configSerializerNotRegistered for it.
            ->withDataRequirement('product', 'removed_plugin_source', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $reconciler = $this->reconciler(['spec-1' => $specification], $this->provider());

        // CONFIG_SERIALIZER_NOT_REGISTERED is a CLIENT_DEFECT_CODE, and used to be swallowed here: the write
        // stored the element with its attribution silently dropped, indistinguishable from an element nothing
        // ever claimed. The wiring cannot be encoded, so it cannot be judged, so the write is refused. The
        // re-thrown fault names the element ("elem-1") so a caller can remove the stale wiring deliberately.
        $expected = ContentSystemException::configSerializerNotRegistered('removed_plugin_source', 'elem-1');
        static::assertTrue(ContentSystemException::isClientDefect($expected));
        static::assertStringContainsString('elem-1', $expected->getMessage());

        $this->expectExceptionObject($expected);

        $reconciler->reconcile([$element]);
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
        $element = StoredElementBuilder::create('card', 'elem-1')
            ->withDataRequirement('product', 'entity', $config)
            ->withAttributedSpecification('product', 'spec-1')
            ->build();

        $reconciler = $this->reconciler(['spec-1' => $specification], $provider);

        $this->expectExceptionObject($internalFault);

        $reconciler->reconcile([$element]);
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
