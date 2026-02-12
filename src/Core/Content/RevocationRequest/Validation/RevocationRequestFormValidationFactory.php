<?php declare(strict_types=1);

namespace Shopware\Core\Content\RevocationRequest\Validation;

use Shopware\Core\Content\ContactForm\Validation\ContactFormValidationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class RevocationRequestFormValidationFactory implements DataValidationFactoryInterface
{
    public const CREATE_VALIDATION_NAME = 'revocation_request_form.create';
    public const UPDATE_VALIDATION_NAME = 'revocation_request_form.update';

    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->createFormValidation(self::CREATE_VALIDATION_NAME, $context);
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->createFormValidation(self::UPDATE_VALIDATION_NAME, $context);
    }

    private function createFormValidation(string $name, SalesChannelContext $context): DataValidationDefinition
    {
        $validationDefinition = new DataValidationDefinition($name);
        $validationDefinition
            ->add('firstName', new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false))
            ->add('lastName', new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false))
            ->add('email', new NotBlank(), new Email())
            ->add('contractNumber', new NotBlank())
            ->add('comment', new NotBlank());

        $required = $this->systemConfigService->get('core.basicInformation.firstNameFieldRequired', $context->getSalesChannelId());
        if ($required) {
            $validationDefinition->set('firstName', new NotBlank(), new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false));
        }

        $required = $this->systemConfigService->get('core.basicInformation.lastNameFieldRequired', $context->getSalesChannelId());
        if ($required) {
            $validationDefinition->set('lastName', new NotBlank(), new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false));
        }

        $validationEvent = new BuildValidationEvent($validationDefinition, new DataBag(), $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validationDefinition;
    }
}
