<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerPasswordMatches;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\EqualTo;

/**
 * @copyright 2019 dasistweb GmbH (https://www.dasistweb.de)
 */
class CustomerEmailValidationService
{
    public function buildCreateValidation(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.email.create');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    public function buildUpdateValidation(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.email.update');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition, SalesChannelContext $context): void
    {
        $definition->add('email', new CustomerEmailUnique(['context' => $context->getContext()]), new EqualTo(['propertyPath' => 'emailConfirmation']))
            ->add('password', new CustomerPasswordMatches(['context' => $context]));
    }
}
