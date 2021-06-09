<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponse;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\Uuid\Uuid;

class NotificationResponseTest extends TestCase
{
    public function testCreateWithValidData(): void
    {
        $notificationResponse = NotificationResponse::create(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_SHOW_NOTITFICATION,
            [
                'status' => 'success',
                'message' => 'This is success',
            ]
        );
        static::assertInstanceOf(NotificationResponse::class, $notificationResponse);
    }

    public function testCreateWithInvalidStatus(): void
    {
        $this->expectException(ActionProcessException::class);
        NotificationResponse::create(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_SHOW_NOTITFICATION,
            [
                'status' => '',
                'message' => 'This is success',
            ]
        );
    }

    public function testCreateWithInvalidMessage(): void
    {
        $this->expectException(ActionProcessException::class);
        NotificationResponse::create(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_SHOW_NOTITFICATION,
            [
                'status' => 'success',
                'message' => '',
            ]
        );
    }
}
