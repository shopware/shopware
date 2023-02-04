<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerAffiliateAndCampaignCodeAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerAffiliateAndCampaignCodeAction
 */
class AddCustomerAffiliateAndCampaignCodeActionTest extends TestCase
{
    private Connection&MockObject $connection;

    private MockObject&EntityRepository $repository;

    private AddCustomerAffiliateAndCampaignCodeAction $action;

    private MockObject&StorableFlow $flow;

    public function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->action = new AddCustomerAffiliateAndCampaignCodeAction($this->connection, $this->repository);

        $this->flow = $this->createMock(StorableFlow::class);
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [CustomerAware::class],
            $this->action->requirements()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.add.customer.affiliate.and.campaign.code', AddCustomerAffiliateAndCampaignCodeAction::getName());
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $existedData
     * @param array<string, mixed> $expected
     *
     * @dataProvider actionExecuteProvider
     */
    public function testActionWithExpectedUpdate(array $config, array $existedData, array $expected): void
    {
        $this->connection->expects(static::once())->method('fetchAssociative')->willReturn($existedData);
        $this->flow->expects(static::exactly(2))->method('getStore')->willReturn(Uuid::randomHex());
        $this->flow->expects(static::once())->method('hasStore')->willReturn(true);
        $this->flow->expects(static::once())->method('getConfig')->willReturn($config);

        $withData = [[...[
            'id' => $this->flow->getStore('customerId'),
        ], ...$expected]];

        $this->repository->expects(static::once())->method('update')->with($withData);

        $this->action->handleFlow($this->flow);
    }

    public function testActionWithNotAware(): void
    {
        $this->flow->expects(static::once())->method('hasStore')->willReturn(false);
        $this->flow->expects(static::never())->method('getStore');
        $this->repository->expects(static::never())->method('update');

        $this->action->handleFlow($this->flow);
    }

    public function testActionWithEmptyConfig(): void
    {
        $this->flow->expects(static::once())->method('hasStore')->willReturn(true);
        $this->flow->expects(static::once())->method('getStore')->willReturn(Uuid::randomHex());
        $this->flow->expects(static::once())->method('getConfig')->willReturn([]);
        $this->repository->expects(static::never())->method('update');

        $this->action->handleFlow($this->flow);
    }

    public function actionExecuteProvider(): \Generator
    {
        yield 'Test with non data exists' => [
            [
                'affiliateCode' => ['value' => '11111', 'upsert' => false],
                'campaignCode' => ['value' => '22222', 'upsert' => false],
            ],
            [
                'affiliate_code' => null,
                'campaign_code' => null,
            ],
            ['affiliateCode' => '11111', 'campaignCode' => '22222'],
        ];

        yield 'Test with data config only upsert campaignCode' => [
            [
                'affiliateCode' => ['value' => '33333', 'upsert' => false],
                'campaignCode' => ['value' => '33333', 'upsert' => true],
            ],
            ['affiliate_code' => '11111', 'campaign_code' => '22222'],
            ['campaignCode' => '33333'],
        ];

        yield 'Test with data config upsert both campaignCode and affiliateCode' => [
            [
                'affiliateCode' => ['value' => '33333', 'upsert' => true],
                'campaignCode' => ['value' => '33333', 'upsert' => true],
            ],
            ['affiliate_code' => '11111', 'campaign_code' => '22222'],
            ['affiliateCode' => '33333', 'campaignCode' => '33333'],
        ];
    }
}
