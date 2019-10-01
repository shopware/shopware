<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\NotBlank;

class AddressValidationService implements ValidationServiceInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function buildCreateValidation(SalesChannelContext $context): DataValidationDefinition
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

        if ($this->systemConfigService->get('core.loginRegistration.showAdditionalAddressField1') && $this->systemConfigService->get('core.loginRegistration.additionalAddressField1Required')) {
            $definition->add('additionalAddressLine1', new NotBlank());
        }

        if ($this->systemConfigService->get('core.loginRegistration.showAdditionalAddressField2') && $this->systemConfigService->get('core.loginRegistration.additionalAddressField2Required')) {
            $definition->add('additionalAddressLine2', new NotBlank());
        }

        return $definition;
    }

    public function buildUpdateValidation(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('address.update');

        $this->buildCommonValidation($definition, $context)
            ->add('id', new NotBlank(), new EntityExists(['context' => $context->getContext(), 'entity' => 'customer_address']));

        return $definition;
    }

    private function buildCommonValidation(DataValidationDefinition $definition, SalesChannelContext $context): DataValidationDefinition
    {
        $definition
            ->add('salutationId', new EntityExists(['entity' => 'salutation', 'context' => $context->getContext()]))
            ->add('countryId', new EntityExists(['entity' => 'country', 'context' => $context->getContext()]))
            ->add('countryStateId', new EntityExists(['entity' => 'country_state', 'context' => $context->getContext()]));

        return $definition;
    }
}
