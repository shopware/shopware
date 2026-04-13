<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Validation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('checkout')]
class OrderValidationFactory implements DataValidationFactoryInterface
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
        return $this->createOrderValidation('order.create', $context);
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->createOrderValidation('order.update', $context);
    }

    private function createOrderValidation(string $validationName, SalesChannelContext $context): DataValidationDefinition
    {
        $validateTos = $this->systemConfigService->getBool('core.cart.showTosCheckbox', $context->getSalesChannelId());

        $definition = new DataValidationDefinition($validationName);

        if ($validateTos) {
            $definition->add('tos', new NotBlank());
        }

        return $definition;
    }
}
