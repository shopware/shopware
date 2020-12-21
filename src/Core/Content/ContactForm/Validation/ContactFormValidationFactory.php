<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Decoratable
 */
class ContactFormValidationFactory implements DataValidationFactoryInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfigService = $systemConfigService;
    }

    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->createContactFormValidation('contact_form.create', $context);
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->createContactFormValidation('contact_form.update', $context);
    }

    private function createContactFormValidation(string $validationName, SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition($validationName);

        $definition->add('salutationId', new NotBlank(), new EntityExists(['entity' => 'salutation', 'context' => $context->getContext()]))
            ->add('email', new NotBlank(), new Email())
            ->add('subject', new NotBlank())
            ->add('comment', new NotBlank());

        $required = $this->systemConfigService->get('core.basicInformation.firstNameFieldRequired', $context->getSalesChannel()->getId());
        if ($required) {
            $definition->add('firstName', new NotBlank());
        }

        $required = $this->systemConfigService->get('core.basicInformation.lastNameFieldRequired', $context->getSalesChannel()->getId());
        if ($required) {
            $definition->add('lastName', new NotBlank());
        }

        $required = $this->systemConfigService->get('core.basicInformation.phoneNumberFieldRequired', $context->getSalesChannel()->getId());
        if ($required) {
            $definition->add('phone', new NotBlank());
        }

        $validationEvent = new BuildValidationEvent($definition, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $definition;
    }
}
