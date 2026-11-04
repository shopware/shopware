<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutRevisionController;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
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
#[CoversClass(ContentLayoutRevisionController::class)]
class ContentLayoutRevisionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const BASE_URL = '/api/_action/content-system/layout/';

    #[TestDox('a draft is created, saved, listed in the history and published through the routes')]
    public function testDraftRoundTrip(): void
    {
        $layoutId = $this->createLayout();

        $branch = $this->json('POST', $layoutId . '/branches', ['name' => 'My draft'])['branch'];
        static::assertSame('My draft', $branch['name']);

        $saved = $this->json('POST', $layoutId . '/branches/' . $branch['id'] . '/revisions', [
            'layout' => [$this->element('draft-a'), $this->element('draft-b')],
            'expectedHead' => $branch['head'],
        ]);
        static::assertSame([$branch['id']], $saved['revision']['branches']);
        static::assertCount(2, $saved['layout']);

        $detail = $this->json('GET', $layoutId . '/branches/' . $branch['id']);
        static::assertSame($saved['revision']['id'], $detail['branch']['head']);
        static::assertCount(2, $detail['layout']);

        $history = $this->json('GET', $layoutId . '/revisions');
        static::assertSame($saved['revision']['id'], $history['revisions'][0]['id']);
        static::assertTrue($history['revisions'][1]['published']);

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/publish', [
            'revisionId' => $saved['revision']['id'],
            'deleteBranchId' => $branch['id'],
        ]);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        static::assertSame(['draft-a', 'draft-b'], $this->liveIds($layoutId));
        static::assertSame([], $this->json('GET', $layoutId . '/branches')['branches']);
    }

    #[TestDox('publishing against a live state the client has not seen answers 409 without writing')]
    public function testPublishWithAStaleExpectedPublishedConflicts(): void
    {
        $layoutId = $this->createLayout();
        $published = $this->json('GET', $layoutId . '/revisions')['published'];
        $branch = $this->json('POST', $layoutId . '/branches', [])['branch'];
        $saved = $this->json('POST', $layoutId . '/branches/' . $branch['id'] . '/revisions', [
            'layout' => [$this->element('draft')],
            'expectedHead' => $branch['head'],
        ]);

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/publish', [
            'revisionId' => $saved['revision']['id'],
            'expectedPublished' => Uuid::randomHex(),
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        static::assertStringContainsString(ContentSystemException::LAYOUT_PUBLISH_CONFLICT, (string) $response->getContent());
        static::assertSame(['live'], $this->liveIds($layoutId));
        static::assertSame($published, $this->json('GET', $layoutId . '/revisions')['published']);
    }

    #[TestDox('an unknown branch answers 404')]
    public function testUnknownBranchIsNotFound(): void
    {
        $layoutId = $this->createLayout();

        $this->getBrowser()->request('GET', self::BASE_URL . $layoutId . '/branches/' . Uuid::randomHex());

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertStringContainsString(ContentSystemException::CONTENT_LAYOUT_BRANCH_NOT_FOUND, (string) $response->getContent());
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, array $payload = []): array
    {
        $this->getBrowser()->jsonRequest($method, self::BASE_URL . $path, $payload);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function createLayout(): string
    {
        $id = Uuid::randomHex();

        $this->repository()->create([[
            'id' => $id,
            'name' => 'revision-' . $id,
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [$this->element('live')],
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @return array{id: string, component: string, properties: array<string, mixed>}
     */
    private function element(string $id): array
    {
        return ['id' => $id, 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []];
    }

    /**
     * @return list<string>
     */
    private function liveIds(string $layoutId): array
    {
        $layout = $this->repository()->search(new Criteria([$layoutId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        return array_map(static fn (StoredElement $element): string => $element->id, $layout->getLayout());
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        return static::getContainer()->get('content_layout.repository');
    }
}
