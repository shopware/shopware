<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Maintenance\SalesChannel\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @internal
 */
#[Package('core')]
class SalesChannelCreatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SalesChannelCreator $salesChannelCreator;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    protected function setUp(): void
    {
        $this->salesChannelCreator = static::getContainer()->get(SalesChannelCreator::class);
        $this->salesChannelRepository = static::getContainer()->get('sales_channel.repository');
    }

    public function testCreateSalesChannel(): void
    {
        $id = Uuid::randomHex();
        $this->salesChannelCreator->createSalesChannel($id, 'test', Defaults::SALES_CHANNEL_TYPE_API);

        $salesChannel = $this->salesChannelRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($salesChannel);
        static::assertSame('test', $salesChannel->getName());
        static::assertSame(Defaults::SALES_CHANNEL_TYPE_API, $salesChannel->getTypeId());
    }
}
