<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopware\Core\Framework\Uuid\Uuid;

class ReloadDataResponseTest extends TestCase
{
    public function testCreateWithValidData(): void
    {
        $reloadDataResponse = ReloadDataResponse::create(
            Uuid::randomHex(),
            ActionButtonResponse::ACTION_RELOAD_DATA,
            []
        );
        static::assertInstanceOf(ReloadDataResponse::class, $reloadDataResponse);
    }
}
