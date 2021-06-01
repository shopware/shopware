<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Command;

use Shopware\Core\Framework\Changelog\Processor\ChangelogReleaseExporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ChangelogChangeCommand extends Command
{
    protected static $defaultName = 'changelog:change';

    /**
     * @var ChangelogReleaseExporter
     */
    private $releaseExporter;

    public function __construct(ChangelogReleaseExporter $releaseExporter)
    {
        parent::__construct();
        $this->releaseExporter = $releaseExporter;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Returns all changes made in a specific / unreleased version.')
            ->addArgument('version', InputArgument::OPTIONAL, 'A version of release. It should be 4-digits type. Please leave it blank for the unreleased version.')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Renders the output of the command in a markdown file under the given path', '')
            ->addOption('core', null, InputOption::VALUE_NONE, 'Returns all changes made in the Core')
            ->addOption('api', null, InputOption::VALUE_NONE, 'Returns all changes made in the API')
            ->addOption('storefront', null, InputOption::VALUE_NONE, 'Returns all changes made in the Storefront')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Returns all changes made in the Administration')
            ->addOption('upgrade', null, InputOption::VALUE_NONE, 'Returns all changes documented in the Upgrade Information')
            ->addOption('include-feature-flags', null, InputOption::VALUE_NONE, 'Returns all changes, including features which are still behind a feature flag.')
            ->addOption('keys-only', null, InputOption::VALUE_NONE, 'Returns only Jira ticket keys of all changes made.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $IOHelper = new SymfonyStyle($input, $output);
        $IOHelper->title('Get all changes made in the given version');

        /** @var string $version */
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

    private function getRequestedSection(InputInterface $input): array
    {
        $requested = [
            'core' => $input->getOption('core'),
            'api' => $input->getOption('api'),
            'storefront' => $input->getOption('storefront'),
            'admin' => $input->getOption('admin'),
            'upgrade' => $input->getOption('upgrade'),
        ];

        return \in_array(true, array_values($requested), true) ? $requested : array_fill_keys(array_keys($requested), true);
    }
}
