<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Controller;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Notification\NotificationCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
class NotificationControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;
    use GuzzleTestClientBehaviour;

    /**
     * @var EntityRepository<NotificationCollection>
     */
    private EntityRepository $notificationRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->notificationRepository = $this->getContainer()->get('notification.repository');

        $this->context = Context::createDefaultContext();
    }

    /**
     * @param array<string> $requirePrivileges
     */
    #[DataProvider('saveNotificationProvider')]
    public function testSaveNotification(
        string $client,
        string $status,
        string $message,
        bool $adminOnly,
        array $requirePrivileges,
        bool $isSuccess
    ): void {
        $integrationId = null;
        if ($client === 'integration') {
            $ids = new IdsCollection();
            $integrationId = $ids->create('integration');
            $client = $this->getBrowserAuthenticatedWithIntegration($integrationId);
        } else {
            $client = $this->getBrowser();
        }

        $url = '/api/notification';
        $data = [
            'status' => $status,
            'message' => $message,
            'adminOnly' => $adminOnly,
            'requiredPrivileges' => $requirePrivileges,
        ];

        $json = \json_encode($data, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        if (!$isSuccess) {
            $this->appendNewResponse(new Response(500));
            $client->request('POST', $url, [], [], [], $json);

            return;
        }

        $this->appendNewResponse(new Response(200));

        $client->request('POST', $url, [], [], [], $json);

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $criteria = (new Criteria())->setLimit(1);

        $notifications = $this->notificationRepository->search($criteria, $this->context)->getEntities();
        static::assertCount(1, $notifications);

        $notification = $notifications->first();
        static::assertNotNull($notification);
        static::assertSame($data['status'], $notification->getStatus());
        static::assertSame($data['message'], $notification->getMessage());
        static::assertSame($data['adminOnly'], $notification->isAdminOnly());
        static::assertSame($data['requiredPrivileges'], $notification->getRequiredPrivileges());

        if ($integrationId) {
            static::assertSame($integrationId, $notification->getCreatedByIntegrationId());
        }
    }

    /**
     * @return array<array<array<string>|string|bool>>
     */
    public static function saveNotificationProvider(): array
    {
        return [
            ['integration', 'success', 'This is a notification', false, ['cache:clear'], true],
            ['integration', '', 'This is a notification', false, ['cache:clear'], false],
            ['integration', 'success', '', false, ['cache:clear'], false],
            ['browser', 'success', 'This is a notification', true, [], true],
        ];
    }

    /**
     * @param array<string> $requiredPrivileges
     * @param array<string>|null $userPrivileges
     */
    #[DataProvider('getNotificationProvider')]
    public function testGetNotifications(
        bool $adminOnly,
        array $requiredPrivileges,
        ?array $userPrivileges,
        int $resultQuantity
    ): void {
        $data = [
            'status' => 'success',
            'message' => 'This is a successful message',
            'adminOnly' => $adminOnly,
            'requiredPrivileges' => $requiredPrivileges,
        ];
        $this->notificationRepository->create([$data], $this->context);

        if (\is_array($userPrivileges)) {
            $this->authorizeBrowser($this->getBrowser(), [], $userPrivileges);
        }

        $this->getBrowser()->request('GET', '/api/notification/message');

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());

        $content = \json_decode($this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if ($resultQuantity === 0) {
            static::assertCount(0, $content['notifications']);

            return;
        }

        static::assertSame($data['status'], $content['notifications'][0]['status']);
        static::assertSame($data['message'], $content['notifications'][0]['message']);
        static::assertSame($data['adminOnly'], $content['notifications'][0]['adminOnly']);
        static::assertSame($data['requiredPrivileges'], $content['notifications'][0]['requiredPrivileges']);
        static::assertNotEmpty($content['timestamp']);
    }

    /**
     * @return array<array<array<string>|bool|int|null>>
     */
    public static function getNotificationProvider(): array
    {
        return [
            [true, [], null, 1],
            [false, ['cache:clear'], ['cache:clear'], 1],
            [false, ['cache:clear'], [], 0],
        ];
    }
}
