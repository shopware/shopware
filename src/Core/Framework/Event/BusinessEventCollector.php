<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterUpdateEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\LogAwareBusinessEventInterface;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;

class BusinessEventCollector
{
    /**
     * @var EntityRepositoryInterface
     */
    private $stateRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentRepository;

    public function __construct(
        EntityRepositoryInterface $stateRepository,
        EntityRepositoryInterface $paymentRepository
    ) {
        $this->stateRepository = $stateRepository;
        $this->paymentRepository = $paymentRepository;
    }

    public function collect(Context $context): BusinessEventCollectorResponse
    {
        $events = [
            CheckoutOrderPlacedEvent::class,
            CustomerAccountRecoverRequestEvent::class,
            CustomerBeforeLoginEvent::class,
            CustomerChangedPaymentMethodEvent::class,
            CustomerDoubleOptInRegistrationEvent::class,
            CustomerGroupRegistrationAccepted::class,
            CustomerGroupRegistrationDeclined::class,
            CustomerLoginEvent::class,
            CustomerLogoutEvent::class,
            CustomerRegisterEvent::class,
            DoubleOptInGuestOrderEvent::class,
            GuestCustomerRegisterEvent::class,
            OrderStateMachineStateChangeEvent::class,
            ContactFormEvent::class,
            MailBeforeSentEvent::class,
            MailBeforeValidateEvent::class,
            MailSentEvent::class,
            NewsletterConfirmEvent::class,
            NewsletterRegisterEvent::class,
            NewsletterUpdateEvent::class,
            ProductExportLoggingEvent::class,
            UserRecoveryRequestEvent::class,
        ];

        $result = new BusinessEventCollectorResponse();
        foreach ($events as $class) {
            $definition = self::define($class);

            if (!$definition) {
                continue;
            }
            $result->set($definition->getName(), $definition);
        }

        $this->addOrderStateEvents($result, $context);

        return $result;
    }

    public static function define(string $class, ?string $name = null): ?BusinessEventDefinition
    {
        $instance = (new \ReflectionClass($class))
            ->newInstanceWithoutConstructor();

        if (!$instance instanceof BusinessEventInterface) {
            throw new \RuntimeException(sprintf('Event %s is not a business event', $class));
        }

        $name = $name ?? $instance->getName();
        if (!$name) {
            return null;
        }

        return new BusinessEventDefinition(
            $name,
            $class,
            $instance instanceof MailActionInterface,
            $instance instanceof LogAwareBusinessEventInterface
        );
    }

    private function addOrderStateEvents(BusinessEventCollectorResponse $result, Context $context): void
    {
        $payments = $this->paymentRepository->search(new Criteria(), $context);

        $criteria = new Criteria();
        $criteria->addAssociation('stateMachine');

        $states = $this->stateRepository->search($criteria, $context);

        $sides = [
            //            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
        ];

        /** @var StateMachineStateEntity $state */
        foreach ($states as $state) {
            foreach ($sides as $side) {
                $machine = $state->getStateMachine();
                if (!$machine) {
                    continue;
                }

                $name = implode('.', [
                    $side,
                    $machine->getTechnicalName(),
                    $state->getTechnicalName(),
                ]);

                $definition = self::define(OrderStateMachineStateChangeEvent::class, $name);

                if (!$definition) {
                    continue;
                }

                $result->set($name, $definition);

                if ($machine->getTechnicalName() !== OrderTransactionStates::STATE_MACHINE) {
                    continue;
                }
            }
        }
    }
}
