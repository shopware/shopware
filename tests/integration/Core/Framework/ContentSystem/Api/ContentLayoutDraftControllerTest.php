<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
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
        static::assertIsString($draft['createdById']);
        static::assertSame($draft['baseRevisionId'], $draft['liveAtOpenRevisionId']);

        $edited = $this->json('POST', $layoutId . '/remove-element', [
            'elementId' => 'other',
            'draftId' => $draft['id'],
            'expectedHeadRevisionId' => $draft['headRevisionId'],
        ]);
        static::assertSame($draft['id'], $edited['draft']['id']);
        static::assertSame(['live'], array_column($edited['layout'], 'id'));

        static::assertSame([$edited['draft']], $this->json('GET', $layoutId . '/drafts')['drafts']);

        $detail = $this->json('GET', $layoutId . '/drafts/' . $draft['id']);
        static::assertSame($edited['draft']['headRevisionId'], $detail['draft']['headRevisionId']);
        static::assertSame(['live'], array_column($detail['layout'], 'id'));
        static::assertSame(['live', 'other'], $this->liveIds($layoutId));

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/publish-draft', [
            'draftId' => $draft['id'],
            'expectedHeadRevisionId' => $edited['draft']['headRevisionId'],
        ]);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        static::assertSame(['live'], $this->liveIds($layoutId));
        static::assertSame([], $this->json('GET', $layoutId . '/drafts')['drafts']);
    }

    #[TestDox('the published revisions list the rollback targets, and a draft opened from one restores it')]
    public function testPublishedRevisionsAreRollbackTargets(): void
    {
        $layoutId = $this->createLayout();
        $draft = $this->json('POST', $layoutId . '/create-draft')['draft'];
        $edited = $this->json('POST', $layoutId . '/remove-element', [
            'elementId' => 'other',
            'draftId' => $draft['id'],
            'expectedHeadRevisionId' => $draft['headRevisionId'],
        ]);
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/publish-draft', [
            'draftId' => $draft['id'],
            'expectedHeadRevisionId' => $edited['draft']['headRevisionId'],
        ]);

        $revisions = $this->json('GET', $layoutId . '/published-revisions')['revisions'];
        static::assertSame([$edited['draft']['headRevisionId'], $draft['baseRevisionId']], array_column($revisions, 'id'));
        static::assertSame([true, false], array_column($revisions, 'live'));
        static::assertSame(
            ['id', 'live', 'createdAt', 'createdById', 'publishedAt', 'publishedById', 'lastLiveAt'],
            array_keys($revisions[0]),
        );

        $rollback = $this->json('POST', $layoutId . '/create-draft', ['fromRevisionId' => $revisions[1]['id']])['draft'];
        static::assertSame($revisions[1]['id'], $rollback['baseRevisionId']);
        static::assertSame($revisions[0]['id'], $rollback['liveAtOpenRevisionId']);
        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/publish-draft', [
            'draftId' => $rollback['id'],
            'expectedHeadRevisionId' => $rollback['headRevisionId'],
        ]);

        static::assertSame(['live', 'other'], $this->liveIds($layoutId));
        static::assertSame($draft['baseRevisionId'], $this->json('GET', $layoutId . '/published-revisions')['revisions'][0]['id']);
    }

    #[TestDox('the published revisions of an unknown layout answer 404')]
    public function testPublishedRevisionsOfAnUnknownLayoutAreNotFound(): void
    {
        $this->getBrowser()->jsonRequest('GET', self::BASE_URL . Uuid::randomHex() . '/published-revisions');

        static::assertSame([ContentSystemException::CONTENT_LAYOUT_NOT_FOUND], $this->errorCodes(Response::HTTP_NOT_FOUND));
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
            'expectedHeadRevisionId' => $draft['headRevisionId'],
        ]);

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/publish-draft', [
            'draftId' => $draft['id'],
            'expectedHeadRevisionId' => $draft['headRevisionId'],
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

        static::assertSame(['This value should be of type string.'], $this->errorDetails(Response::HTTP_BAD_REQUEST));
        static::assertSame(['live', 'other'], $this->liveIds($layoutId));
    }

    #[TestDox('a body field the draft route does not declare is a client error, not silently ignored')]
    public function testUnknownBodyFieldIsABadRequest(): void
    {
        $layoutId = $this->createLayout();

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/create-draft', ['fromRevison' => Uuid::randomHex()]);

        static::assertSame([ContentSystemException::UNKNOWN_REQUEST_FIELD], $this->errorCodes(Response::HTTP_BAD_REQUEST));
        static::assertSame([], $this->json('GET', $layoutId . '/drafts')['drafts']);
    }

    #[TestDox('a non-string fromRevisionId is a client error instead of a draft of the live revision')]
    public function testNonStringFromRevisionIdIsABadRequest(): void
    {
        $layoutId = $this->createLayout();

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/create-draft', ['fromRevisionId' => 5]);

        static::assertSame(['This value should be of type null|string.'], $this->errorDetails(Response::HTTP_BAD_REQUEST));
        static::assertSame([], $this->json('GET', $layoutId . '/drafts')['drafts']);
    }

    #[TestDox('an unknown fromRevisionId answers 404')]
    public function testUnknownFromRevisionIdIsNotFound(): void
    {
        $layoutId = $this->createLayout();

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/create-draft', ['fromRevisionId' => Uuid::randomHex()]);

        static::assertSame([ContentSystemException::CONTENT_LAYOUT_REVISION_NOT_FOUND], $this->errorCodes(Response::HTTP_NOT_FOUND));
    }

    #[DataProvider('invalidDraftNames')]
    #[TestDox('a draft name the draft table cannot hold meaningfully is a client error')]
    public function testInvalidDraftNameIsABadRequest(string $name): void
    {
        $layoutId = $this->createLayout();

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/create-draft', ['name' => $name]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertSame([], $this->json('GET', $layoutId . '/drafts')['drafts']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDraftNames(): iterable
    {
        yield 'an empty name is blank' => [''];
        yield 'a whitespace-only name is blank after trimming' => ['   '];
        yield 'a name longer than the 255-character column' => [str_repeat('a', 256)];
    }

    #[DataProvider('writingRoutes')]
    #[TestDox('$_dataName requires the update privilege and changes nothing without it')]
    public function testWritingRouteRequiresTheUpdatePrivilege(string $route, bool $sendsDraft): void
    {
        $layoutId = $this->createLayout();
        $draft = $this->json('POST', $layoutId . '/create-draft')['draft'];
        $this->actAs(['content_layout:read']);

        $this->getBrowser()->jsonRequest('POST', self::BASE_URL . $layoutId . '/' . $route, $sendsDraft ? [
            'draftId' => $draft['id'],
            'expectedHeadRevisionId' => $draft['headRevisionId'],
        ] : []);

        $this->assertMissingPrivilege('content_layout:update');
        static::assertSame([$draft], $this->json('GET', $layoutId . '/drafts')['drafts']);
        static::assertSame(['live', 'other'], $this->liveIds($layoutId));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function writingRoutes(): iterable
    {
        yield 'creating a draft' => ['create-draft', false];
        yield 'discarding a draft' => ['discard-draft', true];
        yield 'publishing a draft' => ['publish-draft', true];
    }

    #[TestDox('the drafts, a draft and the published revisions can be read with the read privilege alone')]
    public function testReadingRoutesNeedOnlyTheReadPrivilege(): void
    {
        $layoutId = $this->createLayout();
        $draft = $this->json('POST', $layoutId . '/create-draft')['draft'];
        $this->actAs(['content_layout:read']);

        static::assertSame([$draft], $this->json('GET', $layoutId . '/drafts')['drafts']);
        static::assertSame($draft['headRevisionId'], $this->json('GET', $layoutId . '/drafts/' . $draft['id'])['draft']['headRevisionId']);
        static::assertSame([$draft['baseRevisionId']], array_column($this->json('GET', $layoutId . '/published-revisions')['revisions'], 'id'));
    }

    #[DataProvider('readingRoutes')]
    #[TestDox('reading $_dataName requires the read privilege')]
    public function testReadingRouteRequiresTheReadPrivilege(string $path): void
    {
        $layoutId = $this->createLayout();
        $draft = $this->json('POST', $layoutId . '/create-draft')['draft'];
        $this->actAs([]);

        $this->getBrowser()->jsonRequest('GET', self::BASE_URL . $layoutId . str_replace('{draftId}', $draft['id'], $path));

        $this->assertMissingPrivilege('content_layout:read');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function readingRoutes(): iterable
    {
        yield 'the drafts' => ['/drafts'];
        yield 'a draft' => ['/drafts/{draftId}'];
        yield 'the published revisions' => ['/published-revisions'];
    }

    /**
     * @return list<string>
     */
    private function errorCodes(int $status): array
    {
        return array_column($this->errors($status), 'code');
    }

    /**
     * @return list<string>
     */
    private function errorDetails(int $status): array
    {
        return array_column($this->errors($status), 'detail');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function errors(int $status): array
    {
        $response = $this->getBrowser()->getResponse();
        static::assertSame($status, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'];
    }

    /**
     * Replaces the default admin browser with one authenticated as a non-admin user holding exactly $privileges, so
     * every later request in the test runs through the route ACL.
     *
     * @param list<string> $privileges
     */
    private function actAs(array $privileges): void
    {
        $this->resetBrowser();
        $this->getBrowser(true, [], $privileges);
    }

    private function assertMissingPrivilege(string $privilege): void
    {
        $error = $this->errors(Response::HTTP_FORBIDDEN)[0];
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, $error['code']);

        $detail = json_decode($error['detail'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([$privilege], $detail['missingPrivileges']);
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
