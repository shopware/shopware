<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Collection;

/**
 * @internal
 */
#[Package('framework')]
class ContentLayoutMutationControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const BASE_URL = '/api/_action/content-system/layout/';

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('inserts an element and commits the re-resolved layout to storage')]
    public function testInsertElementPersistsToStorage(): void
    {
        $layoutId = $this->createLayout([$this->element('block-a', TestElementTypeLoader::RESOLVABLE)]);

        $body = $this->mutate('insert-element', $layoutId, [
            'type' => TestElementTypeLoader::RESOLVABLE,
            'expectedVersion' => null,
        ]);

        static::assertCount(2, $body['layout']);
        static::assertCount(2, $this->reload($layoutId)->getLayout());
    }

    #[TestDox('rejects a mutation with a stale expected version (409) without writing')]
    public function testStaleVersionConflictDoesNotWrite(): void
    {
        $layoutId = $this->createLayout([
            $this->element('block-a', TestElementTypeLoader::RESOLVABLE),
            $this->element('block-b', TestElementTypeLoader::RESOLVABLE),
        ]);

        $this->request('remove-element', $layoutId, [
            'elementId' => 'block-a',
            'expectedVersion' => '2020-01-01T00:00:00.000+00:00',
        ]);

        static::assertSame(Response::HTTP_CONFLICT, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertSame(['block-a', 'block-b'], $this->layoutIds($layoutId));
    }

    #[TestDox('rejects a second writer that started from the same version once the first has committed')]
    public function testTwoWritersFromSameVersionYieldOneConflict(): void
    {
        $layoutId = $this->createLayout([
            $this->element('block-a', TestElementTypeLoader::RESOLVABLE),
            $this->element('block-b', TestElementTypeLoader::RESOLVABLE),
        ]);

        // Both writers read the same starting revision: a never-updated layout, whose token is null.
        // The named lock in PersistedLayoutMutator serializes them under real concurrency; here the
        // sequential manifestation is pinned: the first writer commits and bumps updatedAt, so the
        // second writer's once-valid token no longer matches and it gets a 409 without writing.
        $token = null;

        $this->mutate('remove-element', $layoutId, ['elementId' => 'block-a', 'expectedVersion' => $token]);

        $this->request('remove-element', $layoutId, ['elementId' => 'block-b', 'expectedVersion' => $token]);

        static::assertSame(Response::HTTP_CONFLICT, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertSame(['block-b'], $this->layoutIds($layoutId));
    }

    #[TestDox('accepts the updatedAt token a client reads back through the Admin API')]
    public function testMatchingVersionTokenAfterUpdateIsAccepted(): void
    {
        $layoutId = $this->createLayout([
            $this->element('block-a', TestElementTypeLoader::RESOLVABLE),
            $this->element('block-b', TestElementTypeLoader::RESOLVABLE),
        ]);

        // first mutation: a never-updated layout matches a null token and sets updatedAt
        $this->mutate('insert-element', $layoutId, ['type' => TestElementTypeLoader::RESOLVABLE, 'expectedVersion' => null]);

        // the token a real client holds is the updatedAt serialized by the Admin API, not a format of our choosing
        $token = $this->apiUpdatedAt($layoutId);

        $this->mutate('remove-element', $layoutId, ['elementId' => 'block-a', 'expectedVersion' => $token]);

        static::assertNotContains('block-a', $this->layoutIds($layoutId));
    }

    #[TestDox('rejects a persisted edit that breaks resolvability for a bound source without writing')]
    public function testGateRejectsResolvabilityBreakingEditForBoundLayout(): void
    {
        $layoutId = $this->createLayout([$this->element('block-a', TestElementTypeLoader::RESOLVABLE)]);
        $this->bindCategory($layoutId);

        $this->request('replace-element', $layoutId, [
            'elementId' => 'block-a',
            'newType' => TestElementTypeLoader::UNRESOLVABLE,
            'expectedVersion' => null,
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        // assert the stable violation code (the wire contract), not the human-readable message text
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ViolationCode::UnresolvedRequired->value, array_column($body['errors'], 'code'));
        static::assertSame(TestElementTypeLoader::RESOLVABLE, $this->reload($layoutId)->getLayout()[0]->component);
    }

    #[TestDox('persists a replace that detaches slot content and returns the orphans for re-attachment')]
    public function testReplaceDetachingContentReportsOrphans(): void
    {
        $parent = $this->element('parent', TestElementTypeLoader::RESOLVABLE);
        $parent['slots'] = ['content' => [$this->element('kid', TestElementTypeLoader::RESOLVABLE)]];
        $layoutId = $this->createLayout([$parent]);

        $body = $this->mutate('replace-element', $layoutId, [
            'elementId' => 'parent',
            'newType' => TestElementTypeLoader::RESOLVABLE,
            'expectedVersion' => null,
        ]);

        static::assertSame(['kid'], array_column($body['orphaned'], 'id'));
        static::assertSame([], $this->reload($layoutId)->getLayout()[0]->slots);
    }

    #[TestDox('attaches a returned orphan subtree to a stored layout with a server-minted id')]
    public function testAttachElementPersistsToStorage(): void
    {
        $layoutId = $this->createLayout([$this->element('block-a', TestElementTypeLoader::RESOLVABLE)]);

        $body = $this->mutate('attach-element', $layoutId, [
            'element' => $this->element('incoming', TestElementTypeLoader::RESOLVABLE),
            'expectedVersion' => null,
        ]);

        static::assertCount(2, $body['layout']);
        static::assertCount(2, $this->reload($layoutId)->getLayout());
        static::assertNotContains('incoming', $this->layoutIds($layoutId));
    }

    #[TestDox('re-attaches an orphan returned by a persisted replace, recovering the detached subtree')]
    public function testReplaceOrphanCanBeReattached(): void
    {
        $parent = $this->element('parent', TestElementTypeLoader::RESOLVABLE);
        $parent['slots'] = ['content' => [$this->element('kid', TestElementTypeLoader::RESOLVABLE)]];
        $layoutId = $this->createLayout([$parent]);

        // a replace into a type without that slot detaches the child and hands it back as an orphan
        $replaced = $this->mutate('replace-element', $layoutId, [
            'elementId' => 'parent',
            'newType' => TestElementTypeLoader::RESOLVABLE,
            'expectedVersion' => null,
        ]);
        static::assertCount(1, $replaced['orphaned']);

        // feed the returned orphan straight back to attach (with the bumped token) to recover it at the root
        $reattached = $this->mutate('attach-element', $layoutId, [
            'element' => $replaced['orphaned'][0],
            'expectedVersion' => $this->apiUpdatedAt($layoutId),
        ]);

        static::assertCount(2, $reattached['layout']);
        static::assertCount(2, $this->reload($layoutId)->getLayout());
    }

    #[TestDox('returns 404 for a mutation targeting an unknown layout id')]
    public function testUnknownLayoutReturnsNotFound(): void
    {
        $this->request('remove-element', $this->ids->get('unknown-layout'), ['elementId' => 'block-a', 'expectedVersion' => null]);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->getBrowser()->getResponse()->getStatusCode());
    }

    #[TestDox('reorders two stored root elements via a persisted move and commits the new order')]
    public function testMoveElementPersistsToStorage(): void
    {
        $layoutId = $this->createLayout([
            $this->element('block-a', TestElementTypeLoader::RESOLVABLE),
            $this->element('block-b', TestElementTypeLoader::RESOLVABLE),
        ]);

        $body = $this->mutate('move-element', $layoutId, ['elementId' => 'block-b', 'index' => 0, 'expectedVersion' => null]);

        static::assertSame(['block-b', 'block-a'], array_column($body['layout'], 'id'));
        static::assertSame(['block-b', 'block-a'], $this->layoutIds($layoutId));
    }

    #[TestDox('duplicates a stored element with a server-minted id and commits the clone')]
    public function testDuplicateElementPersistsToStorage(): void
    {
        $layoutId = $this->createLayout([$this->element('block-a', TestElementTypeLoader::RESOLVABLE)]);

        $body = $this->mutate('duplicate-element', $layoutId, ['elementId' => 'block-a', 'expectedVersion' => null]);

        static::assertCount(2, $body['layout']);
        static::assertCount(2, $this->reload($layoutId)->getLayout());
        static::assertCount(1, $body['affectedElementIds']);
        static::assertNotSame('block-a', $body['affectedElementIds'][0]);
    }

    #[TestDox('wraps two stored sibling roots into a freshly minted container and commits it')]
    public function testWrapElementsPersistsToStorage(): void
    {
        $layoutId = $this->createLayout([
            $this->element('block-a', TestElementTypeLoader::RESOLVABLE),
            $this->element('block-b', TestElementTypeLoader::RESOLVABLE),
        ]);

        $body = $this->mutate('wrap-elements', $layoutId, [
            'elementIds' => ['block-a', 'block-b'],
            'containerType' => TestElementTypeLoader::RESOLVABLE,
            'slot' => 'content',
            'expectedVersion' => null,
        ]);

        static::assertCount(1, $body['layout']);
        static::assertCount(1, $this->reload($layoutId)->getLayout());
        static::assertContains('block-a', $body['affectedElementIds']);
        static::assertContains('block-b', $body['affectedElementIds']);
        // the two roots are nested inside the new container's slot, not silently dropped or orphaned
        static::assertSame([], $body['orphaned']);
        static::assertSame(['block-a', 'block-b'], array_column($body['layout'][0]['slots']['content'], 'id'));
    }

    #[TestDox('unwraps a stored container, hoists its children to the root, and reports nothing dropped')]
    public function testUnwrapElementPersistsToStorage(): void
    {
        $container = $this->element('container', TestElementTypeLoader::RESOLVABLE);
        $container['slots'] = ['content' => [
            $this->element('block-a', TestElementTypeLoader::RESOLVABLE),
            $this->element('block-b', TestElementTypeLoader::RESOLVABLE),
        ]];
        $layoutId = $this->createLayout([$container]);

        $body = $this->mutate('unwrap-element', $layoutId, ['containerElementId' => 'container', 'expectedVersion' => null]);

        static::assertSame(['block-a', 'block-b'], array_column($body['layout'], 'id'));
        static::assertSame(['block-a', 'block-b'], $this->layoutIds($layoutId));
        // the property-free, wiring-free container holds nothing the hoisted children cannot keep
        static::assertSame([], $body['droppedWiring']);
        static::assertSame([], $body['droppedProperties']);
    }

    #[TestDox('rejects an unparseable version token with a 400 once the layout has been updated, without writing')]
    public function testInvalidVersionTokenReturnsBadRequest(): void
    {
        $layoutId = $this->createLayout([
            $this->element('block-a', TestElementTypeLoader::RESOLVABLE),
            $this->element('block-b', TestElementTypeLoader::RESOLVABLE),
        ]);

        // A never-updated layout short-circuits any non-null token to a 409 before the token is parsed; bump
        // updatedAt with a first mutation so the unparseable-token branch (400 invalidVersionToken) is the one under test.
        $this->mutate('insert-element', $layoutId, ['type' => TestElementTypeLoader::RESOLVABLE, 'expectedVersion' => null]);
        $committed = $this->layoutIds($layoutId);

        $this->request('remove-element', $layoutId, ['elementId' => 'block-a', 'expectedVersion' => 'not-a-valid-token']);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        // confirm this is the unparseable-token 400, not some other 400 (e.g. a payload-binding failure)
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::INVALID_VERSION_TOKEN, array_column($body['errors'], 'code'));
        static::assertSame($committed, $this->layoutIds($layoutId));
    }

    #[TestDox('rejects an unknown request field on a persisted mutation with a 400 and the unknownRequestField code without writing')]
    public function testRejectsUnknownRequestField(): void
    {
        $layoutId = $this->createLayout([$this->element('block-a', TestElementTypeLoader::RESOLVABLE)]);

        $this->request('insert-element', $layoutId, [
            'type' => TestElementTypeLoader::RESOLVABLE,
            'expectedVersion' => null,
            'entityType' => 'product',
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::UNKNOWN_REQUEST_FIELD, array_column($body['errors'], 'code'));
        static::assertSame(['block-a'], $this->layoutIds($layoutId));
    }

    #[TestDox('rejects a structurally impossible persisted op (unknown element id) with a 400 without writing')]
    public function testStructuralImpossibilityReturnsBadRequest(): void
    {
        $layoutId = $this->createLayout([$this->element('block-a', TestElementTypeLoader::RESOLVABLE)]);

        $this->request('remove-element', $layoutId, ['elementId' => 'ghost', 'expectedVersion' => null]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        // confirm this is the structural-impossibility 400 (unknown target), not a payload-binding 400
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::MUTATION_TARGET_NOT_FOUND, array_column($body['errors'], 'code'));
        static::assertSame(['block-a'], $this->layoutIds($layoutId));
    }

    // These three tests cover the negative paths that need no shipped specification, and double as the
    // persisted bind-element route-wiring check: an app-level error (not a Symfony 404 route-not-found body)
    // proves the request reached ContentLayoutMutationController::bind(). The positive round trip against the
    // real shipped core:Sw:Media:Image default follows below.
    #[TestDox('returns 404 for a bind-element mutation targeting an unknown layout id')]
    public function testBindElementUnknownLayoutReturnsNotFound(): void
    {
        $this->request('bind-element', $this->ids->get('unknown-layout'), [
            'elementId' => 'block-a',
            'bindingSpecificationId' => 'core:Sw:Media:Image',
            'expectedVersion' => null,
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::CONTENT_LAYOUT_NOT_FOUND, array_column($body['errors'], 'code'));
    }

    #[TestDox('rejects a bind-element mutation with a stale expected version (409) without writing')]
    public function testBindElementStaleVersionConflictDoesNotWrite(): void
    {
        $layoutId = $this->createLayout([$this->element('block-a', TestElementTypeLoader::RESOLVABLE)]);

        $this->request('bind-element', $layoutId, [
            'elementId' => 'block-a',
            'bindingSpecificationId' => 'core:Sw:Media:Image',
            'expectedVersion' => '2020-01-01T00:00:00.000+00:00',
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::LAYOUT_VERSION_CONFLICT, array_column($body['errors'], 'code'));
        static::assertSame(['block-a'], $this->layoutIds($layoutId));
    }

    #[TestDox('rejects an unknown bindingSpecificationId on a persisted bind with a 400 without writing')]
    public function testBindElementRejectsUnknownBindingSpecification(): void
    {
        $layoutId = $this->createLayout([$this->element('block-a', TestElementTypeLoader::RESOLVABLE)]);

        $this->request('bind-element', $layoutId, [
            'elementId' => 'block-a',
            'bindingSpecificationId' => 'ghost:not-a-spec',
            'expectedVersion' => null,
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::BINDING_SPECIFICATION_NOT_FOUND, array_column($body['errors'], 'code'));
        static::assertSame(['block-a'], $this->layoutIds($layoutId));
    }

    #[TestDox('inlines the core Sw:Media:Image default specification\'s wiring and attribution on a persisted bind, committing it to storage')]
    public function testBindElementPersistsCoreSpecificationWiringAndAttribution(): void
    {
        // media is required with no parent-provided context on this layout's "category" root source, so the
        // element must already carry stored wiring for it up front: an unresolved required reference fails
        // write validation on the create() below, before bind-element is ever reached. Since that stored
        // wiring's loader has its own required propertyReference key (mediaId), mediaId must be seeded too,
        // or the create() would instead fail on an unfilled required input. No attributedSpecifications entry
        // is seeded, so the subsequent bind-element call is what adds the attribution asserted below.
        $layoutId = $this->createLayout([
            [
                'id' => 'img-1',
                'component' => 'Sw:Media:Image',
                'properties' => ['mediaId' => 'a-media-id'],
                'dataRequirements' => [
                    'media' => ['source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
                ],
            ],
        ]);

        $body = $this->mutate('bind-element', $layoutId, [
            'elementId' => 'img-1',
            'bindingSpecificationId' => 'core:Sw:Media:Image',
            'expectedVersion' => null,
        ]);

        static::assertSame(
            ['key' => 'media', 'source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            $body['layout'][0]['dataRequirements']['media']
        );
        static::assertSame(['media' => 'core:Sw:Media:Image'], $body['layout'][0]['attributedSpecifications']);

        // the reload asserts the exact persisted wiring, not just that some entry exists under 'media'
        $stored = $this->reload($layoutId)->getLayout()[0];
        static::assertSame(['media' => 'core:Sw:Media:Image'], $stored->attributedSpecifications);

        $requirement = $stored->dataRequirements['media'];
        static::assertSame('entity', $requirement->source);
        static::assertInstanceOf(EntityLoaderConfig::class, $requirement->config);
        static::assertSame('media', $requirement->config->entity);
        static::assertSame('mediaId', $requirement->config->property);
    }

    #[TestDox('auto-applies the core Sw:Media:Image default specification on a persisted replace to the image type carrying no bindingSpecificationId, committing the fill-applied wiring and attribution to storage')]
    public function testReplaceElementAutoAppliesCoreDefaultAndPersists(): void
    {
        // replace-element carries no bindingSpecificationId at all, so the media wiring and attribution can only
        // come from the new type's auto-applied default (the byType()/isDefault() fill path), not from any explicit
        // apply(): a wrong-result regression in that fill path leaves media unwired here. The stored element carries
        // the mediaId storage key up front (an undeclared key, first-class in storage), which ReplaceElement carries
        // onto the image, so the required media reference is filled and the persist gate passes.
        $layoutId = $this->createLayout([
            ['id' => 'el', 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => ['mediaId' => 'a-media-id']],
        ]);

        $body = $this->mutate('replace-element', $layoutId, [
            'elementId' => 'el',
            'newType' => 'Sw:Media:Image',
            'expectedVersion' => null,
        ]);

        $replaced = $body['layout'][0];
        static::assertSame(
            ['key' => 'media', 'source' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            $replaced['dataRequirements']['media']
        );
        static::assertSame(['media' => 'core:Sw:Media:Image'], $replaced['attributedSpecifications']);
        static::assertSame('a-media-id', $replaced['properties']['mediaId']);

        // the reload asserts the fill-applied wiring and attribution survived persistence: the write-boundary
        // AttributionReconciler keeps the attribution because the element's media wiring still matches the default's binding for that key.
        $stored = $this->reload($layoutId)->getLayout()[0];
        static::assertSame(['media' => 'core:Sw:Media:Image'], $stored->attributedSpecifications);

        $mediaId = $stored->property('mediaId');
        static::assertNotNull($mediaId);
        static::assertSame('a-media-id', $mediaId->asString());

        $requirement = $stored->dataRequirements['media'];
        static::assertSame('entity', $requirement->source);
        static::assertInstanceOf(EntityLoaderConfig::class, $requirement->config);
        static::assertSame('media', $requirement->config->entity);
        static::assertSame('mediaId', $requirement->config->property);
    }

    #[TestDox('rejects a persisted insert whose binding type does not match the inserted type with a 400, leaving the stored tree and updated_at untouched')]
    public function testInsertElementWithMismatchedBindingIsRejectedWithoutWriting(): void
    {
        $layoutId = $this->createLayout([$this->element('block-a', TestElementTypeLoader::RESOLVABLE)]);

        // bump updated_at with a successful op so the before/after comparison is a real timestamp, not null == null
        $this->mutate('insert-element', $layoutId, ['type' => TestElementTypeLoader::RESOLVABLE, 'expectedVersion' => null]);
        $before = $this->reload($layoutId)->getUpdatedAt();
        static::assertInstanceOf(\DateTimeInterface::class, $before);

        $this->request('insert-element', $layoutId, [
            'type' => 'Sw:Content:Text',
            'bindingSpecificationId' => 'core:Sw:Media:Image',
            'expectedVersion' => $this->apiUpdatedAt($layoutId),
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::BINDING_TYPE_MISMATCH, array_column($body['errors'], 'code'));

        // the op throws before PersistedLayoutMutator reaches update(): the stored tree keeps the two elements from
        // the bump and its updated_at is unchanged, so the mismatched insert persisted nothing
        static::assertCount(2, $this->layoutIds($layoutId));
        static::assertEquals($before, $this->reload($layoutId)->getUpdatedAt());
    }

    #[TestDox('rejects a persisted attach carrying an unknown style option via the DAL write\'s extra-fields violation, without ever reaching the diagnostics gate')]
    public function testAttachElementWithUnknownStyleOptionIsRejectedByDalConstraintNotDiagnostics(): void
    {
        $layoutId = $this->createLayout([$this->element('block-a', TestElementTypeLoader::RESOLVABLE)]);

        // StoredElementCodec::decodeStyle() is registry-free: an unknown style option rides through decode
        // verbatim (see Layout/Element/Style/AGENTS.md), so this element passes DraftLayoutDecoder::decodeOne()
        // unrejected and only meets a gate once PersistedLayoutMutator commits the mutated tree through the DAL.
        $incoming = $this->element('incoming', TestElementTypeLoader::RESOLVABLE);
        $incoming['style'] = ['definitely-not-a-style-option' => ['xs' => 'x']];

        $this->request('attach-element', $layoutId, [
            'element' => $incoming,
            'expectedVersion' => null,
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $codes = array_column($body['errors'], 'code');
        $details = array_column($body['errors'], 'detail');

        // (A) rejection is named specifically as the style constraint's extra-fields violation (Symfony
        // Collection::allowExtraFields: false on Layout/Codec/StoredTreeConstraints::styleConstraints()),
        // not merely "some 400 happened".
        static::assertContains(Collection::NO_SUCH_FIELD_ERROR, $codes, (string) $response->getContent());
        static::assertContains('This field was not expected.', $details, (string) $response->getContent());

        // (B) the diagnostics gate never ran: no diagnostics report in the body at all, and specifically no
        // unknown_style_option violation anywhere in the error list — the precedence pin, not a duplicate of
        // the DAL-write-level rejection already covered by ElementStylePersistenceTest::testRejectsUnknownStyleOption.
        static::assertArrayNotHasKey('diagnostics', $body);
        static::assertNotContains(ViolationCode::UnknownStyleOption->value, $codes);

        // nothing was written: the attach never landed
        static::assertSame(['block-a'], $this->layoutIds($layoutId));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function mutate(string $action, string $layoutId, array $payload): array
    {
        $this->request($action, $layoutId, $payload);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(string $action, string $layoutId, array $payload): void
    {
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/' . $action, $payload);
    }

    /**
     * @param list<array<string, mixed>> $tree
     */
    private function createLayout(array $tree): string
    {
        $id = $this->ids->get('layout');
        $this->repository()->create([[
            'id' => $id,
            'name' => 'mutation-' . $id,
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => $tree,
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function element(string $id, string $component): array
    {
        return ['id' => $id, 'component' => $component, 'properties' => []];
    }

    private function apiUpdatedAt(string $layoutId): string
    {
        $this->getBrowser()->request('GET', '/api/content-layout/' . $layoutId, [], [], ['HTTP_ACCEPT' => 'application/json']);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $updatedAt = $body['data']['updatedAt'] ?? null;
        static::assertIsString($updatedAt, 'Admin API did not return an updatedAt for the layout: ' . (string) $response->getContent());

        return $updatedAt;
    }

    private function reload(string $layoutId): ContentLayoutEntity
    {
        $layout = $this->repository()->search(new Criteria([$layoutId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        return $layout;
    }

    /**
     * @return list<string>
     */
    private function layoutIds(string $layoutId): array
    {
        return array_map(static fn (StoredElement $element): string => $element->id, $this->reload($layoutId)->getLayout());
    }

    private function bindCategory(string $layoutId): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->ids->get('category');

        $categoryRepository = $this->getContainer()->get('category.repository');
        static::assertInstanceOf(EntityRepository::class, $categoryRepository);
        $categoryRepository->create([['id' => $categoryId, 'name' => 'mutation-bound-category']], $context);

        $assignmentRepository = $this->getContainer()->get('category_content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $assignmentRepository);
        $assignmentRepository->create([['id' => $this->ids->get('assignment'), 'categoryId' => $categoryId, 'contentLayoutId' => $layoutId]], $context);
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
}
