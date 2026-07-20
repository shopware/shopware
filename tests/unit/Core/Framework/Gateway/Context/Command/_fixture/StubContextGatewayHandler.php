<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture;

use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\AbstractContextGatewayCommandHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @extends AbstractContextGatewayCommandHandler<StubContextGatewayCommand|StubContextGatewayFooCommand>
 */
#[Package('framework')]
class StubContextGatewayHandler extends AbstractContextGatewayCommandHandler
{
    public static function supportedCommands(): array
    {
        return [StubContextGatewayCommand::class, StubContextGatewayFooCommand::class];
    }

    /**
     * @param StubContextGatewayCommand|StubContextGatewayFooCommand $command
     */
    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters = []): void
    {
        if ($command instanceof StubContextGatewayFooCommand) {
            return;
        }

        $parameters['test'] = $command->data;
    }
}
