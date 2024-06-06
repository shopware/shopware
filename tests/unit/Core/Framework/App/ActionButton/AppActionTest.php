<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ActionButton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AppAction::class)]
#[Package('core')]
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
        $app = new AppEntity();
        $app->setAppSecret('s3cr3t');
        $result = new AppAction(
            $app,
            new Source($shopUrl, $shopId, $appVersion),
            $targetUrl,
            $entity,
            $action,
            $ids,
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
            new AppEntity(),
            new Source($shopUrl, $shopId, $appVersion),
            $targetUrl,
            $entity,
            $action,
            $ids,
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
            new AppEntity(),
            new Source($shopUrl, $shopId, $appVersion),
            $targetUrl,
            $entity,
            $action,
            $ids,
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
            new AppEntity(),
            new Source($shopUrl, $shopId, $appVersion),
            $targetUrl,
            $entity,
            $action,
            $ids,
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
            new AppEntity(),
            new Source($shopUrl, $shopId, $appVersion),
            $targetUrl,
            $entity,
            $action,
            $ids,
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
            new AppEntity(),
            new Source($shopUrl, $shopId, $appVersion),
            $targetUrl,
            $entity,
            $action,
            $ids,
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
        $app = new AppEntity();
        $app->setAppSecret('');
        new AppAction(
            $app,
            new Source($shopUrl, $shopId, $appVersion),
            $targetUrl,
            $entity,
            $action,
            $ids,
            Uuid::randomHex()
        );
    }
}
