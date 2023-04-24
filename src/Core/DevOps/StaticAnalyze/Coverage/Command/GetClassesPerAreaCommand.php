<?php

namespace Shopware\Core\DevOps\StaticAnalyze\Coverage\Command;

use Composer\Autoload\ClassLoader;
use Composer\Composer;
use Shopware\Core\DevOps\StaticAnalyze\Coverage\CoveragePerArea;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ErrorHandler\Error\ClassNotFoundError;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'coverage:classes-per-area',
    description: 'Output all classes of the Shopware-namespace aggregated by area.

  In order for this command to work properly, you need to dump the composer autoloader before running it:
  $ composer dump-autoload -o
'
)]
#[Package('core')]
class GetClassesPerAreaCommand extends Command
{
    private const OPTION_JSON = 'json';
    private const OPTION_PRETTY = 'pretty-print';

    private ClassLoader $classLoader;

    /**
     * @internal
     */
    public function __construct(
        private readonly string $projectDir,
    ) {
        $this->classLoader = require $this->projectDir . '/vendor/autoload.php';

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            self::OPTION_JSON,
            'j',
            InputOption::VALUE_NONE,
            'Output as JSON'
        );

        $this->addOption(
            self::OPTION_PRETTY,
            'H',
            InputOption::VALUE_NONE,
            'Format output to be human-readable'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption(self::OPTION_JSON)) {
            $output->write(
                json_encode(
                    $this->getClassesPerArea(),
                    $input->getOption(self::OPTION_PRETTY) ? JSON_PRETTY_PRINT : 0
                )
            );
        } else {
            $output->write(
                var_export($this->getClassesPerArea(), true)
            );
        }

        return 0;
    }

    private function getClassesPerArea()
    {
        $areas = [];

        foreach ($this->getShopwareClasses() as $class => $path) {
            try {
                $area = Package::getPackageName($class);
            } catch (\Throwable $e) {
                $areas['unknown'][$class] = $path;
                continue;
            }

            if (!is_string($area)) {
                continue;
            }

            $areaTrim = strstr($area, PHP_EOL, true) ?: $area;

            if (!is_string($areaTrim)) {
                continue;
            }

            $areas[trim($areaTrim)][$class] = $path;
        }

        return $areas;
    }

    /**
     * @return array<array<string, string>>
     */
    private function getShopwareClasses(): array
    {
        return array_filter($this->classLoader->getClassMap(), static function (string $class): bool {
            return str_starts_with($class, 'Shopware\\');
        }, ARRAY_FILTER_USE_KEY);
    }
}
