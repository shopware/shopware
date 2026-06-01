<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItStoresTemplateOverridesAsSalesChannelScopedConfiguration(): void
    {
        $id = Uuid::randomHex();

        $repository = $this->getSalesChannelFileRepository();
        $repository->create([
            [
                'id' => $id,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => 'llms.txt',
                'enabled' => true,
                'templateOverrides' => [
                    'Framework' => 'merchant override',
                    'Ucp' => 'plugin override',
                ],
            ],
        ], Context::createDefaultContext());

        $entity = $repository->search(new Criteria([$id]), Context::createDefaultContext())->first();

        static::assertInstanceOf(SalesChannelFileEntity::class, $entity);
        static::assertSame(TestDefaults::SALES_CHANNEL, $entity->getSalesChannelId());
        static::assertSame('agentic', $entity->getFileFamily());
        static::assertSame('llms.txt', $entity->getFileName());
        static::assertTrue($entity->isEnabled());

        $templateOverrides = $entity->getTemplateOverrides();
        ksort($templateOverrides);

        static::assertSame([
            'Framework' => 'merchant override',
            'Ucp' => 'plugin override',
        ], $templateOverrides);
    }

    public function testSalesChannelAssociationLoadsFiles(): void
    {
        $id = Uuid::randomHex();

        $this->getSalesChannelFileRepository()->create([
            [
                'id' => $id,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => 'agents.md',
                'enabled' => false,
                'templateOverrides' => [],
            ],
        ], Context::createDefaultContext());

        $criteria = (new Criteria([TestDefaults::SALES_CHANNEL]))->addAssociation('salesChannelFiles');
        $salesChannel = $this->getSalesChannelRepository()->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        static::assertNotNull($salesChannel->getSalesChannelFiles());
        static::assertTrue($salesChannel->getSalesChannelFiles()->has($id));
    }

    /**
     * @return EntityRepository<SalesChannelFileCollection>
     */
    private function getSalesChannelFileRepository(): EntityRepository
    {
        return static::getContainer()->get('sales_channel_file.repository');
    }

    /**
     * @return EntityRepository<SalesChannelCollection>
     */
    private function getSalesChannelRepository(): EntityRepository
    {
        return static::getContainer()->get('sales_channel.repository');
    }
}
