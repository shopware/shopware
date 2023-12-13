<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Coverage\Command;

use Composer\Autoload\ClassLoader;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
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
    public const OPTION_JSON = 'json';
    public const OPTION_PRETTY = 'pretty-print';

    public const OPTION_GENERATE_PHPUNIT_TEST = 'generate-phpunit-test';

    public const OPTION_NAMESPACE_PATTERN = 'ns-pattern';

    public const NAMESPACE_PATTERN_DEFAULT = '#^Shopware\\\\(Core|Administration|Storefront|Elasticsearch)\\\\#';

    private ClassLoader $classLoader;

    private string $nsPattern;

    /**
     * @internal
     */
    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();

        $this->classLoader = require $this->projectDir . '/vendor/autoload.php';
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

        $this->addOption(
            self::OPTION_GENERATE_PHPUNIT_TEST,
            'g',
            InputOption::VALUE_NONE,
            'Generate phpunit.xml'
        );

        $this->addOption(
            self::OPTION_NAMESPACE_PATTERN,
            null,
            InputOption::VALUE_REQUIRED,
            'The pattern the namespace has to match',
            self::NAMESPACE_PATTERN_DEFAULT,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->nsPattern = $input->getOption(self::OPTION_NAMESPACE_PATTERN);

        $classesPerArea = $this->getClassesPerArea();
        if ($input->getOption(self::OPTION_JSON)) {
            $output->write(
                json_encode(
                    $classesPerArea,
                    $input->getOption(self::OPTION_PRETTY) ? \JSON_PRETTY_PRINT : 0
                ) ?: ''
            );
        } else {
            $output->write(
                var_export(
                    $classesPerArea,
                    true
                )
            );
        }

        if ($input->getOption(self::OPTION_GENERATE_PHPUNIT_TEST)) {
            $unitFiles = [];
            foreach ($classesPerArea as $area => $classToFile) {
                $unitFile = new \DOMDocument();
                // Load phpunit template
                $unitFile->load('phpunit.xml.dist');
                $unitDocument = $unitFile->documentElement;
                if ($unitDocument === null) {
                    return 1;
                }
                $source = $unitDocument->getElementsByTagName('source')->item(0);
                if ($source === null) {
                    return 1;
                }
                $includeChildElement = $source->getElementsByTagName('include')->item(0);
                if ($includeChildElement === null) {
                    return 1;
                }
                // Remove include from source to create our own includes
                $source->removeChild($includeChildElement);
                $includeElement = $unitFile->createElement('include');

                foreach ($classToFile as $class => $file) {
                    $fileElement = $unitFile->createElement('file', $file);
                    $includeElement->appendChild($fileElement);
                }
                $source->appendChild($includeElement);

                // Create phpunit file per area
                file_put_contents("phpunit.$area.xml", $unitFile->saveXML());
            }
        }

        return 0;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getClassesPerArea(): array
    {
        $areas = [];

        foreach ($this->getShopwareClasses() as $class => $path) {
            $area = Package::getPackageName($class);

            if (!\is_string($area)) {
                continue;
            }

            $areaTrim = strstr($area, \PHP_EOL, true) ?: $area;

            $areas[trim($areaTrim)][$class] = $path;
        }

        return $areas;
    }

    /**
     * @return array<string, string>
     */
    private function getShopwareClasses(): array
    {
        return array_filter($this->classLoader->getClassMap(), function (string $class): bool {
            if (str_starts_with($class, 'Shopware\\')) {
                return (bool) preg_match($this->nsPattern, $class);
            }

            return false;
        }, \ARRAY_FILTER_USE_KEY);
    }
}
