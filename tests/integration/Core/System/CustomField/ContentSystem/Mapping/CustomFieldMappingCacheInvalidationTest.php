<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\CustomField\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStore;
use Shopware\Core\Framework\ContentSystem\Cache\CacheFinalizer;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingExpander;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingMetadata;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\ContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedTreeFactory;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\ContentSystem\Mapping\CustomFieldMappingCacheTags;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[Group('cache')]
class CustomFieldMappingCacheInvalidationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $fieldId;

    private string $setId;

    private string $relationId;

    private string $fieldName;

    private CacheStore $store;

    private CacheInvalidator $invalidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fieldId = Uuid::randomHex();
        $this->setId = Uuid::randomHex();
        $this->relationId = Uuid::randomHex();
        $this->fieldName = 'mapping_material_' . Uuid::randomHex();
        $this->store = static::getContainer()->get(CacheStore::class);
        $this->invalidator = static::getContainer()->get(CacheInvalidator::class);
        static::getContainer()->get('custom_field_set.repository')->create([[
            'id' => $this->setId,
            'name' => 'mapping_cache_' . $this->setId,
            'active' => true,
            'relations' => [['id' => $this->relationId, 'entityName' => 'product']],
            'customFields' => [[
                'id' => $this->fieldId, 'name' => $this->fieldName,
                'type' => 'text', 'active' => true, 'storeApiAware' => true,
            ]],
        ]], Context::createDefaultContext());
        $this->invalidator->invalidateExpired();
    }

    #[DataProvider('mappingKinds')]
    public function testDeactivationAndReactivationInvalidateCustomFieldPagesButKeepCorePages(bool $inline): void
    {
        $custom = $this->cacheMapping('product.customFields.' . $this->fieldName, $inline);
        $core = $this->cacheMapping('product.name', $inline);
        static::assertContains(CustomFieldMappingCacheTags::METADATA, $custom['context']->getTags());
        static::assertNotContains(CustomFieldMappingCacheTags::METADATA, $core['context']->getTags());
        static::assertSame('Cotton', $this->store->lookup($custom['request'])?->getContent());
        static::assertSame('Core product', $this->store->lookup($core['request'])?->getContent());

        static::getContainer()->get('custom_field.repository')->update([['id' => $this->fieldId, 'active' => false]], Context::createDefaultContext());
        $this->invalidator->invalidateExpired();
        static::assertNull($this->store->lookup($custom['request']));
        static::assertSame('Core product', $this->store->lookup($core['request'])?->getContent());
        $unavailable = $this->cacheMapping('product.customFields.' . $this->fieldName, $inline);
        static::assertSame('', $this->store->lookup($unavailable['request'])?->getContent());
        static::assertContains(CustomFieldMappingCacheTags::METADATA, $unavailable['context']->getTags());

        static::getContainer()->get('custom_field.repository')->update([['id' => $this->fieldId, 'active' => true]], Context::createDefaultContext());
        $this->invalidator->invalidateExpired();
        static::assertNull($this->store->lookup($unavailable['request']));
        static::assertSame('Core product', $this->store->lookup($core['request'])?->getContent());
        $reactivated = $this->cacheMapping('product.customFields.' . $this->fieldName, $inline);
        static::assertSame('Cotton', $this->store->lookup($reactivated['request'])?->getContent());
    }

    #[DataProvider('mappingKinds')]
    public function testRealRelationDeletionAndRecreationInvalidateOnlyCustomFieldPages(bool $inline): void
    {
        $custom = $this->cacheMapping('product.customFields.' . $this->fieldName, $inline);
        $core = $this->cacheMapping('product.name', $inline);
        static::assertSame('Cotton', $this->store->lookup($custom['request'])?->getContent());
        static::assertSame('Core product', $this->store->lookup($core['request'])?->getContent());
        $observed = false;
        $expectedRelationId = $this->relationId;
        $listener = static function (EntityWrittenContainerEvent $event) use (&$observed, $expectedRelationId): void {
            $relation = $event->getEventByEntityName('custom_field_set_relation');
            if ($relation === null) {
                return;
            }
            $observed = true;
            static::assertContains($expectedRelationId, $relation->getIds());
            foreach ($relation->getPayloads() as $payload) {
                static::assertArrayNotHasKey('setId', $payload);
                static::assertArrayNotHasKey('customFieldSetId', $payload);
            }
        };
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(EntityWrittenContainerEvent::class, $listener);
        try {
            static::getContainer()->get('custom_field_set_relation.repository')->delete([['id' => $this->relationId]], Context::createDefaultContext());
        } finally {
            $dispatcher->removeListener(EntityWrittenContainerEvent::class, $listener);
        }
        static::assertTrue($observed);
        $this->invalidator->invalidateExpired();
        static::assertNull($this->store->lookup($custom['request']));
        static::assertSame('Core product', $this->store->lookup($core['request'])?->getContent());
        $unavailable = $this->cacheMapping('product.customFields.' . $this->fieldName, $inline);
        static::assertSame('', $this->store->lookup($unavailable['request'])?->getContent());

        static::getContainer()->get('custom_field_set_relation.repository')->create([[
            'id' => Uuid::randomHex(), 'customFieldSetId' => $this->setId, 'entityName' => 'product',
        ]], Context::createDefaultContext());
        $this->invalidator->invalidateExpired();
        static::assertNull($this->store->lookup($unavailable['request']));
        static::assertSame('Core product', $this->store->lookup($core['request'])?->getContent());
        $restored = $this->cacheMapping('product.customFields.' . $this->fieldName, $inline);
        static::assertSame('Cotton', $this->store->lookup($restored['request'])?->getContent());
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('metadataChanges')]
    public function testMetadataChangesInvalidateCustomFieldPagesAndPreserveCoreCacheHits(string $entity, array $changes, bool $delete): void
    {
        $custom = $this->cacheMapping('product.customFields.' . $this->fieldName, inline: true);
        $core = $this->cacheMapping('product.name', inline: true);
        static::assertNotNull($this->store->lookup($custom['request']));
        static::assertNotNull($this->store->lookup($core['request']));

        $repository = $entity === 'custom_field_set'
            ? static::getContainer()->get('custom_field_set.repository')
            : static::getContainer()->get('custom_field.repository');
        $payload = ['id' => $entity === 'custom_field_set' ? $this->setId : $this->fieldId, ...$changes];
        if ($delete) {
            $repository->delete([$payload], Context::createDefaultContext());
        } else {
            $repository->update([$payload], Context::createDefaultContext());
        }
        $this->invalidator->invalidateExpired();

        static::assertNull($this->store->lookup($custom['request']));
        static::assertSame('Core product', $this->store->lookup($core['request'])?->getContent());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, bool}>
     */
    public static function metadataChanges(): iterable
    {
        yield 'field deletion' => ['custom_field', [], true];
        yield 'set deletion' => ['custom_field_set', [], true];
        yield 'set deactivation' => ['custom_field_set', ['active' => false], false];
        yield 'Store API visibility withdrawal' => ['custom_field', ['storeApiAware' => false], false];
        yield 'field label change' => ['custom_field', ['config' => ['label' => ['en-GB' => 'Updated material']]], false];
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function mappingKinds(): iterable
    {
        yield 'whole-field mapping' => [false];
        yield 'inline mapping' => [true];
    }

    /**
     * @return array{request: Request, context: RenderingCacheContext}
     */
    private function cacheMapping(string $path, bool $inline): array
    {
        // Each call represents a fresh render request, including request-local catalogue reset.
        static::getContainer()->get(ContentSystemMappingCandidateRegistry::class)->reset();
        $request = Request::create('http://localhost/mapping-cache/' . Uuid::randomHex());
        $stack = static::getContainer()->get(RequestStack::class);
        $stack->push($request);
        try {
            $text = $inline ? '{{map:' . $path . '}}' : 'Authored';
            $raw = ['id' => 'mapped', 'component' => $inline ? 'Sw:Content:Text' : TestElementTypeLoader::MAPPABLE_PRIMITIVE, 'properties' => ['text' => $text]];
            if (!$inline) {
                $raw['acceptsContext']['text'] = ['type' => 'single', 'required' => false, 'scope' => 'root', 'source' => MappingSourceReference::fromRootPath($path)->jsonSerialize()];
            }
            $element = static::getContainer()->get(StoredElementCodec::class)->decode($raw);
            $product = new SalesChannelProductEntity();
            $product->setTranslated(['name' => 'Core product', 'customFields' => [$this->fieldName => 'Cotton']]);
            $ambient = ['product' => $product];
            $context = new RenderingCacheContext();
            $deliveries = static::getContainer()->get(ContextDeliveryResolver::class)->resolve([$element], [], $ambient, $context, 'product');
            $expanded = static::getContainer()->get(InlineMappingExpander::class)->expand(
                [$element],
                $ambient,
                'product',
                $context,
                inlineMappings: new InlineMappingMetadata([
                    'mapped' => ['text' => (new InlineMappingTokenParser())->parse($text)],
                ]),
                loaderValues: []
            );
            $rendered = static::getContainer()->get(RenderedTreeFactory::class)->create($expanded, $deliveries, [], RenderingMode::FULL);
            $response = new Response($rendered->tree[0]->properties['text'] ?? '');
            $response->setPublic()->setMaxAge(3600)->setSharedMaxAge(3600);
            $response->setDate(new \DateTimeImmutable('-2 seconds'));
            $response->headers->addCacheControlDirective('stale-while-revalidate', '1');
            static::getContainer()->get(CacheFinalizer::class)->finalize($request, $context);
            $this->store->write($request, $response);
        } finally {
            $stack->pop();
        }

        return ['request' => $request, 'context' => $context];
    }
}
