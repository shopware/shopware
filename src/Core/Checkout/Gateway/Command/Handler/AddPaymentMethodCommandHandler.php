<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command\Handler;

use Shopware\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Checkout\Gateway\Command\AddPaymentMethodCommand;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class AddPaymentMethodCommandHandler extends AbstractCheckoutGatewayCommandHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $paymentMethodRepository,
        private readonly ExceptionLogger $logger,
    ) {
    }

    public static function supportedCommands(): array
    {
        return [
            AddPaymentMethodCommand::class,
        ];
    }

    /**
     * @param AddPaymentMethodCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        $technicalName = $command->paymentMethodTechnicalName;
        $methods = $response->getAvailablePaymentMethods();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        $criteria->addAssociation('appPaymentMethod.app');

        /** @var PaymentMethodEntity|null $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context->getContext())->first();

        if (!$paymentMethod) {
            $this->logger->logOrThrowException(
                CheckoutGatewayException::handlerException('Payment method "{{ technicalName }}" not found', ['technicalName' => $technicalName])
            );

            return;
        }

        $methods->add($paymentMethod);
    }
}
