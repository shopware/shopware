<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Command;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateMigrationCommand extends Command
{
    protected static $defaultName = 'database:create-migration';

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var KernelPluginCollection
     */
    private $kernelPluginCollection;

    public function __construct(KernelPluginCollection $kernelPluginCollection, string $projectDir)
    {
        parent::__construct();
        $this->projectDir = $projectDir;
        $this->kernelPluginCollection = $kernelPluginCollection;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::OPTIONAL)
            ->addArgument('namespace', InputArgument::OPTIONAL)
            ->addOption('plugin', 'p', InputOption::VALUE_REQUIRED)
            ->addOption(
                'name',
                '',
                InputOption::VALUE_REQUIRED,
                'An optional descriptive name for the migration which will be used as a suffix for the filename.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Creating migration...');
        $directory = (string) $input->getArgument('directory');
        $namespace = (string) $input->getArgument('namespace');
        $name = $input->getOption('name') ?? '';

        if (!preg_match('/^[a-zA-Z0-9\_]*$/', $name)) {
            throw new \InvalidArgumentException('Migrationname contains forbidden characters!');
        }

        if ($directory && !$namespace) {
            throw new \InvalidArgumentException('Please specify both dir and namespace or none.');
        }

        // Both dir and namespace were given
        if ($directory) {
            $this->createMigrationFile($name, $output, realpath($directory), $namespace);

            return 0;
        }

        $pluginName = $input->getOption('plugin');
        if ($pluginName) {
            if (\is_array($pluginName)) {
                $pluginName = implode(' ', $pluginName);
            }
            $pluginBundles = array_filter($this->kernelPluginCollection->all(), function (Plugin $value) use ($pluginName) {
                return (int) (mb_strpos($value->getName(), (string) $pluginName) === 0);
            });

            if (\count($pluginBundles) === 0) {
                throw new \RuntimeException(sprintf('Plugin "%s" could not be found.', $pluginName));
            }

            if (\count($pluginBundles) > 1) {
                throw new \RuntimeException(
                    sprintf(
                        'More than one pluginname starting with "%s" was found: %s',
                        $pluginName,
                        implode(';', array_keys($pluginBundles))
                    )
                );
            }

            $pluginBundle = array_values($pluginBundles)[0];

            $directory = $pluginBundle->getMigrationPath();
            if (!file_exists($directory) && !mkdir($directory) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Migrationdirectory "%s" could not be created', $directory));
            }

            $namespace = $pluginBundle->getMigrationNamespace();
            $output->writeln(sprintf('Creating plugin-migration with namespace %s in path %s...', $namespace, $directory));
        } else {
            // We create a core-migration in case no plugin was given
            $directory = $this->projectDir . '/vendor/shopware/platform/src/Core/Migration/';
            $namespace = 'Shopware\\Core\\Migration';

            $output->writeln('Creating core-migration ...');
        }

        $this->createMigrationFile($name, $output, $directory, $namespace);

        return 0;
    }

    private function createMigrationFile(string $name, OutputInterface $output, string $directory, string $namespace): void
    {
        $timestamp = (new \DateTime())->getTimestamp();
        $path = rtrim($directory, '/') . '/Migration' . $timestamp . $name . '.php';
        $file = fopen($path, 'wb');

        $template = file_get_contents(\dirname(__DIR__) . '/Template/MigrationTemplate.txt');
        $params = [
            '%%namespace%%' => $namespace,
            '%%timestamp%%' => $timestamp,
            '%%name%%' => $name,
        ];
        fwrite($file, str_replace(array_keys($params), array_values($params), $template));
        fclose($file);

        $output->writeln('Migration created: "' . $path . '"');
    }
}
