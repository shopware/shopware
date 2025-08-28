<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('checkout')]
class AddressValidationFactory implements DataValidationFactoryInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('address.create');

        $this->buildCommonValidation($definition, $context);

        return $definition;
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('address.update');

        $this->buildCommonValidation($definition, $context)
            ->add('id', new NotBlank(), new EntityExists(entity: 'customer_address', context: $context->getContext()));

        return $definition;
    }

    private function buildCommonValidation(DataValidationDefinition $definition, SalesChannelContext $context): DataValidationDefinition
    {
        $frameworkContext = $context->getContext();
        $salesChannelId = $context->getSalesChannelId();

        $definition
            ->add('salutationId', new EntityExists(entity: 'salutation', context: $frameworkContext))
            ->add('countryId', new EntityExists(entity: 'country', context: $frameworkContext))
            ->add('countryStateId', new EntityExists(entity: 'country_state', context: $frameworkContext))
            ->add('firstName', new NotBlank(message: 'VIOLATION::FIRST_NAME_IS_BLANK_ERROR'))
            ->add('lastName', new NotBlank(message: 'VIOLATION::LAST_NAME_IS_BLANK_ERROR'))
            ->add('street', new NotBlank(message: 'VIOLATION::STREET_IS_BLANK_ERROR'))
            ->add('city', new NotBlank(message: 'VIOLATION::CITY_IS_BLANK_ERROR'))
            ->add('countryId', new NotBlank(message: 'VIOLATION::COUNTRY_IS_BLANK_ERROR'), new EntityExists(entity: 'country', context: $frameworkContext))
            ->add('firstName', new Length(max: CustomerAddressDefinition::MAX_LENGTH_FIRST_NAME, exactMessage: 'VIOLATION::FIRST_NAME_IS_TOO_LONG'))
            ->add('lastName', new Length(max: CustomerAddressDefinition::MAX_LENGTH_LAST_NAME, exactMessage: 'VIOLATION::LAST_NAME_IS_TOO_LONG'))
            ->add('title', new Length(max: CustomerAddressDefinition::MAX_LENGTH_TITLE, exactMessage: 'VIOLATION::TITLE_IS_TOO_LONG'))
            ->add('zipcode', new Length(max: CustomerAddressDefinition::MAX_LENGTH_ZIPCODE, exactMessage: 'VIOLATION::ZIPCODE_IS_TOO_LONG'));

        if ($this->systemConfigService->get('core.loginRegistration.showAdditionalAddressField1', $salesChannelId)
            && $this->systemConfigService->get('core.loginRegistration.additionalAddressField1Required', $salesChannelId)) {
            $definition->add('additionalAddressLine1', new NotBlank(message: 'VIOLATION::ADDITIONAL_ADDR1_IS_BLANK_ERROR'));
        }

        if ($this->systemConfigService->get('core.loginRegistration.showAdditionalAddressField2', $salesChannelId)
            && $this->systemConfigService->get('core.loginRegistration.additionalAddressField2Required', $salesChannelId)) {
            $definition->add('additionalAddressLine2', new NotBlank(message: 'VIOLATION::ADDITIONAL_ADDR2_IS_BLANK_ERROR'));
        }

        if ($this->systemConfigService->get('core.loginRegistration.showPhoneNumberField', $salesChannelId)
            && $this->systemConfigService->get('core.loginRegistration.phoneNumberFieldRequired', $salesChannelId)) {
            $definition->add('phoneNumber', new NotBlank(message: 'VIOLATION::PHONE_NUMBER_IS_BLANK_ERROR'));
        }

        if ($this->systemConfigService->get('core.loginRegistration.showPhoneNumberField', $salesChannelId)) {
            $definition->add('phoneNumber', new Length(max: CustomerAddressDefinition::MAX_LENGTH_PHONE_NUMBER, exactMessage: 'VIOLATION::PHONE_NUMBER_IS_TOO_LONG'));
        }

        return $definition;
    }
}
