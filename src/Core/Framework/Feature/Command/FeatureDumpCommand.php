<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Feature\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FeatureDumpCommand extends Command
{
    protected static $defaultName = 'feature:dump';

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
            ->setAliases(['administration:dump:features'])
            ->setDescription('Creating json file with feature config for js testing and hot reloading capabilities.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        file_put_contents(
            $this->kernel->getCacheDir() . '/../../config_js_features.json',
            json_encode(Feature::getAll())
        );

        $style = new ShopwareStyle($input, $output);
        $style->success('Successfully dumped js feature configuration');

        return self::SUCCESS;
    }
}
