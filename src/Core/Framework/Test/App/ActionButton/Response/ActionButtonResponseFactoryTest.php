<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ActionButtonResponseFactoryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    /**
     * @var ActionButtonResponseFactory
     */
    private $actionButtonResponseFactory;

    public function setUp(): void
    {
        $this->actionButtonResponseFactory = $this->getContainer()->get(ActionButtonResponseFactory::class);
    }

    public function testFactoryReturnNotificationResponse(): void
    {
        $notificationResponse = $this->actionButtonResponseFactory->createFromResponse(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_SHOW_NOTITFICATION,
            [
                'status' => 'success',
                'message' => 'This is success',
            ]
        );
        static::assertInstanceOf(NotificationResponse::class, $notificationResponse);
    }

    public function testFactoryReturnReloadDataResponse(): void
    {
        $reloadDataResponse = $this->actionButtonResponseFactory->createFromResponse(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_RELOAD_DATA,
            []
        );
        static::assertInstanceOf(ReloadDataResponse::class, $reloadDataResponse);
    }

    public function testFactoryReturnOpenNewTabResponse(): void
    {
        $openNewTabResponse = $this->actionButtonResponseFactory->createFromResponse(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_OPEN_NEW_TAB,
            [
                'redirectUrl' => 'https://www.google.com/',
            ]
        );
        static::assertInstanceOf(OpenNewTabResponse::class, $openNewTabResponse);
    }

    public function testFactoryThrowException(): void
    {
        $this->expectException(ActionProcessException::class);
        $this->actionButtonResponseFactory->createFromResponse(
            Uuid::randomHex(),
            'test',
            []
        );
    }
}
