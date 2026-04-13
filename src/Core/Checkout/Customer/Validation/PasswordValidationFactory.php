<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('checkout')]
class PasswordValidationFactory implements DataValidationFactoryInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('password.create');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('password.update');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition, SalesChannelContext $context): void
    {
        $minLength = $this->systemConfigService->getInt('core.loginRegistration.passwordMinLength', $context->getSalesChannelId());
        if ($minLength < 0) {
            $minLength = null;
        }
        $definition->add('password', new NotBlank(), new Length(min: $minLength, max: PasswordHasherInterface::MAX_PASSWORD_LENGTH, maxMessage: 'VIOLATION::PASSWORD_IS_TOO_LONG'));
    }
}
