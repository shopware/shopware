<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Command;

use Shopware\Core\Framework\Changelog\Processor\ChangelogValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: 'changelog:check',
    description: 'Checks the changelog for errors',
)]
#[Package('core')]
class ChangelogCheckCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly ChangelogValidator $validator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('changelog', InputArgument::OPTIONAL, 'The path of changelog file which need to check.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $IOHelper = new SymfonyStyle($input, $output);
        $IOHelper->title('Check the validation of changelog files');

        $path = $input->getArgument('changelog') ?: '';
        if (\is_string($path) && $path !== '' && !file_exists($path)) {
            $IOHelper->error('The given file NOT found');

            return self::FAILURE;
        }

        $outputArray = $this->validator->check($path);
        $errorCount = \count($outputArray);
        if ($errorCount) {
            foreach ($outputArray as $file => $violations) {
                $IOHelper->writeln((string) $file);
                $IOHelper->writeln(array_map(static fn ($message) => '* ' . $message, $violations));
                $IOHelper->newLine();
            }
            $IOHelper->error(sprintf('You have %d syntax errors in changelog files.', $errorCount));

            return self::FAILURE;
        }

        $IOHelper->success('Done');

        return self::SUCCESS;
    }
}
