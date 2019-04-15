<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;

/**
 * @copyright 2019 dasistweb GmbH (https://www.dasistweb.de)
 */
class CustomerPasswordValidationService implements ValidationServiceInterface
{
    public function buildCreateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.password.create');
    }

    public function buildUpdateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.password.update');
    }

    public function addPasswordUpdateValidationDefinition(DataValidationDefinition $definition, Context $context): void
    {
        $definition;    //todo add passwords are the same, add password is equal to the current password
    }
}
