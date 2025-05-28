<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture;

use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\AbstractContextGatewayCommandHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @extends AbstractContextGatewayCommandHandler<TestContextGatewayCommand|TestContextGatewayFooCommand>
 */
#[CoversNothing]
#[Package('framework')]
class TestContextGatewayHandler extends AbstractContextGatewayCommandHandler
{
    public static function supportedCommands(): array
    {
        return [TestContextGatewayCommand::class, TestContextGatewayFooCommand::class];
    }

    /**
     * @param TestContextGatewayCommand|TestContextGatewayFooCommand $command
     */
    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters = []): void
    {
        if ($command instanceof TestContextGatewayFooCommand) {
            return;
        }

        $parameters['test'] = $command->data;
    }
}
