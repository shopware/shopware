<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\LandingPage\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class LandingPageRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<LandingPageCollection>
     */
    private EntityRepository $repository;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepo;

    /**
     * @var EntityRepository<CmsPageCollection>
     */
    private EntityRepository $cmsPageRepo;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('landing_page.repository');
        $this->salesChannelRepo = static::getContainer()->get('sales_channel.repository');
        $this->cmsPageRepo = static::getContainer()->get('cms_page.repository');
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testCreateLandingPage(): void
    {
        $this->createLandingPage(Uuid::randomHex());
    }

    public function testUpdateLandingPage(): void
    {
        $uuid = Uuid::randomHex();
        $this->createLandingPage($uuid);

        $update = [
            'id' => $uuid,
            'name' => 'Another title',
        ];

        $this->repository->update([
            $update,
        ], Context::createDefaultContext());

        $result = $this->connection->fetchAllAssociative(
            'SELECT * FROM landing_page_translation WHERE landing_page_id = :id',
            ['id' => Uuid::fromHexToBytes($uuid)]
        );

        static::assertCount(1, $result);
        static::assertSame($update['name'], $result[0]['name']);
    }

    public function testDeleteLandingPage(): void
    {
        $uuid = Uuid::randomHex();
        $this->createLandingPage($uuid);

        $this->repository->delete([[
            'id' => $uuid,
        ]], Context::createDefaultContext());

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM landing_page WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($uuid)]
        );

        static::assertCount(0, $exists);
    }

    #[DataProvider('seoFieldProvider')]
    public function testSeoFieldLongerThan255CharactersIsRejected(string $field): void
    {
        $id = Uuid::randomHex();

        try {
            $this->repository->create([
                ['id' => $id, 'name' => 'test', 'url' => 'test', 'salesChannels' => [['id' => TestDefaults::SALES_CHANNEL]], $field => str_repeat('a', 256)],
            ], Context::createDefaultContext());

            static::fail('A value longer than 255 characters must be rejected');
        } catch (WriteException $e) {
            $errors = iterator_to_array($e->getErrors());
            static::assertCount(1, $errors);
            static::assertStringEndsWith('/' . $field, $errors[0]['source']['pointer']);
        }

        $this->repository->create([
            ['id' => $id, 'name' => 'test', 'url' => 'test', 'salesChannels' => [['id' => TestDefaults::SALES_CHANNEL]], $field => str_repeat('a', 255)],
        ], Context::createDefaultContext());

        $entity = $this->repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($entity);
        static::assertSame(str_repeat('a', 255), $entity->get($field));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function seoFieldProvider(): iterable
    {
        yield 'meta title' => ['metaTitle'];
        yield 'meta description' => ['metaDescription'];
        yield 'keywords' => ['keywords'];
    }

    private function createLandingPage(string $uuid): void
    {
        $salesChannelIds = $this->salesChannelRepo->searchIds(new Criteria(), Context::createDefaultContext())->getIds();
        $cmsPageId = $this->cmsPageRepo->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        $saleChannels = [];
        foreach ($salesChannelIds as $id) {
            $saleChannels[] = [
                'id' => $id,
            ];
        }

        $id = Uuid::fromHexToBytes($uuid);
        $landingPage = [
            'id' => $uuid,
            'name' => 'My landing page',
            'metaTitle' => 'My meta title',
            'metaDescription' => 'My meta description',
            'keywords' => 'landing, page, title',
            'url' => 'coolUrl',
            'salesChannels' => $saleChannels,
            'cmsPageId' => $cmsPageId,
            'tags' => [
                [
                    'name' => 'Cooler Tag',
                ],

                [
                    'name' => 'Awesome Tag',
                ],
            ],
        ];

        $this->repository->create([
            $landingPage,
        ], Context::createDefaultContext());

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM landing_page WHERE id = :id',
            ['id' => $id]
        );

        static::assertCount(1, $exists);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM landing_page_translation WHERE landing_page_id = :id',
            ['id' => $id]
        );

        static::assertCount(1, $exists);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM landing_page_sales_channel WHERE landing_page_id = :id',
            ['id' => $id]
        );

        static::assertCount(\count($saleChannels), $exists);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM landing_page_tag WHERE landing_page_id = :id',
            ['id' => $id]
        );

        static::assertCount(2, $exists);
    }
}
