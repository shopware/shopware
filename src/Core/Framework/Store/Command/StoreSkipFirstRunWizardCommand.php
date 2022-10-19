<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;

class StoreSkipFirstRunWizardCommand extends Command
{
    public static $defaultName = 'store:skip-first-run-wizard';

    private FirstRunWizardClient $frwClient;

    /**
     * @internal
     */
    public function __construct(
        FirstRunWizardClient $frwClient
    ) {
        $this->frwClient = $frwClient;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $context = Context::createDefaultContext();

        try {
            $this->frwClient->finishFrw(false, $context);
        } catch (\Exception $e) {
            return Command::FAILURE;
        }

        $io->success('First run wizard skipped.');

        return Command::SUCCESS;
    }
}
