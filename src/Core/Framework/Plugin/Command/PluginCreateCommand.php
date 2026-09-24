<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

#[Package('framework')]
#[AsCommand(
    name: 'plugin:create',
    description: 'Creates a new plugin',
)]
class PluginCreateCommand extends Command
{
    private const PASCAL_CASE_PATTERN = '/^[A-Z][a-zA-Z0-9]*$/';

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
            ->addOption('static', null, null, 'Plugin will be created in the static-plugins folder')
            ->addOption('no-scaffold', null, null, 'Create only the required plugin files, skip all optional scaffold files');

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
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \InvalidArgumentException('This command accepts only an instance of "ConsoleOutputInterface".');
        }

        $io = new SymfonyStyle($input, $output);
        $helper = new QuestionHelper();

        // is only set once the plugin is written, so the clean up can never remove a pre-existing plugin
        $createdDirectory = null;

        try {
            // check if shopware project
            if (!is_dir($this->projectDir . '/custom/plugins')) {
                throw new \InvalidArgumentException('This command must be run inside a Shopware project.');
            }

            // initialize plugin configuration variables
            $name = '';
            $namespace = '';

            // validate relationship of flags and provided arguments
            $noScaffold = (bool) $input->getOption('no-scaffold');
            $interactive = $input->isInteractive();
            $static = $input->getOption('static') ? 'static-' : '';
            $nameArg = (string) $input->getArgument('plugin-name');
            $namespaceArg = (string) $input->getArgument('plugin-namespace');

            // require plugin name and namespace in non-interactive mode
            if (!$interactive && ($nameArg === '' || $namespaceArg === '')) {
                throw new \InvalidArgumentException('Plugin name and namespace are required in non-interactive mode.');
            }

            // validate plugin name argument
            if ($nameArg !== '') {
                if ($this->isValidPluginName($nameArg)) {
                    $name = $nameArg;
                } else {
                    throw new \InvalidArgumentException('Invalid plugin name provided. Use PascalCase format.');
                }
            }

            // validate plugin namespace argument
            if ($namespaceArg !== '') {
                if ($this->isValidPluginNamespace($namespaceArg)) {
                    $namespace = $namespaceArg;
                } else {
                    throw new \InvalidArgumentException('Invalid plugin namespace provided. Use PascalCase format.');
                }
            }

            // prompt (only) for missing configuration options
            if ($interactive) {
                // write permanent section title (not affected by $section->clear())
                $io->section('Plugin Details');

                // use a separate output section, so the prompts can be replaced by their answers
                $detailsSection = $output->section();

                // prompt for the plugin name if it was not provided as an argument
                if ($name === '') {
                    $detailsSection->writeln('<options=bold>Plugin Name</>');
                    $detailsSection->writeln("<fg=gray>Provide a name for your plugin in PascalCase (e.g. MyExtension).</>\n");

                    $nameQuestion = new Question('<fg=green>Enter a plugin name:</>');
                    $validation = Validation::createCallable(
                        new Assert\NotBlank(message: 'The plugin name cannot be empty.'),
                        new Assert\Regex(
                            pattern: self::PASCAL_CASE_PATTERN,
                            message: 'The plugin name must be in PascalCase.',
                        ),
                    );

                    $nameQuestion->setValidator($validation);
                    $name = (string) $helper->ask($input, $detailsSection, $nameQuestion);
                    $detailsSection->clear();
                }

                // prompt for the plugin namespace if it was not provided as an argument
                if ($namespace === '') {
                    $detailsSection->writeln('<options=bold>Plugin Name: </>' . $name);
                    $detailsSection->writeln('');
                    $detailsSection->writeln('<options=bold>Plugin Namespace</>');
                    $detailsSection->writeln("<fg=gray>Provide a namespace for your plugin in PascalCase (e.g. MyVendor).</>\n");

                    $namespaceQuestion = new Question('<fg=green>Enter a plugin namespace:</>');
                    $validation = Validation::createCallable(
                        new Assert\NotBlank(message: 'The plugin namespace cannot be empty.'),
                        new Assert\Regex(
                            pattern: self::PASCAL_CASE_PATTERN,
                            message: 'The plugin namespace must be in PascalCase.',
                        ),
                    );

                    $namespaceQuestion->setValidator($validation);
                    $namespace = (string) $helper->ask($input, $detailsSection, $namespaceQuestion);
                    $detailsSection->clear();
                }

                $detailsSection->writeln('<options=bold>Plugin Name: </>' . $name);
                $detailsSection->writeln('<options=bold>Plugin Namespace: </>' . $namespace);
                $detailsSection->writeln('');
            }

            $directory = \sprintf('%s/custom/%splugins/%s', $this->projectDir, $static, $name);

            if ($this->filesystem->exists($directory)) {
                throw new \InvalidArgumentException(\sprintf('Plugin directory %s already exists', $directory));
            }

            $configuration = new PluginScaffoldConfiguration(
                $name,
                $namespace,
                $directory
            );

            // the summary keeps the confirmed scaffolding on screen, the questions below it are cleared once answered
            $summarySection = null;
            $questionSection = null;

            // if additional scaffolding is not disabled via flag, ask the user whether to add it
            if ($interactive && !$noScaffold) {
                // permanent section title
                $io->section('Additional Scaffolding');

                $summarySection = $output->section();
                $questionSection = $output->section();

                $question = new ConfirmationQuestion(
                    '<fg=green>Add additional scaffolding (advanced)? [y/N]:</>',
                    false,
                    '/^(y|j)/i'
                );

                $noScaffold = !$helper->ask($input, $questionSection, $question);
                $questionSection->clear();

                if ($noScaffold) {
                    $summarySection->writeln('No additional scaffolding.');
                }
            }

            // the generators without a command option create the required plugin files and therefore always run,
            // the optional ones ask for their own scaffolding options
            $addedScaffolding = [];

            foreach ($this->generators as $generator) {
                if ($noScaffold && $generator->hasCommandOption()) {
                    continue;
                }

                $generator->addScaffoldConfig($configuration, $input, $output);

                $questionSection?->clear();

                // the generator only sets its option if the user confirmed it, so it belongs into the summary
                if ($generator->hasCommandOption() && $configuration->hasOption($generator->getCommandOptionName())) {
                    $optionTitle = $generator->getCommandOptionTitle();
                    $optionValue = $configuration->getOption($generator->getCommandOptionName());

                    if (\is_array($optionValue) && $optionValue !== []) {
                        $optionTitle .= ': ' . implode(', ', $optionValue);
                    }

                    $addedScaffolding[] = $optionTitle;

                    $summarySection?->clear();
                    $summarySection?->writeln('<options=bold>Adding the following scaffolding:</>');

                    foreach ($addedScaffolding as $optionTitle) {
                        $summarySection?->writeln(' - ' . $optionTitle);
                    }
                    $summarySection?->writeln('');
                }
            }

            $stubCollection = $this->scaffoldingCollector->collect($configuration);

            if ($interactive) {
                $finishSection = $output->section();
                $finishSection->writeln('Gathered all necessary information for plugin generation.');
                $finishSection->writeln('');

                $question = new ConfirmationQuestion('<fg=green>Generate Plugin? [Y/n]:</>', true, '/^(y|j)/i');
                $generate = $helper->ask($input, $finishSection, $question);

                $finishSection->clear();

                if (!$generate) {
                    $io->warning('Plugin generation was cancelled.');

                    return Command::SUCCESS;
                }
            }

            $createdDirectory = $directory;
            $this->scaffoldingWriter->write($stubCollection, $configuration);

            $io->writeln("<fg=green;options=bold>✔ Successfully generated Plugin</>\n");

            $io->section('What\'s next?');
            $io->writeln('1) run plugin:install');
            $io->writeln('2) run build admin');
            $io->writeln('3) add storefront javascript code');

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            // clean up the partially created plugin
            if ($createdDirectory !== null && $this->filesystem->exists($createdDirectory)) {
                $this->filesystem->remove($createdDirectory);
            }

            return self::FAILURE;
        }
    }

    private function isValidPluginName(string $pluginName): bool
    {
        return preg_match(self::PASCAL_CASE_PATTERN, $pluginName) === 1;
    }

    private function isValidPluginNamespace(string $pluginNamespace): bool
    {
        return preg_match(self::PASCAL_CASE_PATTERN, $pluginNamespace) === 1;
    }
}
