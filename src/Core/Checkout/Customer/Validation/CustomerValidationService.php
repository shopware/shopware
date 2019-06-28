<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\ValidationServiceInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class CustomerValidationService implements ValidationServiceInterface
{
    /**
     * @todo seems to be the usecase for the shopware api - import or so. maybe rename to CustomerImportValidationService
     *
     * @var CustomerProfileValidationService
     */
    private $profileValidation;
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        CustomerProfileValidationService $profileValidation,
        SystemConfigService $systemConfigService
    ) {
        $this->profileValidation = $profileValidation;
        $this->systemConfigService = $systemConfigService;
    }

    public function buildCreateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.create');

        $this->addConstraints($definition, $context);

        if ($this->systemConfigService->get('core.loginRegistration.requireEmailConfirmation')) {
            $definition->add('emailConfirmation', new EqualTo([
                'propertyPath' => 'email',
            ]));
        }

        if ($this->systemConfigService->get('core.loginRegistration.requirePasswordConfirmation')) {
            $definition->add('passwordConfirmation', new EqualTo([
                'propertyPath' => 'password',
            ]));
        }

        $profileDefinition = $this->profileValidation->buildCreateValidation($context);

        $this->merge($definition, $profileDefinition);

        return $definition;
    }

    public function buildUpdateValidation(Context $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('customer.update');

        $profileDefinition = $this->profileValidation->buildUpdateValidation($context);

        $this->merge($definition, $profileDefinition);

        $this->addConstraints($definition, $context);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition, Context $context): void
    {
        $definition
            ->add('email', new NotBlank(), new Email())
            ->add('active', new Type(['type' => 'boolean']));
    }

    /**
     * merges constraints from the second definition into the first validation definition
     */
    private function merge(DataValidationDefinition $definition, DataValidationDefinition $profileDefinition): void
    {
        foreach ($profileDefinition->getProperties() as $key => $constraints) {
            $parameters = [];
            $parameters[] = $key;
            $parameters = array_merge($parameters, $constraints);

            call_user_func_array([$definition, 'add'], $parameters);
        }
    }
}
