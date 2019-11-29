<?php declare(strict_types=1);

namespace Shopware\Administration\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
use Shopware\Core\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AdministrationDumpFeaturesCommand extends Command
{
    protected static $defaultName = 'administration:dump:features';

    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(Kernel $kernel)
    {
        parent::__construct();
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Creating json file with feature config for administration testing and hot reloading capabilities.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        file_put_contents(
            $this->kernel->getCacheDir() . '/../../config_administration_features.json',
            json_encode(FeatureConfig::getAll())
        );

        $style = new ShopwareStyle($input, $output);
        $style->success('Successfully dumped administration feature configuration');

        return 0;
    }
}
