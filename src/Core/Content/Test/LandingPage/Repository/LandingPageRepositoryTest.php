<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\LandingPage\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class LandingPageRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepository
     */
    private $repository;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepo;

    /**
     * @var EntityRepository
     */
    private $cmsPageRepo;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('landing_page.repository');
        $this->salesChannelRepo = $this->getContainer()->get('sales_channel.repository');
        $this->cmsPageRepo = $this->getContainer()->get('cms_page.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
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
