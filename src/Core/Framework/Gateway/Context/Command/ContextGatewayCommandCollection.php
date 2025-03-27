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
        foreach ($this->elements as $command) {
            if ($command instanceof RegisterCustomerCommand) {
                return $command;
            }
        }

        return null;
    }
}
