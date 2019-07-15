<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactFormValidationService implements ValidationServiceInterface
{
    public function buildCreateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('contact_form.create');

        $definition->add('salutationId', new NotBlank(), new EntityExists(['entity' => 'salutation', 'context' => $context]))
            ->add('firstName', new NotBlank())
            ->add('lastName', new NotBlank())
            ->add('email', new NotBlank(), new Email())
            ->add('phone', new NotBlank())
            ->add('subject', new NotBlank())
            ->add('comment', new NotBlank());

        return $definition;
    }

    public function buildUpdateValidation(Context $context): DataValidationDefinition
    {
        return $this->buildCreateValidation($context);
    }
}
