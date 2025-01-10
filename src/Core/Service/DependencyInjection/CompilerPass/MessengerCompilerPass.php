<?php declare(strict_types=1);

namespace Shopware\Core\Service\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\CompilerPassConfigTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\MessengerRetryStrategy;
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use function PHPUnit\Framework\assertSame;

#[Package('core')]
class MessengerCompilerPass implements CompilerPassInterface
{
    use CompilerPassConfigTrait;

    //decorate the retry strategies created for each transport with our own
    //to handle important webhook events that should be retried
    public function process(ContainerBuilder $container): void
    {
        $retryStrategies = $container->getDefinition('messenger.retry_strategy_locator')->getArgument(0);
        foreach ($retryStrategies as $transport => $strategy) {
            if ($transport === 'failed') {
                continue;
            }

            assert($strategy instanceof ArgumentInterface);

            $ref = $strategy->getValues()[0];

            assert($ref instanceof Reference);

            $container->register($ref . '.decorated', MessengerRetryStrategy::class)
                ->setArgument(0, $strategy->getValues()[0])
                ->setArgument(1, new Reference(Connection::class));

            $strategy->setValues([new Reference($ref . '.decorated')]);
        }
    }
}
