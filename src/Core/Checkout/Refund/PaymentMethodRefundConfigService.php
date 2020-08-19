<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Refund;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class PaymentMethodRefundConfigService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRefundConfigRepository;

    public function __construct(EntityRepositoryInterface $paymentMethodRefundConfigRepository)
    {
        $this->paymentMethodRefundConfigRepository = $paymentMethodRefundConfigRepository;
    }

    public function upsertPaymentMethodRefundConfigFromYaml(
        string $paymentMethodId,
        string $technicalName,
        string $yamlFilePath,
        Context $context
    ): void {
        try {
            $options = Yaml::parseFile($yamlFilePath);
        } catch (ParseException $e) {
            throw new \RuntimeException(sprintf(
                'Yaml file %s could not be parsed: %s',
                $yamlFilePath,
                $e->getMessage()
            ), 0, $e);
        }

        $paymentMethodRefundConfigProto = [
            'paymentMethodId' => $paymentMethodId,
            'technicalName' => $technicalName,
            'options' => $options,
        ];
        $existingRefundConfig = $this->getPaymentMethodRefundConfig($paymentMethodId, $technicalName, $context);
        if ($existingRefundConfig !== null) {
            $paymentMethodRefundConfigProto['id'] = $existingRefundConfig->getId();
        }

        $this->paymentMethodRefundConfigRepository->upsert([$paymentMethodRefundConfigProto], $context);
    }

    private function getPaymentMethodRefundConfig(
        string $paymentMethodId,
        string $technicalName,
        Context $context
    ): ?PaymentMethodRefundConfigEntity {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentMethodId', $paymentMethodId),
            new EqualsFilter('technicalName', $technicalName)
        );

        return $this->paymentMethodRefundConfigRepository->search($criteria, $context)->first();
    }
}
