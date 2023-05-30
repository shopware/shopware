<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'database:create-migration',
    description: 'Creates a new migration file',
)]
#[Package('core')]
class CreateMigrationCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly KernelPluginCollection $kernelPluginCollection,
        private readonly string $coreDir,
        private readonly string $shopwareVersion
    ) {
        parent::__construct();
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

        if (!preg_match('/^[a-zA-Z0-9\_]*$/', (string) $name)) {
            throw new \InvalidArgumentException('Migrationname contains forbidden characters!');
        }

        if ($directory && !$namespace) {
            throw new \InvalidArgumentException('Please specify both dir and namespace or none.');
        }

        $timestamp = (new \DateTime())->getTimestamp();

        // Both dir and namespace were given
        if ($directory) {
            $this->createMigrationFile($output, (string) realpath($directory), \dirname(__DIR__) . '/Template/MigrationTemplate.txt', [
                '%%timestamp%%' => $timestamp,
                '%%name%%' => $name,
                '%%namespace%%' => $namespace,
            ]);

            return self::SUCCESS;
        }

        $pluginName = $input->getOption('plugin');
        if ($pluginName) {
            $pluginBundles = array_filter($this->kernelPluginCollection->all(), static fn (Plugin $value) => mb_strpos($value->getName(), (string) $pluginName) === 0);

            if (\count($pluginBundles) === 0) {
                throw new \RuntimeException(sprintf('Plugin "%s" could not be found.', $pluginName));
            }

            if (\count($pluginBundles) > 1) {
                $pluginBundles = array_filter($pluginBundles, static fn (Plugin $value) => $pluginName === $value->getName());

                if (\count($pluginBundles) > 1) {
                    throw new \RuntimeException(
                        sprintf(
                            'More than one pluginname starting with "%s" was found: %s',
                            $pluginName,
                            implode(';', array_keys($pluginBundles))
                        )
                    );
                }
            }

            $pluginBundle = array_values($pluginBundles)[0];

            $directory = $pluginBundle->getMigrationPath();
            if (!file_exists($directory) && !mkdir($directory) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Migrationdirectory "%s" could not be created', $directory));
            }

            $namespace = $pluginBundle->getMigrationNamespace();
            $output->writeln(sprintf('Creating plugin-migration with namespace %s in path %s...', $namespace, $directory));
        } else {
            [$_, $major] = explode('.', $this->shopwareVersion);
            // We create a core-migration in case no plugin was given
            $directory = $this->coreDir . '/Migration/V6_' . $major;
            $namespace = 'Shopware\\Core\\Migration\\V6_' . $major;
        }

        $params = [
            '%%timestamp%%' => $timestamp,
            '%%name%%' => $name,
            '%%namespace%%' => $namespace,
        ];

        $output->writeln('Creating core-migration ...');

        $this->createMigrationFile(
            $output,
            $directory,
            \dirname(__DIR__) . '/Template/MigrationTemplate.txt',
            $params
        );

        return self::SUCCESS;
    }

    /**
     * @param array{"%%timestamp%%": int, "%%name%%": string, "%%namespace%%": string} $params
     */
    private function createMigrationFile(OutputInterface $output, string $directory, string $templatePatch, array $params): void
    {
        $path = rtrim($directory, '/') . '/Migration' . $params['%%timestamp%%'] . $params['%%name%%'] . '.php';
        $file = fopen($path, 'wb');
        if ($file === false) {
            return;
        }

        $template = file_get_contents($templatePatch);
        if ($template === false) {
            return;
        }

        fwrite($file, str_replace(array_keys($params), array_values($params), $template));
        fclose($file);

        $output->writeln('Migration created: "' . $path . '"');
    }
}
