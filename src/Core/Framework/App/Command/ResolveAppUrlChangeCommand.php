<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\AppUrlChangeResolver\Resolver;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class ResolveAppUrlChangeCommand extends Command
{
    protected static $defaultName = 'app:url-change:resolve';

    /**
     * @var Resolver
     */
    private $appUrlChangeResolver;

    public function __construct(Resolver $appUrlChangeResolverStrategy)
    {
        parent::__construct();

        $this->appUrlChangeResolver = $appUrlChangeResolverStrategy;
    }

    protected function configure(): void
    {
        $this->setDescription('Resolve changes in the app url and how the app system should handle it.')
            ->addArgument('strategy', InputArgument::OPTIONAL, 'The strategy that should be applied');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $availableStrategies = $this->appUrlChangeResolver->getAvailableStrategies();
        $strategy = $input->getArgument('strategy');

        if ($strategy === null || !\array_key_exists($strategy, $availableStrategies)) {
            if ($strategy !== null) {
                $io->note(sprintf('Strategy with name: "%s" not found.', $strategy));
            }

            $strategy = $io->choice(
                'Choose what strategy should be applied, to resolve the app url change?',
                $availableStrategies
            );
        }

        $this->appUrlChangeResolver->resolve($strategy, Context::createDefaultContext());

        $io->success('Strategy "' . $strategy . '" was applied successfully');

        return self::SUCCESS;
    }
}
