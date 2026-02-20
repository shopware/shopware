<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Shopware\Core\Framework\Plugin\PluginException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'plugin:create',
    description: 'Creates a new plugin',
)]
#[Package('framework')]
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
            ->addOption('static', null, null, 'Plugin will be created in the static-plugins folder');

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
                $pluginName = $this->askPascalCaseString(
                    input: $input,
                    questionText: 'Please enter a plugin name (PascalCase)',
                    io: $io
                );
            }

            $directory = \sprintf('%s/custom/%splugins/%s', $this->projectDir, $staticPrefix, $pluginName);

            if ($this->filesystem->exists($directory)) {
                $io->error(\sprintf('Plugin directory %s already exists', $directory));

                return self::FAILURE;
            }

            $namespace = $input->getArgument('plugin-namespace');

            if (!$namespace) {
                $namespace = $this->askPascalCaseString(
                    input: $input,
                    questionText: 'Please enter a plugin namespace (PascalCase)',
                    io: $io
                );
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

    private function askPascalCaseString(
        InputInterface $input,
        string $questionText,
        SymfonyStyle $io
    ): string {
        if (!$input->isInteractive()) {
            throw PluginException::invalidPluginCreationInputError('This command requires interactive mode or the argument must be provided.');
        }

        $question = new Question($questionText);
        $question->setValidator(function (?string $answer) {
            if ($answer === null || $answer === '') {
                throw PluginException::invalidPluginCreationInputError('Answer cannot be empty');
            }

            if (!ctype_upper($answer[0])) {
                throw PluginException::invalidPluginCreationInputError('The name must start with an uppercase character');
            }

            return $answer;
        });

        return $io->askQuestion($question);
    }
}
