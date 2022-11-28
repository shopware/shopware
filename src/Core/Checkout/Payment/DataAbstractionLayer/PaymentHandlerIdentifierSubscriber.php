<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 */
class PaymentHandlerIdentifierSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'formatHandlerIdentifier',
        ];
    }

    public function formatHandlerIdentifier(EntityLoadedEvent $event): void
    {
        /** @var PaymentMethodEntity $entity */
        foreach ($event->getEntities() as $entity) {
            $this->setPaymentMethodHandlerRuntimeFields($entity);

            $explodedHandlerIdentifier = explode('\\', $entity->getHandlerIdentifier());

            $last = $explodedHandlerIdentifier[\count($explodedHandlerIdentifier) - 1];
            $entity->setShortName((new CamelCaseToSnakeCaseNameConverter())->normalize($last));

            if (\count($explodedHandlerIdentifier) < 2) {
                $entity->setFormattedHandlerIdentifier($entity->getHandlerIdentifier());

                continue;
            }

            /** @var string|null $firstHandlerIdentifier */
            $firstHandlerIdentifier = array_shift($explodedHandlerIdentifier);
            $lastHandlerIdentifier = array_pop($explodedHandlerIdentifier);
            if ($firstHandlerIdentifier === null || $lastHandlerIdentifier === null) {
                continue;
            }

            $formattedHandlerIdentifier = 'handler_'
                . mb_strtolower($firstHandlerIdentifier)
                . '_'
                . mb_strtolower($lastHandlerIdentifier);

            $entity->setFormattedHandlerIdentifier($formattedHandlerIdentifier);
        }
    }

    private function setPaymentMethodHandlerRuntimeFields(PaymentMethodEntity $paymentMethod): void
    {
        if ($paymentMethod->getAppPaymentMethod()) {
            $this->setFieldsByAppPaymentMethod($paymentMethod);

            return;
        }

        $handlerIdentifier = $paymentMethod->getHandlerIdentifier();

        if (\is_a($handlerIdentifier, SynchronousPaymentHandlerInterface::class, true)) {
            $paymentMethod->setSynchronous(true);
        }

        if (\is_a($handlerIdentifier, AsynchronousPaymentHandlerInterface::class, true)) {
            $paymentMethod->setAsynchronous(true);
        }

        if (\is_a($handlerIdentifier, PreparedPaymentHandlerInterface::class, true)) {
            $paymentMethod->setPrepared(true);
        }

        if (\is_a($handlerIdentifier, RefundPaymentHandlerInterface::class, true)) {
            $paymentMethod->setRefundable(true);
        }
    }

    private function setFieldsByAppPaymentMethod(PaymentMethodEntity $paymentMethod): void
    {
        if (!$paymentMethod->getAppPaymentMethod()) {
            return;
        }

        $appPaymentMethod = $paymentMethod->getAppPaymentMethod();

        if ($appPaymentMethod->getRefundUrl()) {
            $paymentMethod->setRefundable(true);
        }

        if ($appPaymentMethod->getValidateUrl() && $appPaymentMethod->getCaptureUrl()) {
            $paymentMethod->setPrepared(true);
        }

        if ($appPaymentMethod->getPayUrl() && $appPaymentMethod->getFinalizeUrl()) {
            $paymentMethod->setAsynchronous(true);
        }

        if ($paymentMethod->isAsynchronous()) {
            $paymentMethod->setSynchronous(true);
        }
    }
}
