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
    public function getRegisterCommand(): ?RegisterCustomerCommand
    {
        return $this->filter(static fn (AbstractContextGatewayCommand $command) => $command instanceof RegisterCustomerCommand)->first();
    }

    public function getLoginCommand(): ?LoginCustomerCommand
    {
        return $this->filter(static fn (AbstractContextGatewayCommand $command) => $command instanceof LoginCustomerCommand)->first();
    }
}
