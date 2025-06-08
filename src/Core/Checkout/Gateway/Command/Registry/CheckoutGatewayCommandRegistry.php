<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command\Registry;

use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Checkout\Gateway\Command\Handler\AbstractCheckoutGatewayCommandHandler;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class CheckoutGatewayCommandRegistry
{
    /**
     * @var array<string, AbstractCheckoutGatewayCommandHandler>
     */
    private array $handlers = [];

    /**
     * @var array<string, class-string<AbstractCheckoutGatewayCommand>>
     */
    private array $appCommands = [];

    /**
     * @internal
     *
     * @param iterable<AbstractCheckoutGatewayCommandHandler> $handlers
     */
    public function __construct(
        iterable $handlers,
    ) {
        foreach ($handlers as $handler) {
            /** @var class-string<AbstractCheckoutGatewayCommand> $command */
            foreach ($handler::supportedCommands() as $command) {
                $this->handlers[$command::getDefaultKeyName()] = $handler;
                $this->appCommands[$command::getDefaultKeyName()] = $command;
            }
        }
    }

    public function has(string $key): bool
    {
        return isset($this->handlers[$key]);
    }

    public function get(string $key): AbstractCheckoutGatewayCommandHandler
    {
        return $this->handlers[$key];
    }

    public function hasAppCommand(string $key): bool
    {
        return isset($this->appCommands[$key]);
    }

    public function getAppCommand(string $key): string
    {
        return $this->appCommands[$key];
    }

    /**
     * @return array<string, AbstractCheckoutGatewayCommandHandler>
     */
    public function all(): array
    {
        return $this->handlers;
    }
}
