<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Notification\Repository;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Notification\NotificationEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @internal
 */
class NotificationRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $notificationRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->notificationRepository = $this->getContainer()->get('notification.repository');
        $this->context = Context::createDefaultContext();
    }

    #[DataProvider('notificationProvider')]
    public function testNotificationCreateNotification(string $scope, string $behavior): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'status' => 'success',
            'message' => 'this is a successful message',
            'adminOnly' => true,
        ];

        if ($scope === Context::USER_SCOPE && $behavior === 'write') {
            try {
                $this->context->scope($scope, function (Context $context) use ($data): void {
                    $this->notificationRepository->create([$data], $context);
                });
                static::fail(sprintf('Create within wrong scope \'%s\'', $scope));
            } catch (\Exception $e) {
                static::assertInstanceOf(AccessDeniedHttpException::class, $e);
            }

            return;
        }

        $this->notificationRepository->create([$data], $this->context);

        if ($scope === Context::USER_SCOPE) {
            try {
                $this->context->scope($scope, function (Context $context) use ($id): void {
                    $this->notificationRepository->search(new Criteria([$id]), $context);
                });
                static::fail(sprintf('Read within wrong scope \'%s\'', $scope));
            } catch (\Exception $e) {
                static::assertInstanceOf(AccessDeniedHttpException::class, $e);
            }

            return;
        }

        $result = $this->notificationRepository->search(new Criteria([$id]), $this->context);

        /** @var NotificationEntity $notification */
        $notification = $result->get($id);
        static::assertCount(1, $result);
        static::assertSame($data['status'], $notification->getStatus());
        static::assertSame($data['message'], $notification->getMessage());
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function notificationProvider(): array
    {
        return [
            [Context::USER_SCOPE, 'write'],
            [Context::USER_SCOPE, 'read'],
            [Context::SYSTEM_SCOPE, 'write'],
        ];
    }
}
