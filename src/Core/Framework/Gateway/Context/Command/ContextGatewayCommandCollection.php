<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @template T of AbstractContextGatewayCommand = AbstractContextGatewayCommand
 *
 * @extends Collection<T>
 */
#[Package('framework')]
class ContextGatewayCommandCollection extends Collection
{
    public function getSingleTokenCommand(): LoginCustomerCommand|RegisterCustomerCommand|null
    {
        return $this->getTokenCommands()->first();
    }

    /**
     * @return self<LoginCustomerCommand|RegisterCustomerCommand>
     */
    public function getTokenCommands(): self
    {
        return $this->filter(static fn (AbstractContextGatewayCommand $command) => $command instanceof LoginCustomerCommand || $command instanceof RegisterCustomerCommand);
    }

    /**
     * @return string[]
     */
    public function getCommandTypes(): array
    {
        return $this->map(static fn (AbstractContextGatewayCommand $command) => $command::getDefaultKeyName());
    }
}
