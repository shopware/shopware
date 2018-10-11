<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateMigrationCommand extends ContainerAwareCommand
{
    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var string
     */
    private $pluginDir;

    public function __construct(string $projectDir, string $pluginDir)
    {
        parent::__construct();
        $this->projectDir = $projectDir;
        $this->pluginDir = $pluginDir;
    }

    protected function configure()
    {
        $this->setName('database:create-migration')
            ->addArgument('directory', InputArgument::OPTIONAL, '', null)
            ->addArgument('namespace', InputArgument::OPTIONAL, '', null)
            ->addOption('plugin', 'p', InputOption::VALUE_REQUIRED, '')
            ->addOption('name', '',InputOption::VALUE_REQUIRED,
                'An optional descriptive name for the migration which will be used as a suffix for the filename.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Creating migration...');
        $directory = (string) $input->getArgument('directory');
        $namespace = (string) $input->getArgument('namespace');
        $name = $input->getOption('name') ?? '';

        if ($directory && !$namespace) {
            throw new InvalidArgumentException('Please specify both dir and namespace or none.');
        }

        // Both dir and namespace were given
        if ($directory) {
            $this->createMigrationFile($name, $output, realpath($directory), $namespace);

            return;
        }

        $plugin = $input->getOption('plugin');
        if ($plugin) {
            $pluginPath = $this->pluginDir . '/' . $plugin . '/';
            if (!file_exists($pluginPath)) {
                throw new InvalidArgumentException('Plugin "' . $plugin . '" does not exist.');
            }

            if (!file_exists($pluginPath . 'Migration/')) {
                mkdir($pluginPath . 'Migration/');
            }

            $directory = $pluginPath . 'Migration/';
            $namespace = $plugin . '\\Migration';
            $output->writeln('Creating plugin-migration ...');
        } else {
            // We create a core-migration in case no plugin was given
            $directory = $this->projectDir . '/vendor/shopware/platform/src/Core/Migration/';
            $namespace = 'Shopware\\Core\\Migration';

            $output->writeln('Creating core-migration ...');
        }

        $this->createMigrationFile($name, $output, $directory, $namespace);
    }

    protected function createMigrationFile(string $name, OutputInterface $output, string $directory, string $namespace): void
    {
        if (!preg_match('/^[a-zA-Z0-9\_]*$/', $name)) {
            throw new InvalidArgumentException('Migrationname contains forbidden characters!');
        }

        $timestamp = (new \DateTime())->getTimestamp();
        $path = rtrim($directory, '/') . '/Migration' . $timestamp . $name . '.php';
        $file = fopen($path, 'w');

        $template = file_get_contents(realpath(__DIR__ . '/../Migration/Template/MigrationTemplate.txt'));

        fwrite($file, str_replace(['%%namespace%%', '%%timestamp%%'], [$namespace, $timestamp], $template));
        fclose($file);

        $output->writeln('Migration created: "' . $path . '"');
    }
}
