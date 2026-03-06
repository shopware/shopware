<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-customer-lookup', description: 'Look up a customer by email, customer number, or UUID and get profile with order history. Provide at least one identifier. Returns {success, data: {id, email, customerNumber, firstName, lastName, group, orderCount, orderTotalAmount, recentOrders: [...]}}.')]
#[Package('framework')]
class CustomerLookupTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $email = '', string $customerNumber = '', string $customerId = ''): string
    {
        if ($email === '' && $customerNumber === '' && $customerId === '') {
            return $this->error('Provide at least one of: email, customerNumber, or customerId.');
        }

        $context = $this->contextProvider->getContext();

        if (!$context->isAllowed('customer:read')) {
            return $this->error('Missing privilege: customer:read');
        }

        $repository = $this->registry->getRepository('customer');

        $criteria = $customerId !== ''
            ? new Criteria([$customerId])
            : new Criteria();

        if ($customerId === '' && $email !== '') {
            $criteria->addFilter(new EqualsFilter('email', $email));
        } elseif ($customerId === '' && $customerNumber !== '') {
            $criteria->addFilter(new EqualsFilter('customerNumber', $customerNumber));
        }

        $criteria->setLimit(1);
        $criteria->addAssociation('group');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('defaultShippingAddress.country');

        $orderAssociation = $criteria->getAssociation('orderCustomers.order');
        $orderAssociation->addAssociation('stateMachineState');
        $orderAssociation->addSorting(new FieldSorting('orderDateTime', FieldSorting::DESCENDING));
        $orderAssociation->setLimit(10);

        $result = $repository->search($criteria, $context);

        $customer = $result->first();

        if (!$customer instanceof CustomerEntity) {
            return $this->error('Customer not found.');
        }

        $recentOrders = [];
        foreach ($customer->getOrderCustomers()?->getElements() ?? [] as $orderCustomer) {
            $order = $orderCustomer->getOrder();

            if ($order === null) {
                continue;
            }

            $recentOrders[] = [
                'orderNumber' => $order->getOrderNumber(),
                'status' => $order->getStateMachineState()?->getTechnicalName(),
                'amountTotal' => $order->getAmountTotal(),
                'orderDateTime' => $order->getOrderDateTime()->format('c'),
            ];
        }

        return $this->success([
            'id' => $customer->getId(),
            'email' => $customer->getEmail(),
            'customerNumber' => $customer->getCustomerNumber(),
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
            'group' => $customer->getGroup()?->getName(),
            'active' => $customer->getActive(),
            'createdAt' => $customer->getCreatedAt()?->format('c'),
            'orderCount' => $customer->getOrderCount(),
            'orderTotalAmount' => $customer->getOrderTotalAmount(),
            'lastOrderDate' => $customer->getLastOrderDate()?->format('c'),
            'recentOrders' => $recentOrders,
        ]);
    }
}
