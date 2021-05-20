<?php declare(strict_types=1);

namespace Shopware\Docs\Command;

use Shopware\Docs\Convert\CredentialsService;
use Shopware\Docs\Convert\WikiApiService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveArticlesAndCategoriesCommand extends Command
{
    protected static $defaultName = 'docs:remove-all';

    /**
     * @var string
     */
    private $environment;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);

        $this->environment = (string) getenv('APP_ENV');
    }

    protected function configure(): void
    {
        $this->setDescription('Removes all categories and articles from configured root category id.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $credentialsService = new CredentialsService();
        if (!$credentialsService->credentialsFileExists()) {
            return self::SUCCESS;
        }

        $syncService = new WikiApiService($credentialsService->getCredentials(), $this->environment);
        $syncService->removeAllFromServer();

        return self::SUCCESS;
    }
}
