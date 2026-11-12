<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Workspace;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\ContentSystem\Workspace\ContentWorkspaceDefinition;
use Shopware\Core\Framework\ContentSystem\Workspace\ContentWorkspaceEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * @internal
 */
#[Package('framework')]
class ContentWorkspaceTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testWorkspaceIsSharedAndCanBeRenamedWithoutChangingConfiguration(): void
    {
        $repository = $this->getContainer()->get('content_workspace.repository');
        $id = Uuid::randomHex();
        $configuration = ['frames' => [], 'previewSalesChannelId' => null, 'canvas' => ['panX' => 25, 'panY' => -10, 'zoom' => 0.5]];
        $repository->create([['id' => $id, 'name' => 'Autumn refresh', 'configuration' => $configuration]], Context::createDefaultContext());
        $otherUserContext = Context::createDefaultContext();
        $workspace = $repository->search(new Criteria([$id]), $otherUserContext)->getEntities()->first();
        static::assertInstanceOf(ContentWorkspaceEntity::class, $workspace);
        static::assertEquals($configuration, $workspace->getConfiguration());
        $repository->update([['id' => $id, 'name' => 'Winter refresh']], $otherUserContext);
        $workspace = $repository->search(new Criteria([$id]), $otherUserContext)->getEntities()->first();
        static::assertInstanceOf(ContentWorkspaceEntity::class, $workspace);
        static::assertSame('Winter refresh', $workspace->getName());
        static::assertEquals($configuration, $workspace->getConfiguration());
        $repository->delete([['id' => $id]], $otherUserContext);
        static::assertSame(0, $repository->searchIds(new Criteria([$id]), $otherUserContext)->getTotal());
    }

    public function testWorkspaceWriteRejectsEmbeddedLayoutContent(): void
    {
        $repository = $this->getContainer()->get('content_workspace.repository');
        $this->expectException(WriteException::class);
        $repository->create([[
            'name' => 'Invalid',
            'configuration' => ['frames' => [], 'layout' => [], 'previewSalesChannelId' => null, 'canvas' => ['panX' => 0, 'panY' => 0, 'zoom' => 1]],
        ]], Context::createDefaultContext());
    }

    public function testLimitedUsersCanShareWorkspacesAndCreatorDeletionPreservesThem(): void
    {
        $permissions = ['content_workspace:read', 'content_workspace:create', 'content_workspace:update', 'content_workspace:delete'];
        $browser = $this->getBrowser(permissions: $permissions);
        $id = Uuid::randomHex();
        $browser->jsonRequest('POST', '/api/content-workspace', [
            'id' => $id, 'name' => 'Shared workspace',
            'configuration' => ['frames' => [], 'previewSalesChannelId' => null, 'canvas' => ['panX' => 0, 'panY' => 0, 'zoom' => 1]],
        ]);
        static::assertSame(204, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $repository = $this->getContainer()->get('content_workspace.repository');
        $workspace = $repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ContentWorkspaceEntity::class, $workspace);
        $creatorId = $workspace->getCreatedById();
        static::assertNotNull($creatorId);

        $userRepository = $this->getContainer()->get('user.repository');
        $creator = $userRepository->search(
            (new Criteria([$creatorId]))->addAssociation('createdContentWorkspaces'),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $creator);
        $createdWorkspaces = $creator->getCreatedContentWorkspaces();
        static::assertNotNull($createdWorkspaces);
        static::assertSame([$id], array_values($createdWorkspaces->getIds()));

        $this->authorizeBrowser($browser, aclPermissions: [...$permissions, 'content_layout:read']);
        $browser->jsonRequest('POST', '/api/search/content-workspace', [
            'ids' => [$id],
        ]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $browser->jsonRequest('PATCH', '/api/content-workspace/' . $id, ['name' => 'Renamed by another user']);
        static::assertSame(204, $browser->getResponse()->getStatusCode());
        $userRepository->delete([['id' => $creatorId]], Context::createDefaultContext());
        $workspace = $repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ContentWorkspaceEntity::class, $workspace);
        static::assertNull($workspace->getCreatedById());
        static::assertSame('Renamed by another user', $workspace->getName());
    }

    public function testApiRequiresWorkspacePrivileges(): void
    {
        $browser = $this->getBrowser(permissions: []);
        $browser->jsonRequest('POST', '/api/search/content-workspace', []);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('POST', '/api/content-workspace', ['name' => 'Forbidden', 'configuration' => ['frames' => [], 'previewSalesChannelId' => null, 'canvas' => ['panX' => 0, 'panY' => 0, 'zoom' => 1]]]);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testWorkspaceViewerCannotReadUsersOrPrivateMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $this->getContainer()->get('media.repository')->create([[
            'id' => $mediaId,
            'private' => true,
        ]], Context::createDefaultContext());
        $browser = $this->getBrowser(permissions: ['content_workspace:read']);

        $browser->jsonRequest('POST', '/api/search/content-workspace', []);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $browser->jsonRequest('POST', '/api/search/user', []);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('POST', '/api/search/media', []);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('GET', '/api/_action/media/' . $mediaId . '/download');
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('POST', '/api/search/content-workspace', [
            'associations' => ['createdBy' => ['associations' => ['avatarMedia' => []]]],
        ]);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testWorkspaceFieldsAreOnlyExposedToAdminApi(): void
    {
        $definition = $this->getContainer()->get(ContentWorkspaceDefinition::class);
        foreach ($definition->getFields() as $field) {
            $flag = $field->getFlag(ApiAware::class);
            static::assertInstanceOf(ApiAware::class, $flag, $field->getPropertyName());
            static::assertTrue($flag->isSourceAllowed(AdminApiSource::class), $field->getPropertyName());
            static::assertFalse($flag->isSourceAllowed(SalesChannelApiSource::class), $field->getPropertyName());
        }
    }

    public function testDeletingWorkspaceKeepsReferencedLayout(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = Uuid::randomHex();
        $layoutRepository = $this->getContainer()->get('content_layout.repository');
        $layoutRepository->create([['id' => $layoutId, 'name' => 'Layout', 'version' => '1.0.0', 'rootSource' => 'none', 'layout' => [['id' => Uuid::randomHex(), 'component' => TestElementTypeLoader::DEFAULTED_PRIMITIVE, 'properties' => []]]]], $context);
        $workspaceId = Uuid::randomHex();
        $repository = $this->getContainer()->get('content_workspace.repository');
        $repository->create([[
            'id' => $workspaceId, 'name' => 'Workspace',
            'configuration' => [
                'frames' => [['layoutId' => $layoutId, 'x' => 0, 'y' => 0, 'width' => 700, 'height' => 900, 'viewport' => 'custom', 'previewEntityId' => null]],
                'previewSalesChannelId' => null, 'canvas' => ['panX' => 0, 'panY' => 0, 'zoom' => 1],
            ],
        ]], $context);
        $repository->delete([['id' => $workspaceId]], $context);
        static::assertSame(1, $layoutRepository->searchIds(new Criteria([$layoutId]), $context)->getTotal());
    }
}
