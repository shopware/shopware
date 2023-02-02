<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Test\SalesChannel\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
class SalesChannelCreatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SalesChannelCreator $salesChannelCreator;

    private EntityRepositoryInterface $salesChannelRepository;

    public function setUp(): void
    {
        $this->salesChannelCreator = $this->getContainer()->get(SalesChannelCreator::class);
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
    }

    public function testCreateSalesChannel(): void
    {
        $id = Uuid::randomHex();
        $this->salesChannelCreator->createSalesChannel($id, 'test', Defaults::SALES_CHANNEL_TYPE_API);

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->salesChannelRepository->search(new Criteria([$id]), Context::createDefaultContext())->first();

        static::assertNotNull($salesChannel);
        static::assertEquals('test', $salesChannel->getName());
        static::assertEquals(Defaults::SALES_CHANNEL_TYPE_API, $salesChannel->getTypeId());
    }
}
