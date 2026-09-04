<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Binding;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * The attribution round-trip at the content_layout write boundary: absent, populated, and stale
 * `attributedSpecifications` across a direct DAL write, the Sync API, and a nested slot element.
 * {@see AttributionReconciler} drops a stale entry once the wiring is edited away from the specification.
 *
 * @internal
 */
#[Package('framework')]
class BindingAttributionPersistenceTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const CORE_MEDIA_BINDING_ID = 'core:Sw:Media:Image';

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('round-trips a raw scaffold with no attribution as absent, not {}, without failing write validation')]
    public function testRawScaffoldWithoutAttributionRoundTripsAsAbsent(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');
        $elementId = $this->ids->get('element');

        // A raw scaffold carries no attributedSpecifications key at all; the write must not fail the
        // Type('array') validation the Optional constraint entry exists to guard, and must not force the
        // key to {} either.
        $this->repository()->create([[
            'id' => $layoutId,
            'name' => 'raw-scaffold-' . $layoutId,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [['id' => $elementId, 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []]],
        ]], $context);

        static::assertArrayNotHasKey('attributedSpecifications', $this->rawLayoutElement($layoutId, 0));
        static::assertSame([], $this->reload($layoutId)->getLayout()[0]->attributedSpecifications);
    }

    #[TestDox('round-trips a populated attributedSpecifications map as an array with its entries intact when the wiring matches the specification')]
    public function testPopulatedAttributionRoundTripsAsArrayWhenWiringMatches(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');
        $elementId = $this->ids->get('element');

        $this->repository()->create([[
            'id' => $layoutId,
            'name' => 'bound-element-' . $layoutId,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [$this->boundImageElement($elementId)],
        ]], $context);

        static::assertSame(
            ['media' => self::CORE_MEDIA_BINDING_ID],
            $this->reload($layoutId)->getLayout()[0]->attributedSpecifications
        );
        static::assertSame(['media' => self::CORE_MEDIA_BINDING_ID], $this->rawLayoutElement($layoutId, 0)['attributedSpecifications']);
    }

    #[TestDox('drops attribution at the write seam once the wiring is edited away from the specification, on a direct DAL write')]
    public function testReconciliationDropsAttributionAfterDirectDalWiringEdit(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');
        $elementId = $this->ids->get('element');

        $this->repository()->create([[
            'id' => $layoutId,
            'name' => 'reconcile-dal-' . $layoutId,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [$this->boundImageElement($elementId)],
        ]], $context);

        static::assertSame(
            ['media' => self::CORE_MEDIA_BINDING_ID],
            $this->reload($layoutId)->getLayout()[0]->attributedSpecifications
        );

        $this->repository()->update([[
            'id' => $layoutId,
            'layout' => [$this->boundImageElement($elementId, 'somethingElse')],
        ]], $context);

        $reconciled = $this->reload($layoutId)->getLayout()[0];
        static::assertSame([], $reconciled->attributedSpecifications);

        // the wiring itself stays exactly as edited -- reconciliation drops the attribution, never the wiring
        $requirement = $reconciled->dataRequirements['media'];
        static::assertInstanceOf(EntityLoaderConfig::class, $requirement->config);
        static::assertSame('somethingElse', $requirement->config->property);
    }

    #[TestDox('drops attribution at the write seam once the wiring is edited away from the specification, through the Sync API')]
    public function testReconciliationDropsAttributionAfterSyncApiWiringEdit(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');
        $elementId = $this->ids->get('element');

        $this->repository()->create([[
            'id' => $layoutId,
            'name' => 'reconcile-sync-' . $layoutId,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [$this->boundImageElement($elementId)],
        ]], $context);

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode([
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => 'content_layout',
                'payload' => [[
                    'id' => $layoutId,
                    'layout' => [$this->boundImageElement($elementId, 'somethingElse')],
                ]],
            ],
        ], \JSON_THROW_ON_ERROR));

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        static::assertSame([], $this->reload($layoutId)->getLayout()[0]->attributedSpecifications);
    }

    #[TestDox('drops a dangling attribution whose specification id no longer resolves from the registry, keeping the wiring intact')]
    public function testReconciliationDropsAttributionForUnregisteredSpecificationId(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');
        $elementId = $this->ids->get('element');

        // A source-qualified id shaped like an app binding, but no such app/spec is registered. This exercises
        // AttributionReconciler's "specification no longer resolves from the registry" drop branch (specWiring()
        // returns null once registry->get() returns null) end-to-end through a real DAL write -- the
        // integration-level proxy for "the app/plugin that shipped the specification was uninstalled". The full
        // install/uninstall lifecycle is a separate lifecycle concern; the reconciler's drop is
        // what this feature owns and what this test proves.
        $this->repository()->create([[
            'id' => $layoutId,
            'name' => 'reconcile-dangling-' . $layoutId,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [$this->boundImageElement($elementId, specificationId: 'app:GhostApp:removed-binding')],
        ]], $context);

        $stored = $this->reload($layoutId)->getLayout()[0];
        static::assertSame([], $stored->attributedSpecifications);

        // the wiring itself -- what actually makes the element serve -- stays untouched; only the dangling
        // attribution bookkeeping is dropped
        $requirement = $stored->dataRequirements['media'];
        static::assertSame('entity', $requirement->source);
        static::assertInstanceOf(EntityLoaderConfig::class, $requirement->config);
        static::assertSame('media', $requirement->config->entity);
        static::assertSame('mediaId', $requirement->config->property);
    }

    #[TestDox('rejects a write whose element carries a malformed domain-loader dataRequirements config with a clean write-constraint violation, not an uncaught exception')]
    public function testWriteWithMalformedDomainLoaderConfigIsRejectedWithCleanViolation(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');
        $elementId = $this->ids->get('element');

        // "depth" must be a positive int; NavigationLoaderConfigSerializer::decode() rejects this with a
        // CategoryException (a domain exception, not a ContentSystemException), which
        // DataLoaderConfigSerializerProvider reclassifies to a ContentSystemException client defect at the
        // shared decode chokepoint. The layout field serializer decodes the element's dataRequirements while
        // encoding the write payload, so that reclassified client defect surfaces there as a write-constraint
        // violation carrying its own error code -- the write comes back as a clean WriteException, never an
        // uncaught domain exception surfacing as a 500. (AttributionReconciler's own decode-during-normalize
        // drop-not-throw path is covered separately by AttributionReconcilerTest.)
        $element = [
            'id' => $elementId,
            'component' => 'Sw:Media:Image',
            'properties' => ['mediaId' => 'a-media-id'],
            'dataRequirements' => [
                'navigation' => ['source' => 'navigation', 'config' => ['depth' => 'not-an-int']],
            ],
        ];

        try {
            $this->repository()->create([[
                'id' => $layoutId,
                'name' => 'malformed-domain-loader-config-' . $layoutId,
                'version' => '1.0.0',
                'rootSource' => 'none',
                'layout' => [$element],
            ]], $context);
            static::fail('Expected the write to reject the malformed domain-loader config.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('depth', $exception->getMessage());
            static::assertContains(
                ContentSystemException::INVALID_FIELD_VALUE_TYPE,
                array_column(iterator_to_array($exception->getErrors(), false), 'code')
            );
        }

        static::assertNull($this->repository()->search(new Criteria([$layoutId]), $context)->getEntities()->first());
    }

    #[TestDox('recurses into a slot and drops a nested bound element\'s stale attribution on a direct DAL write')]
    public function testReconciliationDropsNestedElementAttributionInSlot(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->ids->get('layout');
        $parentId = $this->ids->get('parent');
        $childId = $this->ids->get('child');

        $this->repository()->create([[
            'id' => $layoutId,
            'name' => 'reconcile-nested-' . $layoutId,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [[
                'id' => $parentId,
                'component' => TestElementTypeLoader::RESOLVABLE,
                'properties' => [],
                'slots' => ['content' => [$this->boundImageElement($childId)]],
            ]],
        ]], $context);

        static::assertSame(['media' => self::CORE_MEDIA_BINDING_ID], $this->onlySlotChild($layoutId)->attributedSpecifications);

        $this->repository()->update([[
            'id' => $layoutId,
            'layout' => [[
                'id' => $parentId,
                'component' => TestElementTypeLoader::RESOLVABLE,
                'properties' => [],
                'slots' => ['content' => [$this->boundImageElement($childId, 'somethingElse')]],
            ]],
        ]], $context);

        static::assertSame([], $this->onlySlotChild($layoutId)->attributedSpecifications);
    }

    /**
     * A Sw:Media:Image element wired and attributed to core:Sw:Media:Image. mediaId is always filled so
     * the wired `media` reference resolves (Stored) and the derived-required `mediaId` input is never
     * unfilled, so the element stays resolvable and the write is never rejected by the resolvability gate;
     * $property lets a caller edit the wiring away from what the specification produces while keeping the
     * attribution entry stale; $specificationId lets a caller attribute the (still-matching) wiring to a
     * specification id that does not resolve from the registry.
     *
     * @return array<string, mixed>
     */
    private function boundImageElement(string $id, string $property = 'mediaId', string $specificationId = self::CORE_MEDIA_BINDING_ID): array
    {
        return [
            'id' => $id,
            'component' => 'Sw:Media:Image',
            // The wired property always carries a value: `media` is a required reference, so a wired-but-unfilled
            // input would be rejected at the write gate (unfilled_required_input) before reconciliation is observable.
            'properties' => ['mediaId' => 'a-media-id', $property => 'a-media-id'],
            'dataRequirements' => [
                'media' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => $property]],
            ],
            'attributedSpecifications' => ['media' => $specificationId],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rawLayoutElement(string $layoutId, int $index): array
    {
        $raw = $this->connection()->fetchOne(
            'SELECT `layout` FROM `content_layout` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($layoutId)]
        );
        static::assertIsString($raw);

        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertArrayHasKey($index, $decoded);
        static::assertIsArray($decoded[$index]);

        return $decoded[$index];
    }

    /**
     * The single element in the root element's `content` slot, asserted to be exactly one so an indexed read
     * cannot silently pick a different child than the fixture wrote.
     */
    private function onlySlotChild(string $layoutId): StoredElement
    {
        $root = $this->reload($layoutId)->getLayout()[0];
        static::assertArrayHasKey('content', $root->slots);
        static::assertCount(1, $root->slots['content']);

        return $root->slots['content'][0];
    }

    private function reload(string $layoutId): ContentLayoutEntity
    {
        $layout = $this->repository()->search(new Criteria([$layoutId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        return $layout;
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    private function connection(): Connection
    {
        $connection = $this->getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
