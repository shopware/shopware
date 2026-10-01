<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
abstract class AbstractAppActivationCommand extends Command
{
    public function __construct(
        private readonly AppStorage $appStorage,
        private readonly string $action,
    ) {
        parent::__construct();
    }

    abstract public function runAction(string $appId, Context $context): void;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createCLIContext();

        $appName = $input->getArgument('name');

        $app = $this->appStorage->findByName($appName, $context);

        if (!$app) {
            $io->error("No app found for \"{$appName}\".");

            return self::FAILURE;
        }

        $this->runAction($app->getId(), $context);

        $io->success(\sprintf('App %sd successfully.', $this->action));

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the app, has also to be the name of the folder under
                which the app can be found under custom/apps');
    }
}
