<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Package('core')]
class MakerCommand extends Command
{
    public function __construct(
        private readonly ScaffoldingGenerator $generator,
        private readonly ScaffoldingWriter $scaffoldingWriter,
        private readonly PluginService $pluginService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('plugin-name', InputArgument::OPTIONAL, 'Plugin name (PascalCase)');

        if (!$this->generator->hasCommandOption()) {
            return;
        }

        $this->addOption(
            $this->generator->getCommandOptionName(),
            null,
            null,
            $this->generator->getCommandOptionDescription(),
        );
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new ShopwareStyle($input, $output);

        foreach ($this->getDefinition()->getArguments() as $argument) {
            if ($input->getArgument($argument->getName())) {
                continue;
            }

            $value = $io->ask($argument->getDescription(), null, function (mixed $value) {
                if ($value === null || $value === '') {
                    // @phpstan-ignore-next-line RuntimeException is fine in console IO validators
                    throw new \RuntimeException('This value should not be blank');
                }

                return $value;
            });

            $input->setArgument($argument->getName(), $value);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $pluginName = $input->getArgument('plugin-name');

            if ($pluginName === null || $pluginName === '') {
                $io->error('Plugin name is required');

                return self::FAILURE;
            }

            $plugin = $this->pluginService->getPluginByName($pluginName, Context::createCLIContext());

            $directory = $plugin->getPath();

            if ($directory === null) {
                $io->error('Plugin base path is null');

                return self::FAILURE;
            }

            $classString = $plugin->getBaseClass();

            $ref = new \ReflectionClass($classString);

            $configuration = new PluginScaffoldConfiguration(
                $pluginName,
                $ref->getNamespaceName(),
                $directory
            );

            $this->generator->addScaffoldConfig($configuration, $input, $io);

            $stubCollection = new StubCollection();
            $this->generator->generateStubs($configuration, $stubCollection);

            $this->scaffoldingWriter->write($stubCollection, $configuration);

            $io->success('Scaffold created successfully');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
