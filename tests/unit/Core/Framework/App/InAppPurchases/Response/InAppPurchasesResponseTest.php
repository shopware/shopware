<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\InAppPurchases\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\InAppPurchases\Response\InAppPurchasesResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(InAppPurchasesResponse::class)]
#[Package('checkout')]
class InAppPurchasesResponseTest extends TestCase
{
    public function testAssign(): void
    {
        $arrayStruct = [
            'purchases' => [
                'purchase-1',
                'purchase-2',
            ],
        ];

        $response = (new InAppPurchasesResponse())->assign($arrayStruct);

        static::assertCount(2, $response->getPurchases());

        static::assertEquals('purchase-1', $response->getPurchases()[0]);
        static::assertEquals('purchase-2', $response->getPurchases()[1]);
    }

    public function testSetters(): void
    {
        $response = new InAppPurchasesResponse();

        $response->setPurchases([
            'purchase-1',
            'purchase-2',
        ]);

        static::assertCount(2, $response->getPurchases());

        static::assertEquals('purchase-1', $response->getPurchases()[0]);
        static::assertEquals('purchase-2', $response->getPurchases()[1]);
    }
}
