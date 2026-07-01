<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Binding;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * Covers the §19 integration acceptance for the write-boundary attribution round trip: an empty
 * `attributedSpecifications` never fails the `Type('array')` write validation and round-trips as
 * absent/`[]`, a populated one round-trips as an array when its wiring still matches the specification,
 * and {@see AttributionReconciler} drops a stale entry on
 * a real DAL write once the wiring has been edited away from the specification — across a direct DAL
 * write, the Sync API, and a nested element inside a slot.
 *
 * @internal
 */
#[Package('framework')]
class BindingAttributionPersistenceTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const CORE_MEDIA_BINDING_ID = 'core:from-media-library';

    #[TestDox('round-trips a raw scaffold with no attribution as absent, not {}, without failing write validation')]
    public function testRawScaffoldWithoutAttributionRoundTripsAsAbsent(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $elementId = Uuid::randomHex();

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
        static::assertSame([], $this->reload($layoutId)->getLayout()[0]->getAttributedSpecifications());
    }

    #[TestDox('round-trips a populated attributedSpecifications map as an array with its entries intact when the wiring matches the specification')]
    public function testPopulatedAttributionRoundTripsAsArrayWhenWiringMatches(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $elementId = Uuid::randomHex();

        $this->repository()->create([[
            'id' => $layoutId,
            'name' => 'bound-element-' . $layoutId,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [$this->boundImageElement($elementId)],
        ]], $context);

        static::assertSame(
            ['media' => self::CORE_MEDIA_BINDING_ID],
            $this->reload($layoutId)->getLayout()[0]->getAttributedSpecifications()
        );
        static::assertSame(['media' => self::CORE_MEDIA_BINDING_ID], $this->rawLayoutElement($layoutId, 0)['attributedSpecifications']);
    }

    #[TestDox('drops attribution at the write seam once the wiring is edited away from the specification, on a direct DAL write')]
    public function testReconciliationDropsAttributionAfterDirectDalWiringEdit(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $elementId = Uuid::randomHex();

        $this->repository()->create([[
            'id' => $layoutId,
            'name' => 'reconcile-dal-' . $layoutId,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [$this->boundImageElement($elementId)],
        ]], $context);

        static::assertSame(
            ['media' => self::CORE_MEDIA_BINDING_ID],
            $this->reload($layoutId)->getLayout()[0]->getAttributedSpecifications()
        );

        $this->repository()->update([[
            'id' => $layoutId,
            'layout' => [$this->boundImageElement($elementId, 'somethingElse')],
        ]], $context);

        $reconciled = $this->reload($layoutId)->getLayout()[0];
        static::assertSame([], $reconciled->getAttributedSpecifications());

        // the wiring itself stays exactly as edited -- reconciliation drops the attribution, never the wiring
        $requirement = $reconciled->getDataRequirements()['media'];
        static::assertInstanceOf(EntityLoaderConfig::class, $requirement->config);
        static::assertSame('somethingElse', $requirement->config->property);
    }

    #[TestDox('drops attribution at the write seam once the wiring is edited away from the specification, through the Sync API')]
    public function testReconciliationDropsAttributionAfterSyncApiWiringEdit(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $elementId = Uuid::randomHex();

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

        static::assertSame([], $this->reload($layoutId)->getLayout()[0]->getAttributedSpecifications());
    }

    #[TestDox('drops a dangling attribution whose specification id no longer resolves from the registry, keeping the wiring intact')]
    public function testReconciliationDropsAttributionForUnregisteredSpecificationId(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $elementId = Uuid::randomHex();

        // A source-qualified id shaped like an app binding, but no such app/spec is registered. This exercises
        // AttributionReconciler's "specification no longer resolves from the registry" drop branch (specWiring()
        // returns null once registry->get() returns null) end-to-end through a real DAL write -- the
        // integration-level proxy for "the app/plugin that shipped the specification was uninstalled". The full
        // install/uninstall lifecycle is a separate lifecycle concern (see spec §13); the reconciler's drop is
        // what this feature owns and what this test proves.
        $this->repository()->create([[
            'id' => $layoutId,
            'name' => 'reconcile-dangling-' . $layoutId,
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [$this->boundImageElement($elementId, specificationId: 'app:GhostApp:removed-binding')],
        ]], $context);

        $stored = $this->reload($layoutId)->getLayout()[0];
        static::assertSame([], $stored->getAttributedSpecifications());

        // the wiring itself -- what actually makes the element serve -- stays untouched; only the dangling
        // attribution bookkeeping is dropped
        $requirement = $stored->getDataRequirements()['media'];
        static::assertSame('entity', $requirement->source);
        static::assertInstanceOf(EntityLoaderConfig::class, $requirement->config);
        static::assertSame('media', $requirement->config->entity);
        static::assertSame('mediaId', $requirement->config->property);
    }

    #[TestDox('recurses into a slot and drops a nested bound element\'s stale attribution on a direct DAL write')]
    public function testReconciliationDropsNestedElementAttributionInSlot(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

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

        $child = $this->reload($layoutId)->getLayout()[0]->getSlots()['content']->first();
        static::assertInstanceOf(ContentElement::class, $child);
        static::assertSame(['media' => self::CORE_MEDIA_BINDING_ID], $child->getAttributedSpecifications());

        $this->repository()->update([[
            'id' => $layoutId,
            'layout' => [[
                'id' => $parentId,
                'component' => TestElementTypeLoader::RESOLVABLE,
                'properties' => [],
                'slots' => ['content' => [$this->boundImageElement($childId, 'somethingElse')]],
            ]],
        ]], $context);

        $reconciledChild = $this->reload($layoutId)->getLayout()[0]->getSlots()['content']->first();
        static::assertInstanceOf(ContentElement::class, $reconciledChild);
        static::assertSame([], $reconciledChild->getAttributedSpecifications());
    }

    /**
     * A Sw:Media:Image element wired and attributed to core:from-media-library. mediaId is always filled
     * (the type declares it required with no default) so the element stays resolvable and the write is
     * never rejected by the resolvability gate; $property lets a caller edit the wiring away from what the
     * specification produces while keeping the attribution entry stale; $specificationId lets a caller
     * attribute the (still-matching) wiring to a specification id that does not resolve from the registry.
     *
     * @return array<string, mixed>
     */
    private function boundImageElement(string $id, string $property = 'mediaId', string $specificationId = self::CORE_MEDIA_BINDING_ID): array
    {
        return [
            'id' => $id,
            'component' => 'Sw:Media:Image',
            'properties' => ['mediaId' => 'a-media-id'],
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

    private function reload(string $layoutId): ContentLayoutEntity
    {
        $layout = $this->repository()->search(new Criteria([$layoutId]), Context::createDefaultContext())->first();
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
