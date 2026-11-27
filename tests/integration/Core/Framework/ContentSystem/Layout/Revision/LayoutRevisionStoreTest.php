<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Revision;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionStore;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * @internal
 */
#[Package('framework')]
class LayoutRevisionStoreTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    #[TestDox('the tree of a revision is only found through the layout it belongs to')]
    public function testTreeOfAnotherLayoutsRevisionIsNotFound(): void
    {
        $layoutId = $this->createLayout();
        $otherLayoutId = $this->createLayout();
        $otherRevision = static::getContainer()->get(LayoutRevisionService::class)
            ->createDraft($otherLayoutId, Context::createDefaultContext())
            ->head;

        $store = static::getContainer()->get(LayoutRevisionStore::class);

        static::assertNotNull($store->tree($otherLayoutId, $otherRevision));
        static::assertNull($store->tree($layoutId, $otherRevision));
    }

    private function createLayout(): string
    {
        $id = Uuid::randomHex();

        $this->repository()->create([[
            'id' => $id,
            'name' => 'revision-store-' . $id,
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [['id' => 'element', 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []]],
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        return static::getContainer()->get('content_layout.repository');
    }
}
