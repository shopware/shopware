<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Handler;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingMethodCommand;
use Shopware\Core\Framework\Gateway\Context\ContextGatewayException;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class ChangeCheckoutOptionsCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly ExceptionLogger $exceptionLogger,
    ) {
    }

    /**
     * @param ChangeShippingMethodCommand|ChangePaymentMethodCommand $command
     */
    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        $technicalName = $command->technicalName;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        if ($command instanceof ChangeShippingMethodCommand) {
            $shippingMethodId = $this->shippingMethodRepository->searchIds($criteria, $context->getContext())->firstId();

            if ($shippingMethodId === null) {
                $this->exceptionLogger->logOrThrowException(ContextGatewayException::handlerException('Shipping method with technical name {{ technicalName }} not found', ['technicalName' => $technicalName]));

                return;
            }

            $parameters['shippingMethodId'] = $shippingMethodId;
        }

        if ($command instanceof ChangePaymentMethodCommand) {
            $paymentMethodId = $this->paymentMethodRepository->searchIds($criteria, $context->getContext())->firstId();

            if ($paymentMethodId === null) {
                $this->exceptionLogger->logOrThrowException(ContextGatewayException::handlerException('Payment method with technical name {{ technicalName }} not found', ['technicalName' => $technicalName]));

                return;
            }

            $parameters['paymentMethodId'] = $paymentMethodId;
        }
    }

    public static function supportedCommands(): array
    {
        return [
            ChangeShippingMethodCommand::class,
            ChangePaymentMethodCommand::class,
        ];
    }
}
