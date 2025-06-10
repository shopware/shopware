<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Registry;

use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\AbstractContextGatewayCommandHandler;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * @internal
 */
#[Package('framework')]
class ContextGatewayCommandRegistry
{
    /**
     * @var array<string, AbstractContextGatewayCommandHandler>
     */
    private array $handlers = [];

    /**
     * @var array<string, class-string<AbstractContextGatewayCommand>>
     */
    private array $appCommands = [];

    /**
     * @internal
     *
     * @param iterable<AbstractContextGatewayCommandHandler> $handlers
     */
    public function __construct(
        #[AutowireIterator('shopware.context.gateway.command')]
        iterable $handlers,
    ) {
        foreach ($handlers as $handler) {
            /** @var class-string<AbstractContextGatewayCommand> $command */
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

    public function get(string $key): AbstractContextGatewayCommandHandler
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
     * @return array<string, AbstractContextGatewayCommandHandler>
     */
    public function all(): array
    {
        return $this->handlers;
    }
}
