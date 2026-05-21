<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp\Tool;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Order\OrderCapability;
use Shopware\Core\Framework\Ucp\Capability\Order\OrderMapper;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Mcp\AbstractUcpMcpTool;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpTool;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[UcpMcpTool(name: 'get_order', capability: OrderCapability::NAME, description: 'Read an order by id')]
#[Package('framework')]
class GetOrderTool extends AbstractUcpMcpTool
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly OrderMapper $orderMapper,
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
        return $this->ucpSchemaRef('order.json', 'order_resp');
    }

    public function invoke(array $arguments, UcpRequestContext $context): array
    {
        $id = \is_string($arguments['id'] ?? null) ? $arguments['id'] : '';
        if ($id === '') {
            throw UcpException::mcpToolInvalidArguments('get_order', 'order id required');
        }

        $criteria = (new Criteria([$id]))
            ->addAssociation('lineItems')
            ->addAssociation('deliveries.stateMachineState')
            ->addAssociation('orderCustomer')
            ->addAssociation('currency')
            ->addAssociation('stateMachineState');

        $order = $this->orderRepository->search($criteria, $context->salesChannelContext->getContext())->first();
        if (!$order instanceof OrderEntity) {
            return ['error' => 'order not found', 'id' => $id];
        }

        return $this->orderMapper->toUcpOrder($order);
    }
}
