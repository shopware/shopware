<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\Uuid\Uuid;

class OpenNewTabResponseTest extends TestCase
{
    public function testCreateWithValidData(): void
    {
        $openNewTabResponse = OpenNewTabResponse::create(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_OPEN_NEW_TAB,
            [
                'redirectUrl' => 'https://www.google.com/',
            ]
        );
        static::assertInstanceOf(OpenNewTabResponse::class, $openNewTabResponse);
    }

    public function testCreateWithInvalidRedirectUrl(): void
    {
        $this->expectException(ActionProcessException::class);
        OpenNewTabResponse::create(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_OPEN_NEW_TAB,
            [
                'redirectUrl' => '',
            ]
        );
    }
}
