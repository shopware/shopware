<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @Decoratable
 */
class ContactFormValidationFactory implements DataValidationFactoryInterface
{
    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->createContactFormValidation('contact_form.create', $context->getContext());
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->createContactFormValidation('contact_form.update', $context->getContext());
    }

    private function createContactFormValidation(string $validationName, Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition($validationName);

        $definition->add('salutationId', new NotBlank(), new EntityExists(['entity' => 'salutation', 'context' => $context]))
            ->add('firstName', new NotBlank())
            ->add('lastName', new NotBlank())
            ->add('email', new NotBlank(), new Email())
            ->add('phone', new NotBlank())
            ->add('subject', new NotBlank())
            ->add('comment', new NotBlank());

        return $definition;
    }
}
