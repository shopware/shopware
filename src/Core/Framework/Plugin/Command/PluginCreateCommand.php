<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'plugin:create',
    description: 'Creates a new plugin',
)]
#[Package('core')]
class PluginCreateCommand extends Command
{
    private string $composerTemplate = <<<EOL
{
  "name": "swag/plugin-skeleton",
  "description": "Skeleton plugin",
  "type": "shopware-platform-plugin",
  "license": "MIT",
  "autoload": {
    "psr-4": {
      "#namespace#\\\\": "src/"
    }
  },
  "extra": {
    "shopware-plugin-class": "#namespace#\\\\#class#",
    "label": {
      "de-DE": "Skeleton plugin",
      "en-GB": "Skeleton plugin"
    }
  }
}

EOL;

    private string $bootstrapTemplate = <<<EOL
<?php declare(strict_types=1);

namespace #namespace#;

use Shopware\Core\Framework\Plugin;

class #class# extends Plugin
{
}
EOL;

    private string $servicesXmlTemplate = <<<EOL
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>

    </services>
</container>
EOL;

    private string $configXmlTemplate = <<<EOL
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/System/SystemConfig/Schema/config.xsd">
    <card>
        <title>#pluginName# Settings</title>
        <title lang="de-DE">#pluginName# Einstellungen</title>

        <input-field type="bool">
            <name>active</name>
            <label>Active</label>
            <label lang="de-DE">Aktiviert</label>
        </input-field>
    </card>
</config>
EOL;

    private string $testBoostrap = <<<EOL
<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

\$loader = (new TestBootstrapper())
    ->addCallingPlugin()
    ->addActivePlugins('#name#')
    ->bootstrap()
    ->getClassLoader();

\$loader->addPsr4('#name#\\\\Tests\\\\', __DIR__);
EOL;

    private string $phpUnitXml = <<<EOL
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.3/phpunit.xsd"
         bootstrap="tests/TestBootstrap.php"
         executionOrder="random">
    <coverage>
        <include>
            <directory>./src/</directory>
        </include>
    </coverage>
    <php>
        <ini name="error_reporting" value="-1"/>
        <server name="KERNEL_CLASS" value="Shopware\Core\Kernel"/>
        <env name="APP_ENV" value="test"/>
        <env name="APP_DEBUG" value="1"/>
        <env name="SYMFONY_DEPRECATIONS_HELPER" value="weak"/>
    </php>
    <testsuites>
        <testsuite name="#name# Testsuite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>

EOL;

    /**
     * @internal
     *
     * @param iterable<ScaffoldingGenerator> $generators
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly ScaffoldingCollector $scaffoldingCollector,
        private readonly ScaffoldingWriter $scaffoldingWriter,
        private readonly Filesystem $filesystem,
        private readonly iterable $generators
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('plugin-name', InputArgument::OPTIONAL, 'Plugin name (PascalCase)')
            ->addArgument('plugin-namespace', InputArgument::OPTIONAL, 'Plugin namespace (PascalCase)');

        foreach ($this->generators as $generator) {
            if (!$generator->hasCommandOption()) {
                continue;
            }

            $this->addOption(
                $generator->getCommandOptionName(),
                null,
                null,
                $generator->getCommandOptionDescription()
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $pluginName = $input->getArgument('plugin-name');

            if (!$pluginName) {
                $pluginName = $this->askPascalCaseString('Please enter a plugin name (PascalCase)', $io);
            }

            $directory = $this->projectDir . '/custom/plugins/' . $pluginName;

            if ($this->filesystem->exists($directory)) {
                $io->error(sprintf('Plugin directory %s already exists', $directory));

        mkdir($directory . '/src/Resources/config/', 0777, true);
        mkdir($directory . '/tests/', 0777, true);

        $composerFile = $directory . '/composer.json';
        $bootstrapFile = $directory . '/src/' . $name . '.php';
        $servicesXmlFile = $directory . '/src/Resources/config/services.xml';
        $testFile = $directory . '/tests/TestBoostrap.php';
        $phpUnitXmlFile = $directory . '/phpunit.xml';

            if (!$namespace) {
                $namespace = $this->askPascalCaseString('Please enter a plugin namespace (PascalCase)', $io);
            }

        $bootstrap = str_replace(
            ['#namespace#', '#class#'],
            [$name, $name],
            $this->bootstrapTemplate
        );

        $test = str_replace(
            ['#name#'],
            [$name],
            $this->testBoostrap
        );

        $xml = str_replace(
            ['#name#'],
            [$name],
            $this->phpUnitXml
        );

        file_put_contents($composerFile, $composer);
        file_put_contents($bootstrapFile, $bootstrap);
        file_put_contents($servicesXmlFile, $this->servicesXmlTemplate);
        file_put_contents($testFile, $test);
        file_put_contents($phpUnitXmlFile, $xml);

        if ($input->getOption('create-config')) {
            $configXmlFile = $directory . '/src/Resources/config/config.xml';
            $configXml = str_replace(
                ['pluginName'],
                [$name],
                $this->configXmlTemplate
            );

            foreach ($this->generators as $generator) {
                $generator->addScaffoldConfig($configuration, $input, $io);
            }

            $io->info('Creating plugin files...');

            $stubCollection = $this->scaffoldingCollector->collect($configuration);

            $this->scaffoldingWriter->write($stubCollection, $configuration);

            $io->success('Plugin created successfully');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            if (isset($directory) && $this->filesystem->exists($directory)) {
                $this->filesystem->remove($directory);
            }

            return self::FAILURE;
        }
    }

    private function askPascalCaseString(string $question, SymfonyStyle $io): string
    {
        $answer = $io->ask($question);

        if (empty($answer)) {
            $io->error('Answer cannot be empty');

            return $this->askPascalCaseString($question, $io);
        }

        if (!ctype_upper((string) $answer[0])) {
            $io->error('The name must start with an uppercase character');

            return $this->askPascalCaseString($question, $io);
        }

        return $answer;
    }
}
