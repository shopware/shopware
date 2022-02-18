<?php declare(strict_types=1);

namespace Shopware\Administration\Test\System\SalesChannel\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\System\SalesChannel\Subscriber\SalesChannelUserConfigSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;

class SalesChannelUserConfigSubscriberTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    public function testDeleteWillRemoveUserConfigs(): void
    {
        $admin = TestUser::createNewTestUser($this->getContainer()->get(Connection::class), ['product:read']);
        $context = Context::createDefaultContext();

        $salesChannelId1 = Uuid::randomHex();
        $salesChannelId2 = Uuid::randomHex();

        $userConfigRepository = $this->getContainer()->get('user_config.repository');
        $userConfigId = Uuid::randomHex();
        $userConfigRepository->create([
            [
                'id' => $userConfigId,
                'userId' => $admin->getUserId(),
                'key' => SalesChannelUserConfigSubscriber::CONFIG_KEY,
                'value' => [$salesChannelId1, $salesChannelId2],
                'createdAt' => new \DateTime(),
            ],
        ], $context);

        static::assertCount(2, $userConfigRepository->search(new Criteria([$userConfigId]), $context)->first()->getValue());

        $this->createSalesChannel(['id' => $salesChannelId1]);
        $this->createSalesChannel(['id' => $salesChannelId2]);

        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $salesChannelRepository->delete([['id' => $salesChannelId1], ['id' => $salesChannelId2]], $context);

        static::assertCount(0, $userConfigRepository->search(new Criteria([$userConfigId]), $context)->first()->getValue());
    }
}
