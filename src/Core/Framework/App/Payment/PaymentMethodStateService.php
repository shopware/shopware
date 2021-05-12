<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @internal only for use by the app-system
 */
class PaymentMethodStateService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    public function __construct(EntityRepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function activatePaymentMethods(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appPaymentMethod.appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', false));

        $templates = $this->paymentMethodRepository->searchIds($criteria, $context);

        $updateSet = array_map(function (string $id) {
            return ['id' => $id, 'active' => true];
        }, $templates->getIds());

        $this->paymentMethodRepository->update($updateSet, $context);
    }

    public function deactivatePaymentMethods(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appPaymentMethod.appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        $templates = $this->paymentMethodRepository->searchIds($criteria, $context);

        $updateSet = array_map(function (string $id) {
            return ['id' => $id, 'active' => false];
        }, $templates->getIds());

        $this->paymentMethodRepository->update($updateSet, $context);
    }
}
