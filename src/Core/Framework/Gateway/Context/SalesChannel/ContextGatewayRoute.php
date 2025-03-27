<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopware\Core\Framework\Gateway\Context\ContextGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('framework')]
class ContextGatewayRoute extends AbstractContextGatewayRoute
{
    public function __construct(
        private readonly ContextGatewayInterface $contextGateway,
    ) {
    }

    public function getDecorated(): AbstractContextGatewayRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/context/gateway', name: 'store-api.context.gateway', methods: ['GET', 'POST'])]
    public function load(Request $request, Cart $cart, SalesChannelContext $context): ContextTokenResponse
    {
        $data = new RequestDataBag($request->request->all());

        return $this->contextGateway->process(new ContextGatewayPayloadStruct($cart, $context, $data));
    }
}
