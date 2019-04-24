<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class OrderValidationService implements ValidationServiceInterface
{
    public function buildCreateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('order.create');

        $definition->add('tos', new NotBlank());

        return $definition;
    }

    public function buildUpdateValidation(Context $context): DataValidationDefinition
    {
        return $this->buildCreateValidation($context);
    }
}
