<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal (flag:FEATURE_NEXT_15170)
 */
class PaymentDistinguishableNameSubscriber implements EventSubscriberInterface
{
    private EntityRepositoryInterface $paymentMethodRepository;

    public function __construct(EntityRepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_TRANSLATION_WRITTEN_EVENT => 'generateDistinguishablePaymentNames',
            PaymentEvents::PAYMENT_METHOD_TRANSLATION_DELETED_EVENT => 'removeDistinguishablePaymentNames',
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'addDistinguishablePaymentName',
        ];
    }

    public function generateDistinguishablePaymentNames(EntityWrittenEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_15170')) {
            return;
        }

        if ($this->hasEventChangedPaymentMethodName($event)) {
            $this->generateOrRemoveDistinguishablePaymentNames($event->getContext());
        }
    }

    public function removeDistinguishablePaymentNames(EntityDeletedEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_15170')) {
            return;
        }

        $this->generateOrRemoveDistinguishablePaymentNames($event->getContext());
    }

    public function addDistinguishablePaymentName(EntityLoadedEvent $event): void
    {
        /** @var PaymentMethodEntity $payment */
        foreach ($event->getEntities() as $payment) {
            if ($payment->getTranslation('distinguishableName') === null) {
                $payment->addTranslated('distinguishableName', $payment->getTranslation('name'));
            }
            if ($payment->getDistinguishableName() === null) {
                $payment->setDistinguishableName($payment->getName());
            }
        }
    }

    private function generateOrRemoveDistinguishablePaymentNames(Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context): void {
            $payments = $this->getInstalledPayments($context);
            $paymentNames = $this->getExistingPaymentNames($payments);

            $upsertablePayments = $this->generateDistinguishableNamesPayload($payments, $paymentNames);

            $this->paymentMethodRepository->upsert($upsertablePayments, $context);
        });
    }

    private function simplifyPaymentName(string $paymentName): string
    {
        return mb_strtolower(
            str_replace(' ', '', $paymentName)
        );
    }

    private function getInstalledPayments(Context $context): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria
            ->addAssociation('translations')
            ->addAssociation('plugin.translations')
            ->addAssociation('appPaymentMethod.app.translations');

        /** @var PaymentMethodCollection $payments */
        $payments = $this->paymentMethodRepository
            ->search($criteria, $context)
            ->getEntities();

        return $payments;
    }

    private function hasEventChangedPaymentMethodName(EntityWrittenEvent $event): bool
    {
        foreach ($event->getPayloads() as $payload) {
            if (\array_key_exists('name', $payload)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string,string>> $paymentNames
     */
    private function generateDistinguishableNamesPayload(PaymentMethodCollection $payments, array $paymentNames): array
    {
        $upsertablePayments = [];
        foreach ($payments as $payment) {
            if ($payment->getTranslations() === null) {
                continue;
            }

            foreach ($payment->getTranslations() as $translation) {
                $languageId = $translation->getLanguageId();
                $paymentName = $translation->getName() ?? $payment->getTranslation('name');

                $paymentNamesToCompare = $this->removeCurrentPayment($paymentNames[$languageId], $payment);

                if (!\in_array($this->simplifyPaymentName($paymentName), $paymentNamesToCompare, true)) {
                    continue;
                }

                $upsertablePayments[] = [
                    'id' => $payment->getId(),
                    'distinguishableName' => [
                        $languageId => $this->generatePluginBasedName($payment, $languageId, $paymentName)
                            ?? $this->generateAppBasedName($payment, $languageId, $paymentName),
                    ],
                ];
            }
        }

        return $upsertablePayments;
    }

    private function removeCurrentPayment(array $paymentNames, PaymentMethodEntity $payment): array
    {
        unset($paymentNames[$payment->getId()]);

        return $paymentNames;
    }

    /**
     * @return array<string, array<string,string>>
     */
    private function getExistingPaymentNames(PaymentMethodCollection $payments): array
    {
        /** @var array<string, array<string,string>> $paymentNames */
        $paymentNames = [];

        foreach ($payments as $payment) {
            if ($payment->getTranslations() === null) {
                continue;
            }

            foreach ($payment->getTranslations() as $translation) {
                $languageId = $translation->getLanguageId();
                $paymentNames[$languageId][$payment->getId()] = $this->simplifyPaymentName($translation->getName() ?? $payment->getTranslation('name'));
            }
        }

        return $paymentNames;
    }

    private function generatePluginBasedName(PaymentMethodEntity $payment, string $languageId, string $paymentName): ?string
    {
        if ($payment->getPlugin() === null) {
            return null;
        }

        if ($payment->getPlugin()->getTranslations() === null) {
            return null;
        }

        $pluginLabel = $payment->getPlugin()->getTranslations()->filterByProperty('languageId', $languageId)->first()
            ? $payment->getPlugin()->getTranslations()->filterByProperty('languageId', $languageId)->first()->getLabel()
            : $payment->getPlugin()->getTranslation('label');

        return sprintf(
            '%s | %s',
            $paymentName,
            $pluginLabel
        );
    }

    private function generateAppBasedName(PaymentMethodEntity $payment, string $languageId, string $paymentName): ?string
    {
        if ($payment->getAppPaymentMethod() === null) {
            return null;
        }

        if ($payment->getAppPaymentMethod()->getApp() === null) {
            return null;
        }

        if ($payment->getAppPaymentMethod()->getApp()->getTranslations() === null) {
            return null;
        }

        $appLabel = $payment->getAppPaymentMethod()->getApp()->getTranslations()->filterByProperty('languageId', $languageId)->first()
            ? $payment->getAppPaymentMethod()->getApp()->getTranslations()->filterByProperty('languageId', $languageId)->first()->getLabel()
            : $payment->getAppPaymentMethod()->getApp()->getTranslation('label');

        return sprintf(
            '%s | %s',
            $paymentName,
            $appLabel
        );
    }
}
