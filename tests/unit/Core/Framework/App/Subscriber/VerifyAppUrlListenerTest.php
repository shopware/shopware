<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdChangedEvent;
use Shopware\Core\Framework\App\Subscriber\VerifyAppUrlListener;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;

/**
 * @internal
 */
#[CoversClass(VerifyAppUrlListener::class)]
class VerifyAppUrlListenerTest extends TestCase
{
    public function testForceVerifyCalledWhenUrlsDiffer(): void
    {
        $verifier = $this->createMock(AppUrlVerifier::class);
        $listener = new VerifyAppUrlListener($verifier);

        $newShopId = ShopId::v2('new-shop-id', [AppUrl::IDENTIFIER => 'https://new.example']);
        $oldShopId = ShopId::v2('old-shop-id', [AppUrl::IDENTIFIER => 'https://old.example']);

        $event = new ShopIdChangedEvent($newShopId, $oldShopId);

        $verifier->expects($this->once())
            ->method('forceVerify')
            ->with($newShopId);

        $listener($event);
    }

    public function testNoVerificationWhenUrlsNotChanged(): void
    {
        $verifier = $this->createMock(AppUrlVerifier::class);
        $listener = new VerifyAppUrlListener($verifier);

        $newShopId = ShopId::v2('new-shop-id', [AppUrl::IDENTIFIER => 'https://www.example.com']);
        $oldShopId = ShopId::v2('old-shop-id', [AppUrl::IDENTIFIER => 'https://www.example.com']);

        $event = new ShopIdChangedEvent($newShopId, $oldShopId);

        $verifier->expects($this->never())
            ->method('forceVerify');

        $listener($event);
    }

    public function testForceVerifyWhenNoOldShopId(): void
    {
        $verifier = $this->createMock(AppUrlVerifier::class);
        $listener = new VerifyAppUrlListener($verifier);

        $shopId = ShopId::v2('new-shop-id', [AppUrl::IDENTIFIER => 'https://www.example.com']);

        $event = new ShopIdChangedEvent($shopId, null);

        $verifier->expects($this->once())
            ->method('forceVerify')
            ->with($shopId);

        $listener($event);
    }

    public function testNoVerificationWhenNewUrlMissing(): void
    {
        $verifier = $this->createMock(AppUrlVerifier::class);
        $listener = new VerifyAppUrlListener($verifier);

        $newShopId = ShopId::v2('new-shop-id');
        $oldShopId = ShopId::v2('old-shop-id', [AppUrl::IDENTIFIER => 'https://www.example.com']);

        $event = new ShopIdChangedEvent($newShopId, $oldShopId);

        $verifier->expects($this->never())
            ->method('forceVerify');

        $listener($event);
    }
}
