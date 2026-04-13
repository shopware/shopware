<?php declare(strict_types=1);

namespace Shopware\Core\Content\RevocationRequest\Validation;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ContactForm\Validation\ContactFormValidationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class RevocationRequestFormValidationFactory implements DataValidationFactoryInterface
{
    public const CREATE_VALIDATION_NAME = 'revocation_request_form.create';

    public const UPDATE_VALIDATION_NAME = 'revocation_request_form.update';

    public const FIRST_NAME_FIELD = 'core.basicInformation.firstNameFieldRequired';

    public const LAST_NAME_FIELD = 'core.basicInformation.lastNameFieldRequired';

    public const COMMENT_MAX_LENGTH = 4096;

    public const CONTRACT_NUMBER_MAX_LENGTH = 255;

    /**
     * see:
     * https://www.rfc-editor.org/rfc/rfc3696
     * 3. Restrictions on email addresses
     */
    public const EMAIL_MAX_LENGTH = 320;

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
            ->add(
                'firstName',
                new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false),
                new Length(min: 0, max: CustomerDefinition::MAX_LENGTH_FIRST_NAME)
            )
            ->add(
                'lastName',
                new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false),
                new Length(min: 0, max: CustomerDefinition::MAX_LENGTH_LAST_NAME)
            )
            ->add(
                'email',
                new NotBlank(),
                new Email(),
                new Length(min: 0, max: self::EMAIL_MAX_LENGTH)
            )
            ->add(
                'contractNumber',
                new NotBlank(),
                new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false),
                new Length(min: 0, max: self::CONTRACT_NUMBER_MAX_LENGTH)
            )
            ->add(
                'comment',
                new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false),
                new Length(min: 0, max: self::COMMENT_MAX_LENGTH)
            );

        if ($this->systemConfigService->get(self::FIRST_NAME_FIELD, $context->getSalesChannelId())) {
            $validationDefinition->set(
                'firstName',
                new NotBlank(),
                new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false),
                new Length(min: 0, max: CustomerDefinition::MAX_LENGTH_FIRST_NAME)
            );
        }

        if ($this->systemConfigService->get(self::LAST_NAME_FIELD, $context->getSalesChannelId())) {
            $validationDefinition->set(
                'lastName',
                new NotBlank(),
                new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false),
                new Length(min: 0, max: CustomerDefinition::MAX_LENGTH_LAST_NAME)
            );
        }

        $validationEvent = new BuildValidationEvent($validationDefinition, new DataBag(), $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validationDefinition;
    }
}
