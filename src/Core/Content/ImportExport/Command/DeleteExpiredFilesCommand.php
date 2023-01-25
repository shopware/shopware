<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Command;

use Shopware\Core\Content\ImportExport\Service\DeleteExpiredFilesService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import-export:delete-expired',
    description: 'Deletes expired files',
)]
#[Package('system-settings')]
class DeleteExpiredFilesCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly DeleteExpiredFilesService $deleteExpiredFilesService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = Context::createDefaultContext();

        $count = $this->deleteExpiredFilesService->countFiles($context);

        if ($count === 0) {
            $io->comment('No expired files found.');

            return self::SUCCESS;
        }

        $confirm = $io->confirm(sprintf('Are you sure that you want to delete %d expired files?', $count), false);

        if (!$confirm) {
            $io->caution('Aborting due to user input.');

            return self::SUCCESS;
        }

        $this->deleteExpiredFilesService->deleteFiles($context);
        $io->success(sprintf('Successfully deleted %d expired files.', $count));

        return self::SUCCESS;
    }
}
