<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Validation;

use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;

class OrderValidationService implements ValidationServiceInterface
{
    public function buildCreateValidation(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('order.create');

        $definition->add('tos', new NotBlank());

        return $definition;
    }

    public function buildUpdateValidation(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->buildCreateValidation($context);
    }
}
