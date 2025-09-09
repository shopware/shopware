<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'app:shop-id:change',
    description: 'Change the shop ID by choosing a resolution strategy',
    /** @deprecated tag:v6.8.0 - Alias `app:url-change:resolve` will be removed */
    aliases: ['app:url-change:resolve'],
)]
#[Package('framework')]
class ChangeShopIdCommand extends Command
{
    public function __construct(private readonly Resolver $shopIdChangeResolver)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('strategy', InputArgument::OPTIONAL, 'The strategy that should be applied');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        if ($input->hasArgument('command') && $input->getArgument('command') === 'app:url-change:resolve') {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                $deprecationMessage = 'The command alias "app:url-change:resolve" is deprecated and will be removed in v6.8.0. Use "app:shop-id:change" instead.'
            );

            $io->warning($deprecationMessage);
        }

        $availableStrategies = $this->shopIdChangeResolver->getAvailableStrategies();
        $strategy = $input->getArgument('strategy');

        if ($strategy === null || !\array_key_exists($strategy, $availableStrategies)) {
            if ($strategy !== null) {
                $io->note(\sprintf('Strategy with name: "%s" not found.', $strategy));
            }

            $strategy = $io->choice(
                'Choose what strategy should be applied when changing the shop ID?',
                $availableStrategies
            );
        }

        $this->shopIdChangeResolver->resolve($strategy, Context::createCLIContext());

        $io->success('Strategy "' . $strategy . '" was applied successfully');

        return self::SUCCESS;
    }
}
