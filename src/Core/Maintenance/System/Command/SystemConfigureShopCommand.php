<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Command;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
class SystemConfigureShopCommand extends Command
{
    public static $defaultName = 'system:configure-shop';

    private ShopConfigurator $shopConfigurator;

    private CacheClearer $cacheClearer;

    public function __construct(ShopConfigurator $shopConfigurator, CacheClearer $cacheClearer)
    {
        parent::__construct();
        $this->shopConfigurator = $shopConfigurator;
        $this->cacheClearer = $cacheClearer;
    }

    protected function configure(): void
    {
        $this->addOption('shop-name', null, InputOption::VALUE_REQUIRED, 'The name of your shop')
            ->addOption('shop-email', null, InputOption::VALUE_REQUIRED, 'Shop email address')
            ->addOption('shop-locale', null, InputOption::VALUE_REQUIRED, 'Default language locale of the shop')
            ->addOption('shop-currency', null, InputOption::VALUE_REQUIRED, 'Iso code for the default currency of the shop')
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, 'Run command in non-interactive mode')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new ShopwareStyle($input, $output);

        $this->shopConfigurator->updateBasicInformation($input->getOption('shop-name'), $input->getOption('shop-email'));

        $output->writeln('Shop configured successfully');
        $output->writeln('');

        if ($input->getOption('shop-locale')) {
            if (!$input->getOption('no-interaction')) {
                if (!$output->confirm('Changing the shops default locale after the fact can be destructive. Are you sure you want to continue', false)) {
                    $output->writeln('Aborting due to user input');

                    return 0;
                }
            }

            $this->shopConfigurator->setDefaultLanguage($input->getOption('shop-locale'));
            $output->writeln('Successfully changed shop default language');
            $output->writeln('');
        }

        if ($input->getOption('shop-currency')) {
            if (!$input->getOption('no-interaction')) {
                if (!$output->confirm('Changing the shops default currency after the fact can be destructive. Are you sure you want to continue', false)) {
                    $output->writeln('Aborting due to user input');

                    return 0;
                }
            }

            $this->shopConfigurator->setDefaultCurrency($input->getOption('shop-currency'));
            $output->writeln('Successfully changed shop default currency');
            $output->writeln('');
        }

        $this->cacheClearer->clear();

        return 0;
    }
}
