<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp\Tool;

use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartLoadRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartCapability;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartMapper;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Mcp\AbstractUcpMcpTool;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpTool;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[UcpMcpTool(name: 'get_cart', capability: CartCapability::NAME, description: 'Read a cart by id')]
#[Package('framework')]
class GetCartTool extends AbstractUcpMcpTool
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartLoadRoute $cartLoadRoute,
        private readonly CartMapper $cartMapper,
    ) {
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id'],
            'properties' => ['id' => ['type' => 'string', 'description' => 'Cart id from create_cart']],
        ];
    }

    public function outputSchema(): ?array
    {
        return $this->ucpSchemaRef('cart.json', 'cart_resp');
    }

    public function invoke(array $arguments, UcpRequestContext $context): array
    {
        $id = \is_string($arguments['id'] ?? null) ? $arguments['id'] : '';
        if ($id === '') {
            throw UcpException::mcpToolInvalidArguments('get_cart', 'cart id required');
        }

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $id);

        $request->attributes->set('token', $id);

        $request->query->set('token', $id);

        $cart = $this->cartLoadRoute->load($request, $context->salesChannelContext)->getCart();

        return $this->cartMapper->toResponse($cart, $context->salesChannelContext);
    }
}
