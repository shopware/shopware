<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponse;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\Uuid\Uuid;

class OpenModalResponseTest extends TestCase
{
    public function testCreateWithValidData(): void
    {
        $openNewTabResponse = OpenModalResponse::create(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_OPEN_MODAL,
            [
                'iframeUrl' => 'https://www.google.com/',
                'size' => 'medium',
            ]
        );
        static::assertInstanceOf(OpenModalResponse::class, $openNewTabResponse);
    }

    public function testCreateWithInvalidIframeUrl(): void
    {
        $this->expectException(ActionProcessException::class);
        OpenModalResponse::create(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_OPEN_MODAL,
            [
                'iframeUrl' => '',
            ]
        );
    }

    public function testCreateWithInvalidSize(): void
    {
        $this->expectException(ActionProcessException::class);
        OpenModalResponse::create(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_OPEN_MODAL,
            [
                'iframeUrl' => 'https://www.google.com/',
                'size' => 'full',
            ]
        );
    }
}
