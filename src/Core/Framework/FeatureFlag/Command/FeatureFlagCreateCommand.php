<?php declare(strict_types=1);

namespace Shopware\Core\Framework\FeatureFlag\Command;

use Shopware\Core\Framework\FeatureFlag\FeatureFlagGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FeatureFlagCreateCommand extends Command
{
    /**
     * @var FeatureFlagGenerator
     */
    private $generator;

    public function __construct(FeatureFlagGenerator $generator)
    {
        parent::__construct();

        $this->generator = $generator;
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('name', InputArgument::REQUIRED, 'What is the feature gonna be called?');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $name */
        $name = $input->getArgument('name');

        $io->title("Creating feature flag: $name");

        $phpFlag = $this->generator
            ->exportPhp('Flag', $name, __DIR__ . '/../../../Flag');

        $jsFlag = $this->generator
            ->exportJs($name, __DIR__ . '/../../../../Administration/Resources/administration/src/flag');

        $envName = $this->generator
            ->getEnvironmentName($name);

        $io->table(
            ['Type', 'Value'], [
                ['PHP-Flag', realpath($phpFlag)],
                ['JS-Flag', realpath($jsFlag)],
                ['Constant', $envName],
            ]
        );

        $io->success("Created flag: $name");
        $io->note('Please remember to add and commit the files');
    }
}
