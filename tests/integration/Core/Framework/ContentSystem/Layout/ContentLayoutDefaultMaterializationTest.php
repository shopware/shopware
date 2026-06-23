<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * @internal
 */
#[Package('framework')]
class ContentLayoutDefaultMaterializationTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('materializes a type primitive default into a content layout written by a plain DAL create, so the stored tree is resolvable')]
    public function testPlainDalCreateMaterializesPrimitiveDefaults(): void
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        // A plain DAL create with the raw-array payload the Admin / Sync API and fixtures build, bypassing the
        // mutation ops: the element carries no headline, so only the write-boundary materializer can seed it.
        $this->repository()->create([[
            'id' => $id,
            'name' => 'materializer-test',
            'version' => '1.0.0',
            'layout' => [['id' => Uuid::randomHex(), 'component' => TestElementTypeLoader::DEFAULTED_PRIMITIVE, 'properties' => []]],
        ]], $context);

        $layout = $this->repository()->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        $tree = $layout->getLayout();
        static::assertSame('Seeded headline', $tree[0]->getProperty('headline'));
        static::assertTrue($this->diagnostics()->analyze($tree, [])->report->isResolvable());
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

    private function diagnostics(): LayoutDiagnostics
    {
        $diagnostics = $this->getContainer()->get(LayoutDiagnostics::class);
        static::assertInstanceOf(LayoutDiagnostics::class, $diagnostics);

        return $diagnostics;
    }
}
