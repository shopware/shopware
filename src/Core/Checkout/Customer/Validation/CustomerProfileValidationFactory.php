<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\Annotation\Concept\DeprecationPattern\ReplaceDecoratedInterface;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @ReplaceDecoratedInterface(
 *     deprecatedInterface="ValidationServiceInterface",
 *     replacedBy="DataValidationFactoryInterface"
 * )
 * @Decoratable
 */
class CustomerProfileValidationFactory implements ValidationServiceInterface, DataValidationFactoryInterface
{
    /**
     * @var SalutationDefinition
     */
    private $salutationDefinition;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        SalutationDefinition $salutationDefinition,
        SystemConfigService $systemConfigService
    ) {
        $this->salutationDefinition = $salutationDefinition;
        $this->systemConfigService = $systemConfigService;
    }

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

    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.profile.create');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.profile.update');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    /**
     * @param Context|SalesChannelContext $context
     */
    private function addConstraints(DataValidationDefinition $definition, $context): void
    {
        if ($context instanceof SalesChannelContext) {
            $frameworkContext = $context->getContext();
            $salesChannelId = $context->getSalesChannel()->getId();
        } else {
            $frameworkContext = $context;
            $salesChannelId = null;
        }

        $definition
            ->add('salutationId', new NotBlank(), new EntityExists(['entity' => $this->salutationDefinition->getEntityName(), 'context' => $frameworkContext]))
            ->add('firstName', new NotBlank())
            ->add('lastName', new NotBlank());

        if ($this->systemConfigService->get('core.loginRegistration.showBirthdayField', $salesChannelId)
            && $this->systemConfigService->get('core.loginRegistration.birthdayFieldRequired', $salesChannelId)) {
            $definition
                ->add('birthdayDay', new GreaterThanOrEqual(['value' => 1]), new LessThanOrEqual(['value' => 31]))
                ->add('birthdayMonth', new GreaterThanOrEqual(['value' => 1]), new LessThanOrEqual(['value' => 12]))
                ->add('birthdayYear', new GreaterThanOrEqual(['value' => 1900]), new LessThanOrEqual(['value' => date('Y')]));
        }
    }
}
