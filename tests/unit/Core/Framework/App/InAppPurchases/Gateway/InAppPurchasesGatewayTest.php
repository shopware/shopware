<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\InAppPurchases\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\InAppPurchases\Event\InAppPurchasesGatewayEvent;
use Shopware\Core\Framework\App\InAppPurchases\Gateway\InAppPurchasesGateway;
use Shopware\Core\Framework\App\InAppPurchases\Payload\InAppPurchasesPayload;
use Shopware\Core\Framework\App\InAppPurchases\Payload\InAppPurchasesPayloadService;
use Shopware\Core\Framework\App\InAppPurchases\Response\InAppPurchasesResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(InAppPurchasesGateway::class)]
#[Package('checkout')]
class InAppPurchasesGatewayTest extends TestCase
{
    public function testProcess(): void
    {
        $context = Generator::createSalesChannelContext();

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setInAppPurchasesGatewayUrl('https://example.com/filter');

        $inAppPurchasesPayload = new InAppPurchasesPayload(['purchase-1', 'purchase-2']);

        $inAppPurchaseFilterResponse = new InAppPurchasesResponse();
        $inAppPurchaseFilterResponse->setPurchases([
            'purchase-1',
            'purchase-2',
        ]);

        $payloadService = $this->createMock(InAppPurchasesPayloadService::class);
        $payloadService
            ->expects(static::once())
            ->method('request')
            ->with(
                static::equalTo($inAppPurchasesPayload),
                $app,
                $context->getContext()
            )
            ->willReturn($inAppPurchaseFilterResponse);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::equalTo(new InAppPurchasesGatewayEvent($inAppPurchaseFilterResponse)));

        $gateway = new InAppPurchasesGateway($app, $context->getContext(), $payloadService, $eventDispatcher);
        $response = $gateway->process($inAppPurchasesPayload);

        static::assertSame($inAppPurchaseFilterResponse, $response);
        static::assertCount(2, $response->getPurchases());
    }
}
