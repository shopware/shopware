<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\AppUrlChangeResolver\Resolver;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        /** @var string|null $strategy */
        $strategy = $input->getArgument('strategy');

        if ($strategy === null || !array_key_exists($strategy, $availableStrategies)) {
            if ($strategy !== null) {
                $io->note('Strategy with name: "' . $strategy . '" not found.');
            }

            $strategy = $io->choice(
                'Choose what strategy should be applied, to resolve the app url change?',
                $availableStrategies
            );
        }

        $this->appUrlChangeResolver->resolve($strategy, Context::createDefaultContext());

        $io->success('Strategy "' . $strategy . '" was applied successfully');

        return 0;
    }
}
