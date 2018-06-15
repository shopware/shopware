<?php declare(strict_types=1);

namespace Shopware\Storefront\Command;

use Shopware\Core\System\Command\TouchpointCreateCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TouchpointCreateStorefrontCommand extends TouchpointCreateCommand
{
    protected function configure()
    {
        parent::configure();

        $this->setName('touchpoint:create:storefront')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'App URL for storefront')
        ;
    }

    protected function getType(): string
    {
        return 'storefront';
    }

    protected function getTouchpointConfiguration(InputInterface $input, OutputInterface $output): array
    {
        return [
            'domains' => [
                ['url' => $input->getOption('url'), 'language' => $input->getOption('languageId')],
            ],
        ];
    }
}
