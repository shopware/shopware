<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\InAppPurchases\Payload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\InAppPurchases\Payload\InAppPurchasesPayload;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(InAppPurchasesPayload::class)]
#[Package('checkout')]
class InAppPurchasesPayloadTest extends TestCase
{
    public function testApi(): void
    {
        $payload = new InAppPurchasesPayload(['purchase-1', 'purchase-2']);
        static::assertSame(['purchase-1', 'purchase-2'], $payload->getPurchases());
    }
}
