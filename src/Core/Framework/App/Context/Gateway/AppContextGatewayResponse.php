<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Context\Gateway;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @phpstan-type ContextGatewayCommand array{command: string, payload: array<mixed>}
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final class AppContextGatewayResponse extends Struct
{
    /**
     * @param array<ContextGatewayCommand> $commands
     *
     * @internal
     */
    public function __construct(protected array $commands = [])
    {
    }

    /**
     * @return array<ContextGatewayCommand>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @param ContextGatewayCommand $command
     */
    public function add(array $command): void
    {
        $this->commands[] = $command;
    }

    /**
     * @param array<ContextGatewayCommand> $commands
     */
    public function merge(array $commands): void
    {
        $this->commands = \array_merge($this->commands, $commands);
    }
}
