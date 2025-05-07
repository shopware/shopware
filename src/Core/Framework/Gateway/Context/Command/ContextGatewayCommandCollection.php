<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<AbstractContextGatewayCommand>
 */
#[Package('framework')]
class ContextGatewayCommandCollection extends Collection
{
    public function getTokenCommand(): LoginCustomerCommand|RegisterCustomerCommand|null
    {
        /** @var LoginCustomerCommand|RegisterCustomerCommand|null $command */
        $command = $this->filter(static fn (AbstractContextGatewayCommand $command) => $command instanceof LoginCustomerCommand || $command instanceof RegisterCustomerCommand)->first();

        return $command;
    }
}
