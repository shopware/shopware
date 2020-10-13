<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ContextSwitchRoute extends AbstractContextSwitchRoute
{
    private const SHIPPING_METHOD_ID = SalesChannelContextService::SHIPPING_METHOD_ID;
    private const PAYMENT_METHOD_ID = SalesChannelContextService::PAYMENT_METHOD_ID;
    private const BILLING_ADDRESS_ID = SalesChannelContextService::BILLING_ADDRESS_ID;
    private const SHIPPING_ADDRESS_ID = SalesChannelContextService::SHIPPING_ADDRESS_ID;
    private const COUNTRY_ID = SalesChannelContextService::COUNTRY_ID;
    private const STATE_ID = SalesChannelContextService::COUNTRY_STATE_ID;
    private const CURRENCY_ID = SalesChannelContextService::CURRENCY_ID;
    private const LANGUAGE_ID = SalesChannelContextService::LANGUAGE_ID;

    /**
     * @var SalesChannelContextPersister
     */
    protected $contextPersister;

    /**
     * @var DataValidator
     */
    protected $validator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        DataValidator $validator,
        SalesChannelContextPersister $contextPersister,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->contextPersister = $contextPersister;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getDecorated(): AbstractContextSwitchRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Patch(
     *      path="/context",
     *      description="Update the context",
     *      operationId="updateContext",
     *      tags={"Store API","Context"},
     *      @OA\Parameter(name="currencyId", description="Currency", @OA\Schema(type="string")),
     *      @OA\Parameter(name="languageId", description="Language", @OA\Schema(type="string")),
     *      @OA\Parameter(name="billingAddressId", description="Billing Address", @OA\Schema(type="string")),
     *      @OA\Parameter(name="shippingAddressId", description="Shipping Address", @OA\Schema(type="string")),
     *      @OA\Parameter(name="paymentMethodId", description="Payment Method", @OA\Schema(type="string")),
     *      @OA\Parameter(name="shippingMethodId", description="Shipping Method", @OA\Schema(type="string")),
     *      @OA\Parameter(name="countryId", description="Country", @OA\Schema(type="string")),
     *      @OA\Parameter(name="countryStateId", description="Country State", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response="200",
     *          description="Context",
     *          @OA\JsonContent(ref="#/definitions/ContextTokenResponse")
     *     )
     * )
     * @Route("/store-api/v{version}/context", name="store-api.switch-context", methods={"PATCH"})
     */
    public function switchContext(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        $definition = new DataValidationDefinition('context_switch');

        $parameters = $data->only(
            self::SHIPPING_METHOD_ID,
            self::PAYMENT_METHOD_ID,
            self::BILLING_ADDRESS_ID,
            self::SHIPPING_ADDRESS_ID,
            self::COUNTRY_ID,
            self::STATE_ID,
            self::CURRENCY_ID,
            self::LANGUAGE_ID
        );

        $addressCriteria = new Criteria();
        if ($context->getCustomer()) {
            $addressCriteria->addFilter(new EqualsFilter('customer_address.customerId', $context->getCustomer()->getId()));
        } else {
            // do not allow to set address ids if the customer is not logged in
            if (isset($parameters[self::SHIPPING_ADDRESS_ID])) {
                throw new CustomerNotLoggedInException();
            }

            if (isset($parameters[self::BILLING_ADDRESS_ID])) {
                throw new CustomerNotLoggedInException();
            }
        }

        $currencyCriteria = new Criteria();
        $currencyCriteria->addFilter(
            new EqualsFilter('currency.salesChannels.id', $context->getSalesChannel()->getId())
        );

        $languageCriteria = new Criteria();
        $languageCriteria->addFilter(
            new EqualsFilter('language.salesChannels.id', $context->getSalesChannel()->getId())
        );

        $paymentMethodCriteria = new Criteria();
        $paymentMethodCriteria->addFilter(
            new EqualsFilter('payment_method.salesChannels.id', $context->getSalesChannel()->getId())
        );

        $shippingMethodCriteria = new Criteria();
        $shippingMethodCriteria->addFilter(
            new EqualsFilter('shipping_method.salesChannels.id', $context->getSalesChannel()->getId())
        );

        $definition
            ->add(self::LANGUAGE_ID, new EntityExists(['entity' => 'language', 'context' => $context->getContext(), 'criteria' => $languageCriteria]))
            ->add(self::CURRENCY_ID, new EntityExists(['entity' => 'currency', 'context' => $context->getContext(), 'criteria' => $currencyCriteria]))
            ->add(self::SHIPPING_METHOD_ID, new EntityExists(['entity' => 'shipping_method', 'context' => $context->getContext(), 'criteria' => $shippingMethodCriteria]))
            ->add(self::PAYMENT_METHOD_ID, new EntityExists(['entity' => 'payment_method', 'context' => $context->getContext(), 'criteria' => $paymentMethodCriteria]))
            ->add(self::BILLING_ADDRESS_ID, new EntityExists(['entity' => 'customer_address', 'context' => $context->getContext(), 'criteria' => $addressCriteria]))
            ->add(self::SHIPPING_ADDRESS_ID, new EntityExists(['entity' => 'customer_address', 'context' => $context->getContext(), 'criteria' => $addressCriteria]))
            ->add(self::COUNTRY_ID, new EntityExists(['entity' => 'country', 'context' => $context->getContext()]))
            ->add(self::STATE_ID, new EntityExists(['entity' => 'country_state', 'context' => $context->getContext()]))
        ;

        $this->validator->validate($parameters, $definition);

        if (Feature::isActive('FEATURE_NEXT_10058') && $customer = $context->getCustomer()) {
            $this->contextPersister->save($context->getToken(), $parameters, $customer->getId());
        } else {
            $this->contextPersister->save($context->getToken(), $parameters);
        }

        $event = new SalesChannelContextSwitchEvent($context, $data);
        $this->eventDispatcher->dispatch($event);

        return new ContextTokenResponse($context->getToken());
    }
}
