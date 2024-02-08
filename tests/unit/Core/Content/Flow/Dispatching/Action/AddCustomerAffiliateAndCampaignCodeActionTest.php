<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\AddCustomerAffiliateAndCampaignCodeAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(AddCustomerAffiliateAndCampaignCodeAction::class)]
class AddCustomerAffiliateAndCampaignCodeActionTest extends TestCase
{
    private Connection&MockObject $connection;

    private MockObject&EntityRepository $repository;

    private AddCustomerAffiliateAndCampaignCodeAction $action;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->action = new AddCustomerAffiliateAndCampaignCodeAction($this->connection, $this->repository);
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
     */
    #[DataProvider('actionExecuteProvider')]
    public function testActionWithExpectedUpdate(array $config, array $existedData, array $expected): void
    {
        $this->connection->expects(static::once())->method('fetchAssociative')->willReturn($existedData);

        $customerId = Uuid::randomHex();
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            CustomerAware::CUSTOMER_ID => $customerId,
        ]);
        $flow->setConfig($config);

        $expected['id'] = $customerId;
        $this->repository->expects(static::once())->method('update')->with([$expected]);

        $this->action->handleFlow($flow);
    }

    public function testActionWithNotAware(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext());

        $this->repository->expects(static::never())->method('update');

        $this->action->handleFlow($flow);
    }

    public function testActionWithEmptyConfig(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            CustomerAware::CUSTOMER_ID => Uuid::randomHex(),
        ]);

        $this->repository->expects(static::never())->method('update');

        $this->action->handleFlow($flow);
    }

    public static function actionExecuteProvider(): \Generator
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
