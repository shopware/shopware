<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelContextSwitcher
{
    private const SHIPPING_METHOD_ID = SalesChannelContextService::SHIPPING_METHOD_ID;
    private const PAYMENT_METHOD_ID = SalesChannelContextService::PAYMENT_METHOD_ID;
    private const BILLING_ADDRESS_ID = SalesChannelContextService::BILLING_ADDRESS_ID;
    private const SHIPPING_ADDRESS_ID = SalesChannelContextService::SHIPPING_ADDRESS_ID;
    private const COUNTRY_ID = SalesChannelContextService::COUNTRY_ID;
    private const STATE_ID = SalesChannelContextService::STATE_ID;

    /**
     * @var SalesChannelContextPersister
     */
    protected $contextPersister;

    /**
     * @var DataValidator
     */
    protected $validator;

    public function __construct(
        DataValidator $validator,
        SalesChannelContextPersister $contextPersister
    ) {
        $this->contextPersister = $contextPersister;
        $this->validator = $validator;
    }

    public function update(DataBag $data, SalesChannelContext $context): void
    {
        $definition = new DataValidationDefinition('context_switch');

        $parameters = $data->all();

        $criteria = new Criteria();
        if ($context->getCustomer()) {
            $criteria->addFilter(new EqualsFilter('customer_address.customerId', $context->getCustomer()->getId()));
        } else {
            // do not allow to set address ids if the customer is not logged in
            if (isset($parameters[self::SHIPPING_ADDRESS_ID])) {
                throw new CustomerNotLoggedInException();
            }

            if (isset($parameters[self::BILLING_ADDRESS_ID])) {
                throw new CustomerNotLoggedInException();
            }
        }

        $definition
            ->add(self::SHIPPING_METHOD_ID, new EntityExists(['entity' => 'shipping_method', 'context' => $context->getContext()]))
            ->add(self::PAYMENT_METHOD_ID, new EntityExists(['entity' => 'payment_method', 'context' => $context->getContext()]))
            ->add(self::BILLING_ADDRESS_ID, new EntityExists(['entity' => 'customer_address', 'context' => $context->getContext(), 'criteria' => $criteria]))
            ->add(self::SHIPPING_ADDRESS_ID, new EntityExists(['entity' => 'customer_address', 'context' => $context->getContext(), 'criteria' => $criteria]))
            ->add(self::COUNTRY_ID, new EntityExists(['entity' => 'country', 'context' => $context->getContext()]))
            ->add(self::STATE_ID, new EntityExists(['entity' => 'country_state', 'context' => $context->getContext()]))
        ;

        $this->validator->validate($parameters, $definition);

        $this->contextPersister->save($context->getToken(), $parameters);
    }
}
