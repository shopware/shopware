<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Api\AppJWTGenerateRoute;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AppJWTGenerateRoute::class)]
class AppJWTGenerateRouteTest extends TestCase
{
    public function testNotLoggedIn(): void
    {
        $appJWTGenerateRoute = new AppJWTGenerateRoute(
            $this->createMock(Connection::class),
            $this->createMock(ShopIdProvider::class)
        );

        $context = Generator::createSalesChannelContext();
        $context->assign(['customer' => null]);

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('JWT generation requires customer to be logged in');
        $appJWTGenerateRoute->generate('test', $context);
    }

    public function testNotExistingApp(): void
    {
        $appJWTGenerateRoute = new AppJWTGenerateRoute(
            $this->createMock(Connection::class),
            $this->createMock(ShopIdProvider::class)
        );

        $context = Generator::createSalesChannelContext();

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Could not find app with identifier "test"');
        $appJWTGenerateRoute->generate('test', $context);
    }

    public function testGenerate(): void
    {
        InAppPurchase::registerPurchases([
            'active-license-1' => 'extension-1',
            'active-license-2' => 'extension-1',
            'active-license-3' => 'extension-2',
        ]);

        $privileges = [
            'sales_channel:read',
            'customer:read',
            'currency:read',
            'language:read',
            'payment_method:read',
            'shipping_method:read',
        ];

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => 'extension-1', 'app_secret' => '454545454545454545454545454544545454545', 'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR)]);

        $appJWTGenerateRoute = new AppJWTGenerateRoute(
            $connection,
            $this->createMock(ShopIdProvider::class)
        );

        $context = Generator::createSalesChannelContext();

        $response = $appJWTGenerateRoute->generate('test', $context);
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('token', $data);
        $token = $data['token'];

        $parts = explode('.', $token);
        static::assertCount(3, $parts);

        $payload = json_decode((string) base64_decode($parts[1], true), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('salesChannelId', $payload);
        static::assertArrayHasKey('customerId', $payload);
        static::assertSame($context->getSalesChannel()->getId(), $payload['salesChannelId']);
        static::assertSame($context->getCustomer()?->getId(), $payload['customerId']);
        static::assertSame($context->getPaymentMethod()->getId(), $payload['paymentMethodId']);
        static::assertSame($context->getShippingMethod()->getId(), $payload['shippingMethodId']);
        static::assertSame($context->getCurrency()->getId(), $payload['currencyId']);
        static::assertSame($context->getLanguageId(), $payload['languageId']);
        static::assertSame(['active-license-1', 'active-license-2'], $payload['inAppPurchases']);
        InAppPurchase::reset();
    }
}
