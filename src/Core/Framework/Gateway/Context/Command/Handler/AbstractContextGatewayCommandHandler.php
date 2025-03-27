<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Handler;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
abstract class AbstractContextGatewayCommandHandler
{
    /**
     * @param array<string, mixed> $parameters
     */
    abstract public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void;

    /**
     * @return array<class-string<AbstractContextGatewayCommand>>
     */
    abstract public static function supportedCommands(): array;
}
