<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Feature\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'feature:dump', description: 'Dumps all features', aliases: ['administration:dump:features'])]
#[Package('core')]
class FeatureDumpCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly Kernel $kernel)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        file_put_contents(
            $this->kernel->getProjectDir() . '/var/config_js_features.json',
            json_encode(Feature::getAll(), \JSON_THROW_ON_ERROR)
        );

        $style = new ShopwareStyle($input, $output);
        $style->success('Successfully dumped js feature configuration');

        return self::SUCCESS;
    }
}
