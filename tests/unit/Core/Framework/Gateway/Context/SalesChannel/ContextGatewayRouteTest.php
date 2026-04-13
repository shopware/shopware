<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\App\Context\Gateway\AppContextGateway;
use Shopware\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopware\Core\Framework\Gateway\Context\SalesChannel\ContextGatewayRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextGatewayRoute::class)]
class ContextGatewayRouteTest extends TestCase
{
    public function testGetDecorated(): void
    {
        $route = new ContextGatewayRoute($this->createMock(AppContextGateway::class));

        $this->expectException(DecorationPatternException::class);

        $route->getDecorated();
    }

    public function testLoad(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $request = new Request([], ['foo' => 'bar', 'bat' => 'baz']);

        $expectedPayload = new ContextGatewayPayloadStruct($cart, $context, new RequestDataBag(['foo' => 'bar', 'bat' => 'baz']));

        $appContextGateway = $this->createMock(AppContextGateway::class);
        $appContextGateway
            ->expects($this->once())
            ->method('process')
            ->with(static::equalTo($expectedPayload));

        $route = new ContextGatewayRoute($appContextGateway);
        $route->load($request, $cart, $context);
    }
}
