<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Command;

use Shopware\Core\Framework\Changelog\Processor\ChangelogReleaseCreator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: 'changelog:release',
    description: 'Creates a new changelog release',
)]
#[Package('core')]
class ChangelogReleaseCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly ChangelogReleaseCreator $releaseCreator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Collect all markdown files, which do not have a flag meta field, inside the `/changelog/_unreleased` directory and move them to a new directory for the release in `/changelog/release-6-x-x-x`. After that the command will update the global `/CHANGELOG.md` file with a new section for the release with a list of links to the single changelog files. For major and minor releases it will also create or update the corresponding UPGRADE-6.x.md file with the markdown content from the "Upgrade Information" section of the single changelog files.')
            ->addArgument('version', InputArgument::OPTIONAL, 'A version of release. It should be 4-digits type')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Use the --dry-run argument to preview the changelog content and prevent actually writing to file.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Use the --force argument to override an existing release.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $IOHelper = new SymfonyStyle($input, $output);
        $IOHelper->title('Creating or updating the final changelog for a new release');

        $version = $input->getArgument('version')
            ?? $IOHelper->ask('A version of release', null, function ($version) {
                if (!$version) {
                    throw new \RuntimeException('Version of release is required.');
                }

                return $version;
            });
        if (!preg_match("/^\d+(\.\d+){3}$/", (string) $version)) {
            throw new \RuntimeException('Invalid version of release ("' . $version . '"). It should be 4-digits type');
        }

        $force = $input->getOption('force');
        if ($force && !$IOHelper->confirm('You are using "-f" argument. It could override an existing release before. Are you sure?', false)) {
            return self::FAILURE;
        }

        $outputArray = $this->releaseCreator->release($version, (bool) $force, $input->getOption('dry-run'));
        $IOHelper->writeln($outputArray);

        $IOHelper->success('Released the given version successfully');

        return self::SUCCESS;
    }
}
