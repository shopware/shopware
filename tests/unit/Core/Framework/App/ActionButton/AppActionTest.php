<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ActionButton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AppAction::class)]
#[Package('framework')]
class AppActionTest extends TestCase
{
    public function testAsPayload(): void
    {
        $shopUrl = 'https://my-shop.com';
        $appVersion = '1.0.0';
        $entity = 'product';
        $action = 'detail';
        $shopId = Random::getAlphanumericString(12);
        $ids = [Uuid::randomHex()];
        $app = new AppEntity();
        $app->setAppSecret('s3cr3t');
        $app->setName('TestApp');

        $result = new AppAction(
            $app,
            new Source($shopUrl, $shopId, $appVersion),
            'https://my-server.com/action',
            $entity,
            $action,
            $ids,
            Uuid::randomHex()
        );

        $expected = [
            'source' => [
                'url' => $shopUrl,
                'shopId' => $shopId,
                'appVersion' => $appVersion,
                'inAppPurchases' => null,
            ],
            'data' => [
                'ids' => $ids,
                'entity' => $entity,
                'action' => $action,
            ],
        ];

        static::assertSame($expected, $result->asPayload());
    }

    public function testInvalidTargetUrl(): void
    {
        $targetUrl = 'https://my-server:.com/action';
        $this->expectExceptionObject(AppException::invalidArgument($targetUrl . ' is not a valid url'));

        new AppAction(
            new AppEntity(),
            new Source(
                url: 'https://my-shop.com',
                shopId: Random::getAlphanumericString(12),
                appVersion: '1.0.0'
            ),
            targetUrl: $targetUrl,
            entity: 'product',
            action: 'detail',
            ids: [Uuid::randomHex()],
            actionId: Uuid::randomHex()
        );
    }

    public function testRelativeTargetUrlIsValid(): void
    {
        $action = new AppAction(
            app: new AppEntity(),
            source: new Source(
                url: 'https://my-shop.com',
                shopId: Random::getAlphanumericString(12),
                appVersion: '1.0.0'
            ),
            targetUrl: '/api/script/custom-script',
            entity: 'product',
            action: 'detail',
            ids: [Uuid::randomHex()],
            actionId: Uuid::randomHex()
        );

        static::assertSame('/api/script/custom-script', $action->getTargetUrl());
    }

    public function testEmptyEntity(): void
    {
        $this->expectExceptionObject(AppException::missingRequestParameter('entity'));

        new AppAction(
            app: new AppEntity(),
            source: new Source(
                url: 'https://my-shop.com',
                shopId: Random::getAlphanumericString(12),
                appVersion: '1.0.0'
            ),
            targetUrl: 'https://my-server.com/action',
            entity: '',
            action: 'detail',
            ids: [Uuid::randomHex()],
            actionId: Uuid::randomHex()
        );
    }

    public function testEmptyAction(): void
    {
        $this->expectExceptionObject(AppException::missingRequestParameter('action'));

        new AppAction(
            app: new AppEntity(),
            source: new Source(
                url: 'https://my-shop.com',
                shopId: Random::getAlphanumericString(12),
                appVersion: '1.0.0'
            ),
            targetUrl: 'https://my-server.com/action',
            entity: 'product',
            action: '',
            ids: [Uuid::randomHex()],
            actionId: Uuid::randomHex()
        );
    }

    public function testInvalidId(): void
    {
        $this->expectExceptionObject(AppException::invalidArgument('test is not a valid uuid'));

        new AppAction(
            app: new AppEntity(),
            source: new Source(
                url: 'https://my-shop.com',
                shopId: Random::getAlphanumericString(12),
                appVersion: '1.0.0'
            ),
            targetUrl: 'https://my-server.com/action',
            entity: 'product',
            action: 'detail',
            ids: [Uuid::randomHex(), 'test'],
            actionId: Uuid::randomHex()
        );
    }

    public function testInvalidAppSecret(): void
    {
        $this->expectExceptionObject(AppException::missingRequestParameter('app secret'));

        $app = new AppEntity();
        $app->setAppSecret('');

        new AppAction(
            app: $app,
            source: new Source(
                url: 'https://my-shop.com',
                shopId: Random::getAlphanumericString(12),
                appVersion: '1.0.0'
            ),
            targetUrl: 'https://my-server.com/action',
            entity: 'product',
            action: 'detail',
            ids: [Uuid::randomHex()],
            actionId: Uuid::randomHex()
        );
    }
}
