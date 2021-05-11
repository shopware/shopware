<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait BasicTestDataBehaviour
{
    public function getDeDeLanguageId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('language.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.translationCode.code', 'de-DE'));

        /** @var LanguageEntity $language */
        $language = $repository->search($criteria, Context::createDefaultContext())->first();

        return $language->getId();
    }

    abstract protected function getContainer(): ContainerInterface;

    protected function getValidPaymentMethodId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    protected function getInactivePaymentMethodId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', false));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    protected function getAvailablePaymentMethod(): PaymentMethodEntity
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true));
        $paymentMethods = $repository->search($criteria, Context::createDefaultContext())->getEntities();

        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->getAvailabilityRuleId() === null) {
                return $paymentMethod;
            }
        }

        throw new \LogicException('No available Payment method configured');
    }

    protected function getValidShippingMethodId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('shipping_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    protected function getAvailableShippingMethod(): ShippingMethodEntity
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('shipping_method.repository');

        $shippingMethods = $repository->search(
            (new Criteria())
                ->addAssociation('prices')
                ->addFilter(new EqualsFilter('shipping_method.prices.calculation', 1))
                ->addFilter(new EqualsFilter('active', true)),
            Context::createDefaultContext()
        )->getEntities();

        /** @var ShippingMethodEntity $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            if ($shippingMethod->getAvailabilityRuleId() !== null) {
                return $shippingMethod;
            }
        }

        throw new \LogicException('No available ShippingMethod configured');
    }

    protected function getValidSalutationId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('salutation.repository');

        $criteria = (new Criteria())->setLimit(1);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    protected function getLocaleIdOfSystemLanguage(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('language.repository');

        /** @var LanguageEntity $language */
        $language = $repository->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())->get(Defaults::LANGUAGE_SYSTEM);

        return $language->getLocaleId();
    }

    protected function getSnippetSetIdForLocale(string $locale): ?string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('snippet_set.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('iso', $locale))
            ->setLimit(1);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0] ?? null;
    }

    /**
     * @param string|null $salesChannelId (null when no saleschannel filtering)
     */
    protected function getValidCountryId(?string $salesChannelId = Defaults::SALES_CHANNEL): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('country.repository');

        $criteria = (new Criteria())->setLimit(1)
            ->addFilter(new EqualsFilter('taxFree', 0))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shippingAvailable', true));

        if ($salesChannelId !== null) {
            $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
        }

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    protected function getValidCategoryId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('category.repository');

        $criteria = (new Criteria())->setLimit(1);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    protected function getValidTaxId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('tax.repository');

        $criteria = (new Criteria())->setLimit(1);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    protected function getStateMachineState(string $stateMachine = OrderStates::STATE_MACHINE, string $state = OrderStates::STATE_OPEN): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('state_machine_state.repository');

        $criteria = new Criteria();
        $criteria
            ->setLimit(1)
            ->addFilter(new EqualsFilter('technicalName', $state))
            ->addFilter(new EqualsFilter('stateMachine.technicalName', $stateMachine));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }
}
