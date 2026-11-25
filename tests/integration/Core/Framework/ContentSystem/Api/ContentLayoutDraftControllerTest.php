<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
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
class ContentLayoutDraftControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const BASE_URL = '/api/_action/content-system/layout/';

    #[TestDox('a draft is created, edited through a mutation route, listed, read and published')]
    public function testDraftRoundTrip(): void
    {
        $layoutId = $this->createLayout();

        $draft = $this->json('POST', $layoutId . '/create-draft', ['name' => 'My draft'])['draft'];
        static::assertSame('My draft', $draft['name']);

        $edited = $this->json('POST', $layoutId . '/remove-element', [
            'elementId' => 'other',
            'draftId' => $draft['id'],
            'expectedHead' => $draft['head'],
        ]);
        static::assertSame($draft['id'], $edited['draft']['id']);
        static::assertSame(['live'], array_column($edited['layout'], 'id'));

        static::assertSame([$edited['draft']], $this->json('GET', $layoutId . '/drafts')['drafts']);

        $detail = $this->json('GET', $layoutId . '/drafts/' . $draft['id']);
        static::assertSame($edited['draft']['head'], $detail['draft']['head']);
        static::assertSame(['live'], array_column($detail['layout'], 'id'));
        static::assertSame(['live', 'other'], $this->liveIds($layoutId));

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/publish-draft', [
            'draftId' => $draft['id'],
            'expectedHead' => $edited['draft']['head'],
        ]);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        static::assertSame(['live'], $this->liveIds($layoutId));
        static::assertSame([], $this->json('GET', $layoutId . '/drafts')['drafts']);
    }

    #[TestDox('a discarded draft is gone and the live layout is untouched')]
    public function testDiscardDraft(): void
    {
        $layoutId = $this->createLayout();
        $draft = $this->json('POST', $layoutId . '/create-draft')['draft'];

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/discard-draft', ['draftId' => $draft['id']]);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        static::assertSame([], $this->json('GET', $layoutId . '/drafts')['drafts']);
        static::assertSame(['live', 'other'], $this->liveIds($layoutId));
    }

    #[TestDox('publishing a head the client has not seen answers 409 without writing')]
    public function testPublishWithAStaleHeadConflicts(): void
    {
        $layoutId = $this->createLayout();
        $draft = $this->json('POST', $layoutId . '/create-draft')['draft'];
        $this->json('POST', $layoutId . '/remove-element', [
            'elementId' => 'other',
            'draftId' => $draft['id'],
            'expectedHead' => $draft['head'],
        ]);

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/publish-draft', [
            'draftId' => $draft['id'],
            'expectedHead' => $draft['head'],
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        static::assertStringContainsString(ContentSystemException::LAYOUT_VERSION_CONFLICT, (string) $response->getContent());
        static::assertSame(['live', 'other'], $this->liveIds($layoutId));
    }

    #[TestDox('an unknown draft answers 404')]
    public function testUnknownDraftIsNotFound(): void
    {
        $layoutId = $this->createLayout();

        $this->getBrowser()->request('GET', self::BASE_URL . $layoutId . '/drafts/' . Uuid::randomHex());

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertStringContainsString(ContentSystemException::CONTENT_LAYOUT_DRAFT_NOT_FOUND, (string) $response->getContent());
    }

    #[TestDox('publishing without the head token is a client error')]
    public function testPublishWithoutExpectedHeadIsABadRequest(): void
    {
        $layoutId = $this->createLayout();
        $draft = $this->json('POST', $layoutId . '/create-draft')['draft'];

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/publish-draft', ['draftId' => $draft['id']]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString(ContentSystemException::MISSING_REQUEST_FIELD, (string) $response->getContent());
        static::assertSame(['live', 'other'], $this->liveIds($layoutId));
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
            'name' => 'draft-' . $id,
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [$this->element('live'), $this->element('other')],
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
