<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Console;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Package('core')]
class ShopwareStyle extends SymfonyStyle
{
    public function createProgressBar(int $max = 0): ProgressBar
    {
        $progressBar = parent::createProgressBar($max);

        $character = (string) EnvironmentHelper::getVariable('PROGRESS_BAR_CHARACTER', '');
        if ($character) {
            $progressBar->setProgressCharacter($character);
        }

        $progressBar->setBarCharacter('<fg=magenta>=</>');

        return $progressBar;
    }
}
