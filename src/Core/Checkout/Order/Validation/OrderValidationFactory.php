<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Validation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('customer-order')]
class OrderValidationFactory implements DataValidationFactoryInterface
{
    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->createOrderValidation('order.create');
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->createOrderValidation('order.update');
    }

    private function createOrderValidation(string $validationName): DataValidationDefinition
    {
        $definition = new DataValidationDefinition($validationName);

        $definition->add('tos', new NotBlank());

        return $definition;
    }
}
