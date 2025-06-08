<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SetPaymentOrderRouteResponse::class)]
class SetPaymentOrderRouteResponseTest extends TestCase
{
    public function testPublicAPI(): void
    {
        $response = new SetPaymentOrderRouteResponse();
        $object = $response->getObject();

        static::assertInstanceOf(ArrayStruct::class, $object);
        static::assertTrue($object->get('success'));
    }
}
