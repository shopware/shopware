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
use Symfony\Component\Console\Input\InputOption;
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
            ->addArgument('plugin-namespace', InputArgument::OPTIONAL, 'Plugin namespace (PascalCase)')
            ->addOption('static', null, null, 'Plugin will create in static-plugins folder');

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
            $staticPrefix = $input->getOption('static') ? 'static-' : '';

            if (!$pluginName) {
                $pluginName = $this->askPascalCaseString('Please enter a plugin name (PascalCase)', $io);
            }

            $directory = $this->projectDir . "/custom/{$staticPrefix}plugins/" . $pluginName;

            if ($this->filesystem->exists($directory)) {
                $io->error(\sprintf('Plugin directory %s already exists', $directory));

                return self::FAILURE;
            }

            $namespace = $input->getArgument('plugin-namespace');

            if (!$namespace) {
                $namespace = $this->askPascalCaseString('Please enter a plugin namespace (PascalCase)', $io);
            }

            $configuration = new PluginScaffoldConfiguration(
                $pluginName,
                $namespace,
                $directory
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
