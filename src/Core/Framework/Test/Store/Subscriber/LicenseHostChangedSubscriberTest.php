<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Subscriber\LicenseHostChangedSubscriber;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
class LicenseHostChangedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private LicenseHostChangedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = $this->getContainer()->get(LicenseHostChangedSubscriber::class);
    }

    public function testIsSubscribedToSystemConfigChangedEvents(): void
    {
        static::assertArrayHasKey(SystemConfigChangedEvent::class, $this->subscriber->getSubscribedEvents());
        static::assertEquals('onLicenseHostChanged', $this->subscriber->getSubscribedEvents()[SystemConfigChangedEvent::class]);
    }

    public function testOnlyHandlesLicenseHostChangedEvents(): void
    {
        $event = new SystemConfigChangedEvent('random.config.key', null, null);

        $this->subscriber->onLicenseHostChanged($event);
    }

    public function testDeletesShopSecretAndLogsOutAllUsers(): void
    {
        $context = Context::createDefaultContext();

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.store.shopSecret', 'shop-s3cr3t');

        /** @var EntityRepository $userRepository */
        $userRepository = $this->getContainer()->get('user.repository');

        /** @var UserEntity $adminUser */
        $adminUser = $userRepository->search(new Criteria(), $context)->first();

        $userRepository->create([
            [
                'localeId' => $adminUser->getLocaleId(),
                'username' => 'admin2',
                'password' => 'v3rys3cr3t',
                'firstName' => 'admin2',
                'lastName' => 'admin2',
                'email' => 'admin2@shopware.com',
                'storeToken' => null,
            ],
            [
                'localeId' => $adminUser->getLocaleId(),
                'username' => 'admin3',
                'password' => 'v3rys3cr3t',
                'firstName' => 'admin3',
                'lastName' => 'admin3',
                'email' => 'admin3@shopware.com',
                'storeToken' => null,
            ],
        ], $context);

        $event = new SystemConfigChangedEvent('core.store.licenseHost', null, null);

        $this->subscriber->onLicenseHostChanged($event);

        $adminUsers = $this->fetchAllAdminUsers();

        static::assertCount(3, $adminUsers);

        foreach ($adminUsers as $adminUser) {
            static::assertNull($adminUser['store_token']);
        }

        static::assertNull($systemConfigService->get('core.store.shopSecret'));
    }

    private function fetchAllAdminUsers(): array
    {
        return $this->getContainer()->get(Connection::class)->executeQuery(
            'SELECT * FROM user'
        )->fetchAllAssociative();
    }
}
