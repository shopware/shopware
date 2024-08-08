<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\InAppPurchases\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\InAppPurchases\Event\InAppPurchasesGatewayEvent;
use Shopware\Core\Framework\App\InAppPurchases\Response\InAppPurchasesResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(InAppPurchasesGatewayEvent::class)]
#[Package('checkout')]
class InAppPurchasesGatewayEventTest extends TestCase
{
    public function testGetResponse(): void
    {
        $response = new InAppPurchasesResponse();
        $event = new InAppPurchasesGatewayEvent($response);

        static::assertSame($response, $event->getResponse());
    }
}
