<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ContentLayoutMutationControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const BASE_URL = '/api/_action/content-system/layout/';

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
        static::assertStringContainsString('not deterministically resolvable', (string) $response->getContent());
        static::assertSame(TestElementTypeLoader::RESOLVABLE, $this->reload($layoutId)->getLayout()[0]->getComponent());
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
        static::assertSame([], $this->reload($layoutId)->getLayout()[0]->getSlots());
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
        $this->request('remove-element', Uuid::randomHex(), ['elementId' => 'block-a', 'expectedVersion' => null]);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->getBrowser()->getResponse()->getStatusCode());
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
        $id = Uuid::randomHex();
        $this->repository()->create([[
            'id' => $id,
            'name' => 'mutation-' . $id,
            'version' => '1.0.0',
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
        $layout = $this->repository()->search(new Criteria([$layoutId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        return $layout;
    }

    /**
     * @return list<string>
     */
    private function layoutIds(string $layoutId): array
    {
        return array_map(static fn (object $element): string => $element->getId(), $this->reload($layoutId)->getLayout());
    }

    private function bindCategory(string $layoutId): void
    {
        $context = Context::createDefaultContext();
        $categoryId = Uuid::randomHex();

        $categoryRepository = $this->getContainer()->get('category.repository');
        static::assertInstanceOf(EntityRepository::class, $categoryRepository);
        $categoryRepository->create([['id' => $categoryId, 'name' => 'mutation-bound-category']], $context);

        $assignmentRepository = $this->getContainer()->get('category_content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $assignmentRepository);
        $assignmentRepository->create([['id' => Uuid::randomHex(), 'categoryId' => $categoryId, 'contentLayoutId' => $layoutId]], $context);
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
