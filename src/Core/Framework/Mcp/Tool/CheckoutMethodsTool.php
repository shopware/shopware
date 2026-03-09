<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-checkout-methods', description: 'List available payment and shipping methods for a sales channel. Use type "payment" for payment methods only, "shipping" for shipping methods only, or "all" for both. Returns method IDs needed for shopware-cart-checkout.')]
#[Package('framework')]
class CheckoutMethodsTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(
        string $salesChannelId,
        string $type = 'all',
    ): string {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'sales_channel:read')) {
            return $error;
        }

        $methodType = CheckoutMethodType::tryFrom($type);
        if ($methodType === null) {
            return $this->error(\sprintf('Invalid type "%s". Must be one of: %s', $type, implode(', ', array_column(CheckoutMethodType::cases(), 'value'))));
        }

        try {
            $context = $this->contextService->get(new SalesChannelContextServiceParameters(
                salesChannelId: $salesChannelId,
                token: Random::getAlphanumericString(32),
            ));

            $request = new Request(['onlyAvailable' => 1]);
            $result = [];

            if ($methodType === CheckoutMethodType::Payment || $methodType === CheckoutMethodType::All) {
                $paymentMethods = $this->paymentMethodRoute->load($request, $context, new Criteria())->getPaymentMethods();
                $result['paymentMethods'] = array_values(array_map(
                    static fn (PaymentMethodEntity $method) => [
                        'id' => $method->getId(),
                        'name' => $method->getTranslation('name'),
                        'description' => $method->getTranslation('description'),
                        'active' => $method->getActive(),
                        'position' => $method->getPosition(),
                    ],
                    $paymentMethods->getElements(),
                ));
            }

            if ($methodType === CheckoutMethodType::Shipping || $methodType === CheckoutMethodType::All) {
                $shippingMethods = $this->shippingMethodRoute->load($request, $context, new Criteria())->getShippingMethods();
                $result['shippingMethods'] = array_values(array_map(
                    static fn (ShippingMethodEntity $method) => [
                        'id' => $method->getId(),
                        'name' => $method->getTranslation('name'),
                        'description' => $method->getTranslation('description'),
                        'active' => $method->getActive(),
                    ],
                    $shippingMethods->getElements(),
                ));
            }

            return $this->success($result);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}
