<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp\Tool;

use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartLoadRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutCapability;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutMapper;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Mcp\AbstractUcpMcpTool;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpTool;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[UcpMcpTool(name: 'get_checkout', capability: CheckoutCapability::NAME, description: 'Read a checkout session by id')]
#[Package('framework')]
class GetCheckoutTool extends AbstractUcpMcpTool
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartLoadRoute $cartLoadRoute,
        private readonly CheckoutMapper $checkoutMapper,
    ) {
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id'],
            'properties' => ['id' => ['type' => 'string']],
        ];
    }

    public function outputSchema(): ?array
    {
        return $this->ucpSchemaRef('checkout.json', 'checkout_resp');
    }

    public function invoke(array $arguments, UcpRequestContext $context): array
    {
        $id = \is_string($arguments['id'] ?? null) ? $arguments['id'] : '';
        if ($id === '') {
            throw UcpException::mcpToolInvalidArguments('get_checkout', 'checkout id required');
        }
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $id);

        $request->attributes->set('token', $id);

        $request->query->set('token', $id);

        $cart = $this->cartLoadRoute->load($request, $context->salesChannelContext)->getCart();

        return $this->checkoutMapper->toResponse(
            $cart,
            $context->salesChannelContext,
            $context->config,
            false,
            $context
        );
    }
}
