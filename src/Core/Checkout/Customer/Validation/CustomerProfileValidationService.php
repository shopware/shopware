<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;

/**
 * @copyright 2019 dasistweb GmbH (https://www.dasistweb.de)
 */
class CustomerProfileValidationService implements ValidationServiceInterface
{
    public function buildCreateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.profile.create');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    public function buildUpdateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.profile.update');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition, Context $context): void
    {
        $definition
            ->add('salutationId', new NotBlank(), new EntityExists(['entity' => 'salutation', 'context' => $context]))
            ->add('title', new Optional())
            ->add('firstName', new NotBlank())
            ->add('lastName', new NotBlank())
            ->add('birthdayDay', new GreaterThanOrEqual(['value' => 1]), new LessThanOrEqual(['value' => 31]))
            ->add('birthdayMonth', new GreaterThanOrEqual(['value' => 1]), new LessThanOrEqual(['value' => 12]))
            ->add('birthdayYear', new LessThanOrEqual(['value' => date('Y')]));
    }
}
