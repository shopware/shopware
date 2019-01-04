<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Helper;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PaymentMethodHelper
{
    /**
     * @var RepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepo;

    public function __construct(RepositoryInterface $pluginRepo, RepositoryInterface $paymentMethodRepo)
    {
        $this->pluginRepo = $pluginRepo;
        $this->paymentMethodRepo = $paymentMethodRepo;
    }

    public function create(string $pluginName, PaymentMethodEntity $paymentMethod, Context $context): void
    {
        $pluginId = $this->getPluginId($pluginName, $context);

        $paymentMethod->setPluginId($pluginId);

        $paymentMethodData = $this->getPaymentMethodUpsertData($paymentMethod);

        $this->paymentMethodRepo->upsert([$paymentMethodData], $context);
    }

    public function setPaymentMethodIsActiveById(bool $active, string $paymentMethodId, Context $context): void
    {
        $paymentMethodData = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        $this->paymentMethodRepo->update([$paymentMethodData], $context);
    }

    public function setPaymentMethodIsActiveByTechnicalName(bool $active, string $paymentMethodName, Context $context): void
    {
        $paymentMethodId = $this->getPaymentMethodId($paymentMethodName, $context);

        $this->setPaymentMethodIsActiveById($active, $paymentMethodId, $context);
    }

    private function getPluginId(string $pluginName, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $pluginName));
        $pluginIds = $this->pluginRepo->searchIds($criteria, $context)->getIds();

        return array_pop($pluginIds);
    }

    private function getPaymentMethodUpsertData(PaymentMethodEntity $paymentMethod): array
    {
        $paymentMethodData = json_decode(json_encode($paymentMethod), true);

        if ($paymentMethodData['position'] === null) {
            unset($paymentMethodData['position']);
        }

        unset(
            $paymentMethodData['_class'],
            $paymentMethodData['plugin'],
            $paymentMethodData['orderTransactions'],
            $paymentMethodData['orders'],
            $paymentMethodData['customers'],
            $paymentMethodData['salesChannelDefaultAssignments'],
            $paymentMethodData['salesChannels'],
            $paymentMethodData['_uniqueIdentifier'],
            $paymentMethodData['viewData']
        );

        return $paymentMethodData;
    }

    private function getPaymentMethodId(string $paymentMethodName, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $paymentMethodName));
        $paymentMethodIds = $this->paymentMethodRepo->searchIds($criteria, $context)->getIds();

        return array_pop($paymentMethodIds);
    }
}
