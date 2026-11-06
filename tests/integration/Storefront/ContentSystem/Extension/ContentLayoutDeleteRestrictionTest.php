<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\ContentSystem\Extension;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Shopware\Storefront\ContentSystem\Extension\ContentLayoutExtension;

/**
 * Proves the header/footer associations contributed by {@see ContentLayoutExtension}
 * carry the RestrictDelete flag, so a layout bound to a header or footer cannot be deleted out from under its binding.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutDeleteRestrictionTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('rejects deleting a content layout that is bound to a header assignment')]
    public function testRejectsDeletingHeaderBoundLayout(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout('header', $context);
        $this->headerRepository()->create([['id' => Uuid::randomHex(), 'contentLayoutId' => $layoutId]], $context);

        try {
            $this->layoutRepository()->delete([['id' => $layoutId]], $context);
            static::fail('Expected the RestrictDelete flag to block deleting a header-bound layout.');
        } catch (RestrictDeleteViolationException $exception) {
            static::assertStringContainsString('header_content_layout', $exception->getMessage());
        }

        static::assertSame($layoutId, $this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
    }

    #[TestDox('rejects deleting a content layout that is bound to a footer assignment')]
    public function testRejectsDeletingFooterBoundLayout(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout('footer', $context);
        $this->footerRepository()->create([['id' => Uuid::randomHex(), 'contentLayoutId' => $layoutId]], $context);

        try {
            $this->layoutRepository()->delete([['id' => $layoutId]], $context);
            static::fail('Expected the RestrictDelete flag to block deleting a footer-bound layout.');
        } catch (RestrictDeleteViolationException $exception) {
            static::assertStringContainsString('footer_content_layout', $exception->getMessage());
        }

        static::assertSame($layoutId, $this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
    }

    #[TestDox('rejects deleting a header-bound content layout via the Sync API')]
    public function testRejectsDeletingHeaderBoundLayoutViaSyncApi(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout('header', $context);
        $this->headerRepository()->create([['id' => Uuid::randomHex(), 'contentLayoutId' => $layoutId]], $context);

        $operations = [
            new SyncOperation('delete-layout', ContentLayoutDefinition::ENTITY_NAME, SyncOperation::ACTION_DELETE, [['id' => $layoutId]]),
        ];

        try {
            $this->syncService()->sync($operations, $context, new SyncBehavior());
            static::fail('Expected the RestrictDelete flag to block deleting a header-bound layout via the Sync API.');
        } catch (RestrictDeleteViolationException $exception) {
            static::assertStringContainsString('header_content_layout', $exception->getMessage());
        }

        static::assertSame($layoutId, $this->layoutRepository()->searchIds(new Criteria([$layoutId]), $context)->firstId());
    }

    private function createLayout(string $rootSource, Context $context): string
    {
        $id = Uuid::randomHex();
        $this->layoutRepository()->create([[
            'id' => $id,
            'name' => 'delete-restriction-layout',
            'version' => '1.0.0',
            'rootSource' => $rootSource,
            'layout' => [
                ['id' => Uuid::randomHex(), 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []],
            ],
        ]], $context);

        return $id;
    }

    private function syncService(): SyncService
    {
        $service = $this->getContainer()->get(SyncService::class);
        static::assertInstanceOf(SyncService::class, $service);

        return $service;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function layoutRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function headerRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('header_content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function footerRepository(): EntityRepository
    {
        $repository = $this->getContainer()->get('footer_content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
