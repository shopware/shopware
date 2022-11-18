<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Controller;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Notification\NotificationCollection;
use Shopware\Administration\Notification\NotificationEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;

/**
 * @internal
 */
class NotificationControllerTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;

    private EntityRepository  $notificationRepository;

    private Context $context;

    private string $defaultSecret = 's3cr3t';

    public function setUp(): void
    {
        $this->notificationRepository = $this->getContainer()->get('notification.repository');

        $this->context = Context::createDefaultContext();
    }

    /**
     * @dataProvider saveNotificationProvider
     */
    public function testSaveNotification($client, $status, $message, $adminOnly, $requirePrivileges, $isSuccess): void
    {
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

        if (!$isSuccess) {
            $this->appendNewResponse(new Response(500));
            $client->request('POST', $url, [], [], [], json_encode($data));

            return;
        }

        $this->appendNewResponse(new Response(200));
        $client->request('POST', $url, [], [], [], json_encode($data));

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        $criteria = (new Criteria())->setLimit(1);

        /** @var NotificationCollection $notifications */
        $notifications = $this->notificationRepository->search($criteria, $this->context);
        static::assertEquals(1, $notifications->count());

        /** @var NotificationEntity $notification */
        $notification = $notifications->first();
        static::assertEquals($data['status'], $notification->getStatus());
        static::assertEquals($data['message'], $notification->getMessage());
        static::assertEquals($data['adminOnly'], $notification->isAdminOnly());
        static::assertEquals($data['requiredPrivileges'], $notification->getRequiredPrivileges());

        if ($integrationId) {
            static::assertEquals($integrationId, $notification->getCreatedByIntegrationId());
        }
    }

    public function saveNotificationProvider(): array
    {
        return [
            ['integration', 'success', 'This is a notification', false, ['cache:clear'], true],
            ['integration', '', 'This is a notification', false, ['cache:clear'], false],
            ['integration', 'success', '', false, ['cache:clear'], false],
            ['browser', 'success', 'This is a notification', true, [], true],
        ];
    }

    /**
     * @dataProvider getNotificationProvider
     */
    public function testGetNotifications($adminOnly, $requiredPrivileges, $userPrivileges, $resultQuantity): void
    {
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

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        $content = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        if ($resultQuantity === 0) {
            static::assertCount(0, $content['notifications']);

            return;
        }

        static::assertEquals($data['status'], $content['notifications'][0]['status']);
        static::assertEquals($data['message'], $content['notifications'][0]['message']);
        static::assertEquals($data['adminOnly'], $content['notifications'][0]['adminOnly']);
        static::assertEquals($data['requiredPrivileges'], $content['notifications'][0]['requiredPrivileges']);
        static::assertNotEmpty($content['timestamp']);
    }

    public function getNotificationProvider(): array
    {
        return [
            [true, [], null, 1],
            [false, ['cache:clear'], ['cache:clear'], 1],
            [false, ['cache:clear'], [], 0],
        ];
    }
}
