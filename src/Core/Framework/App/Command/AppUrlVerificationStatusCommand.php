<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\Url\AppUrlVerificationPrinter;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'app:url:status',
    description: 'Check the status of the app URL',
)]
#[Package('framework')]
class AppUrlVerificationStatusCommand extends Command
{
    public function __construct(
        private readonly AppUrlVerifier $appUrlVerifier,
        private readonly AppUrlVerificationPrinter $printer,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $state = $this->appUrlVerifier->getCurrentState();

        if ($state === null) {
            $io->warning('No verification state found. Run "app:url:verify" or if you already have, check you have a persistent cache configured.');

            return Command::SUCCESS;
        }

        $this->printer->print($io, $state, false);

        return Command::SUCCESS;
    }
}
