<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Checkout\Gateway;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @psalm-type CheckoutGatewayCommand = array{command: string, payload: array<mixed>}
 *
 * @internal only for use by the app-system
 */
#[Package('checkout')]
final class AppCheckoutGatewayResponse extends Struct
{
    /**
     * @param CheckoutGatewayCommand[] $commands
     *
     * @internal
     */
    public function __construct(protected array $commands = [])
    {
    }

    /**
     * @return CheckoutGatewayCommand[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @param CheckoutGatewayCommand $command
     */
    public function add(array $command): void
    {
        $this->commands[] = $command;
    }

    /**
     * @param CheckoutGatewayCommand[] $commands
     */
    public function merge(array $commands): void
    {
        $this->commands = \array_merge($this->commands, $commands);
    }
}
