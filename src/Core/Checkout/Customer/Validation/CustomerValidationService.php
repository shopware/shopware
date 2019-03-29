<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use function date;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class CustomerValidationService implements ValidationServiceInterface
{
    public function buildCreateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.create');

        $this->buildCommon($definition, $context)
            ->add('firstName', new NotBlank())
            ->add('lastName', new NotBlank())
            ->add('email', new NotBlank())
            ->add('salutationId', new NotBlank());

        return $definition;
    }

    public function buildUpdateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.update');

        return $this->buildCommon($definition, $context);
    }

    private function buildCommon(DataValidationDefinition $definition, Context $context): DataValidationDefinition
    {
        $definition
            ->add('email', new Email())
            ->add('salutationId', new EntityExists(['entity' => 'salutation', 'context' => $context]))
            ->add('active', new Type(['type' => 'boolean']))
            ->add('birthdayDay', new GreaterThanOrEqual(['value' => 1]), new LessThanOrEqual(['value' => 31]))
            ->add('birthdayMonth', new GreaterThanOrEqual(['value' => 1]), new LessThanOrEqual(['value' => 12]))
            ->add('birthdayYear', new LessThanOrEqual(['value' => date('Y')]));

        return $definition;
    }
}
