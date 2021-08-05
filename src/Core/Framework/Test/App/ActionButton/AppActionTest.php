<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

class AppActionTest extends TestCase
{
    public function testAsPayload(): void
    {
        $targetUrl = 'https://my-server.com/action';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = 'product';
        $action = 'detail';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];
        $result = new AppAction(
            $targetUrl,
            $shopUrl,
            $appVersion,
            $entity,
            $action,
            $ids,
            's3cr3t',
            $shopId,
            Uuid::randomHex()
        );

        $expected = [
            'source' => [
                'url' => $shopUrl,
                'appVersion' => $appVersion,
                'shopId' => $shopId,
            ],
            'data' => [
                'ids' => $ids,
                'entity' => $entity,
                'action' => $action,
            ],
        ];

        static::assertEquals($expected, $result->asPayload());
    }

    public function testInvalidTargetUrl(): void
    {
        static::expectException(InvalidArgumentException::class);
        $targetUrl = 'https://my-server:.com/action';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = 'product';
        $action = 'detail';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];
        new AppAction(
            $targetUrl,
            $shopUrl,
            $appVersion,
            $entity,
            $action,
            $ids,
            's3cr3t',
            $shopId,
            Uuid::randomHex()
        );
    }

    public function testInvalidShopUrl(): void
    {
        static::expectException(InvalidArgumentException::class);
        $targetUrl = 'https://my-server.com/action';
        $shopUrl = 'https://my:shop.com';
        $appVersion = '1.0.0';
        $entity = 'product';
        $action = 'detail';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];
        new AppAction(
            $targetUrl,
            $shopUrl,
            $appVersion,
            $entity,
            $action,
            $ids,
            's3cr3t',
            $shopId,
            Uuid::randomHex()
        );
    }

    public function testInvalidAppVersion(): void
    {
        static::expectException(InvalidArgumentException::class);
        $targetUrl = 'https://my-server.com/action';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0';
        $entity = 'product';
        $action = 'detail';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];
        new AppAction(
            $targetUrl,
            $shopUrl,
            $appVersion,
            $entity,
            $action,
            $ids,
            's3cr3t',
            $shopId,
            Uuid::randomHex()
        );
    }

    public function testEmptyEntity(): void
    {
        static::expectException(InvalidArgumentException::class);
        $targetUrl = 'https://my-server.com/action';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = '';
        $action = 'detail';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];
        new AppAction(
            $targetUrl,
            $shopUrl,
            $appVersion,
            $entity,
            $action,
            $ids,
            's3cr3t',
            $shopId,
            Uuid::randomHex()
        );
    }

    public function testEmptyAction(): void
    {
        static::expectException(InvalidArgumentException::class);
        $targetUrl = 'https://my-server.com/action';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = 'product';
        $action = '';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];
        new AppAction(
            $targetUrl,
            $shopUrl,
            $appVersion,
            $entity,
            $action,
            $ids,
            's3cr3t',
            $shopId,
            Uuid::randomHex()
        );
    }

    public function testInvalidId(): void
    {
        static::expectException(InvalidArgumentException::class);
        $targetUrl = 'https://my-server.com/action';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = 'product';
        $action = 'detail';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex(), 'test'];
        new AppAction(
            $targetUrl,
            $shopUrl,
            $appVersion,
            $entity,
            $action,
            $ids,
            's3cr3t',
            $shopId,
            Uuid::randomHex()
        );
    }

    public function testInvalidAppSecret(): void
    {
        static::expectException(InvalidArgumentException::class);
        $targetUrl = 'https://my-server.com/action';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = 'product';
        $action = 'detail';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];
        new AppAction(
            $targetUrl,
            $shopUrl,
            $appVersion,
            $entity,
            $action,
            $ids,
            '',
            $shopId,
            Uuid::randomHex()
        );
    }

    public function testInvalidShopId(): void
    {
        static::expectException(InvalidArgumentException::class);
        $targetUrl = 'https://my-server.com/action';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = 'product';
        $action = 'detail';
        $shopId = '';
        $ids = [Uuid::randomHex()];
        new AppAction(
            $targetUrl,
            $shopUrl,
            $appVersion,
            $entity,
            $action,
            $ids,
            's3cr3t',
            $shopId,
            Uuid::randomHex()
        );
    }
}
