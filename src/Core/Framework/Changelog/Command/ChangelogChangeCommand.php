<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Command;

use Shopware\Core\Framework\Changelog\ChangelogSection;
use Shopware\Core\Framework\Changelog\Processor\ChangelogReleaseExporter;
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
    name: 'changelog:change',
    description: 'Changes the changelog of a release',
)]
#[Package('core')]
class ChangelogChangeCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly ChangelogReleaseExporter $releaseExporter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('version', InputArgument::OPTIONAL, 'A version of release. It should be 4-digits type. Please leave it blank for the unreleased version.')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Renders the output of the command in a markdown file under the given path', '');
        foreach (ChangelogSection::cases() as $changelogSection) {
            $this->addOption($changelogSection->name, null, InputOption::VALUE_NONE, sprintf('Returns all documented changes in the "%s" section', $changelogSection->value));
        }
        $this->addOption('include-feature-flags', null, InputOption::VALUE_NONE, 'Returns all changes, including features which are still behind a feature flag.')
            ->addOption('keys-only', null, InputOption::VALUE_NONE, 'Returns only Jira ticket keys of all changes made.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $IOHelper = new SymfonyStyle($input, $output);
        $IOHelper->title('Get all changes made in the given version');

        $version = $input->getArgument('version');
        if (!empty($version) && !preg_match("/^\d+(\.\d+){3}$/", $version)) {
            throw new \RuntimeException('Invalid version of release. It should be 4-digits type');
        }

        $includeFeatureFlags = $input->getOption('include-feature-flags');
        if (!empty($version) && $includeFeatureFlags) {
            $IOHelper->warning('You cannot use `include-feature-flags` argument for an existing release version.');
            $includeFeatureFlags = false;
        }

        $outputArray = $this->releaseExporter->export($this->getRequestedSection($input), $version, $includeFeatureFlags, $input->getOption('keys-only'));

        $path = $input->getOption('path') ?: '';
        if (\is_string($path) && $path !== '') {
            file_put_contents($path, implode("\n", $outputArray));
            $IOHelper->writeln('* Pushed all changelogs into ' . $path);
        } else {
            $IOHelper->writeln($outputArray);
        }

        $IOHelper->newLine();
        $IOHelper->success('Done');

        return self::SUCCESS;
    }

    /**
     * @return array<string, bool>
     */
    private function getRequestedSection(InputInterface $input): array
    {
        $requested = [];
        foreach (ChangelogSection::cases() as $changelogSection) {
            $requested[$changelogSection->name] = $input->getOption($changelogSection->name);
        }

        return \in_array(true, $requested, true) ? $requested : array_fill_keys(array_keys($requested), true);
    }
}
