<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use Shopware\Core\Framework\FeatureFlag\FeatureFlagGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FeatureFlagCreateCommand extends ContainerAwareCommand
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

    protected function configure()
    {
        parent::configure();
        $this->addArgument('name', InputArgument::REQUIRED, 'What is the feature gonna be called?');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var string $tenantId */
        $name = $input->getArgument('name');

        $this->generator->exportPhp('Flag', $name, __DIR__ . '/../../Flag');
        $this->generator->exportJs($name, __DIR__ . '/../../../Administration/Resources/administration/src/flag');

        $output->writeln('Created flag: ' . $name);
    }
}
