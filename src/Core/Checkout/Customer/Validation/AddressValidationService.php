<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class AddressValidationService implements ValidationServiceInterface
{
    public function buildCreateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('address.create');

        $this->buildCommonValidation($definition, $context)
            ->add('salutationId', new NotBlank())
            ->add('firstName', new NotBlank())
            ->add('lastName', new NotBlank())
            ->add('street', new NotBlank())
            ->add('zipcode', new NotBlank())
            ->add('city', new NotBlank())
            ->add('countryId', new NotBlank());

        return $definition;
    }

    public function buildUpdateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('address.update');

        $this->buildCommonValidation($definition, $context)
            ->add('id', new NotBlank(), new EntityExists(['context' => $context, 'entity' => 'customer_address']));

        return $definition;
    }

    private function buildCommonValidation(DataValidationDefinition $definition, Context $context): DataValidationDefinition
    {
        $definition
            ->add('salutationId', new EntityExists(['entity' => 'salutation', 'context' => $context]))
            ->add('countryId', new EntityExists(['entity' => 'country', 'context' => $context]))
            ->add('countryStateId', new EntityExists(['entity' => 'country_state', 'context' => $context]));

        return $definition;
    }
}
