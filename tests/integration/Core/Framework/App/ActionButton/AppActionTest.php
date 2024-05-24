<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ActionButton;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
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
            new Source($shopUrl, $shopId, $appVersion),
            $entity,
            $action,
            $ids,
            's3cr3t',
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
            new Source($shopUrl, $shopId, $appVersion),
            $entity,
            $action,
            $ids,
            's3cr3t',
            Uuid::randomHex()
        );
    }

    public function testRelativeTargetUrlIsValid(): void
    {
        $targetUrl = '/api/script/custom-script';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = 'product';
        $action = 'detail';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];

        $action = new AppAction(
            $targetUrl,
            new Source($shopUrl, $shopId, $appVersion),
            $entity,
            $action,
            $ids,
            's3cr3t',
            Uuid::randomHex()
        );

        static::assertSame('/api/script/custom-script', $action->getTargetUrl());
    }

    public function testEmptyEntity(): void
    {
        static::expectException(AppException::class);
        $targetUrl = 'https://my-server.com/action';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = '';
        $action = 'detail';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];
        new AppAction(
            $targetUrl,
            new Source($shopUrl, $shopId, $appVersion),
            $entity,
            $action,
            $ids,
            's3cr3t',
            Uuid::randomHex()
        );
    }

    public function testEmptyAction(): void
    {
        static::expectException(AppException::class);
        $targetUrl = 'https://my-server.com/action';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = 'product';
        $action = '';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];
        new AppAction(
            $targetUrl,
            new Source($shopUrl, $shopId, $appVersion),
            $entity,
            $action,
            $ids,
            's3cr3t',
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
            new Source($shopUrl, $shopId, $appVersion),
            $entity,
            $action,
            $ids,
            's3cr3t',
            Uuid::randomHex()
        );
    }

    public function testInvalidAppSecret(): void
    {
        static::expectException(AppException::class);
        $targetUrl = 'https://my-server.com/action';
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = 'product';
        $action = 'detail';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];
        new AppAction(
            $targetUrl,
            new Source($shopUrl, $shopId, $appVersion),
            $entity,
            $action,
            $ids,
            '',
            Uuid::randomHex()
        );
    }
}
