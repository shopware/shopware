<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The DAL fixture writes the content-layout integration tests share: a container-resolved repository, an active
 * category, a `content_layout` row, and the assignment binding the layout to that category.
 *
 * Every id, name and tree stays at the call site, so the fixture axis a test varies is readable in the test file
 * rather than here.
 *
 * The using class must supply `getContainer()`, which `KernelTestBehaviour` provides.
 *
 * @internal
 */
#[Package('framework')]
trait ContentLayoutFixtureBehaviour
{
    abstract protected static function getContainer(): ContainerInterface;

    private function createTestCategory(string $id, string $name): void
    {
        $this->repository('category.repository')->create([[
            'id' => $id,
            'name' => $name,
            'active' => true,
        ]], Context::createDefaultContext());
    }

    /**
     * @param list<array<string, mixed>> $tree
     */
    private function persistContentLayout(string $id, string $name, string $version, string $rootSource, array $tree): void
    {
        $this->repository('content_layout.repository')->create([[
            'id' => $id,
            'name' => $name,
            'version' => $version,
            'rootSource' => $rootSource,
            'layout' => $tree,
        ]], Context::createDefaultContext());
    }

    private function assignLayoutToCategory(string $id, string $categoryId, string $layoutId): void
    {
        $this->repository('category_content_layout.repository')->create([[
            'id' => $id,
            'categoryId' => $categoryId,
            'salesChannelId' => null,
            'contentLayoutId' => $layoutId,
        ]], Context::createDefaultContext());
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $serviceId): EntityRepository
    {
        $repository = static::getContainer()->get($serviceId);
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
